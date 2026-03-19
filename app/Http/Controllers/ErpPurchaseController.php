<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\ErpProduct;
use App\Models\ErpCategory;
use App\Models\ErpStockMovement;
use App\Models\ErpPurchase;
use App\Models\ErpPurchaseDetail;
use App\Models\ErpSupplier;

use App\Models\CfdiCliente;

use App\Services\InventoryService;

use Exception;

use Carbon\Carbon;

class ErpPurchaseController extends Controller
{
    public function index(Request $request)
    {
        $query = ErpPurchase::noEliminados()
            ->with('supplier')
            // ->with('detalles')
            ->withSum('detalles as nro_productos', 'cantidad');

        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();

            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $coleccion = $query->get();

        return response()->json([
            'success' => true,
            'data'=>$coleccion
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:cfdi_clientes,id',
            'fecha_compra' => 'required|date',
            'folio' => 'nullable|string',
            'tipo_documento' => 'nullable|string',
            'metodo_pago' => 'nullable|string',
            'forma_pago' => 'nullable|string',
            'moneda' => 'nullable|string|max:10',
            'tipo_cambio' => 'nullable|numeric|min:0',
            'notas' => 'nullable|string',
            'user_id' => 'required|numeric',
            'detalles' => 'required|array|min:1',
            'detalles.*.product_id' => 'required|exists:erp_products,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.0001',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.porcentaje_desc' => 'required|numeric|min:0',
            'detalles.*.porcentaje_impuesto' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data' => $validator->errors()
            ], 422);
        }

        $proveedor = CfdiCliente::noEliminados()
            ->where('id', $request->input('supplier_id'))
            ->first();
        if (!$proveedor)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Proveedor con id '.$request->input('supplier_id')
            ], 404);
        }

        $user = User::buscarPorId($request->input('user_id'));
        if (!$user)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'Usuario no encontrado.'
            ], 404);
        }

        foreach ($request->detalles as $detalle) {
            $producto = ErpProduct::noEliminados()
                ->where('id', $detalle['product_id'])
                ->first();
            if (!$producto)
            {
                // Devolvemos error codigo http 404
                return response()->json([
                    'success' => false,
                    'message'=>'No existe el producto con ID '.$detalle['product_id']
                ], 404);
            }
        }

        DB::beginTransaction();

        try {
            $purchase = ErpPurchase::create([
                'supplier_id' => $request->supplier_id,
                'fecha_compra' => $request->fecha_compra,
                'folio' => $request->folio,
                'tipo_documento' => $request->tipo_documento,
                'metodo_pago' => $request->metodo_pago,
                'forma_pago' => $request->forma_pago,
                'moneda' => $request->moneda ?? 'MXN',
                'tipo_cambio' => $request->tipo_cambio ?? 1,
                'notas' => $request->notas,
                'user_id' => $request->user_id,
            ]);

            $subtotalGeneral = $descuentoGeneral = $impuestoGeneral = $totalGeneral = 0;

            foreach ($request->detalles as $detalle) {
                $producto = ErpProduct::find($detalle['product_id']);

                if (!$producto) {
                    throw new \Exception("El producto con ID {$detalle['product_id']} no existe.");
                }

                // Porcentajes de descuento e impuesto
                $porcentaje_desc = isset($detalle['porcentaje_desc']) ? round($detalle['porcentaje_desc'], 2) : 0;
                $porcentaje_impuesto = isset($detalle['porcentaje_impuesto']) ? round($detalle['porcentaje_impuesto'], 2) : 0;

                // Cálculos base
                $cantidad = round($detalle['cantidad'], 4);
                $precio_unitario = round($detalle['precio_unitario'], 4);
                $subtotal = round($cantidad * $precio_unitario, 4);

                // Descuento calculado
                $descuento = round($subtotal * ($porcentaje_desc / 100), 4);
                $base = $subtotal - $descuento;

                // Impuesto calculado
                $impuesto = round($base * ($porcentaje_impuesto / 100), 4);

                // Total por línea
                $total = round($base + $impuesto, 4);

                // Guardar detalle de compra
                $detalleCompra = ErpPurchaseDetail::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $producto->id,
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio_unitario,
                    'porcentaje_desc' => $porcentaje_desc,
                    'porcentaje_impuesto' => $porcentaje_impuesto,
                    'subtotal' => $subtotal,
                    'descuento' => $descuento,
                    'impuesto' => $impuesto,
                    'total' => $total,
                ]);


                // Totales globales
                $subtotalGeneral += $subtotal;
                $descuentoGeneral += $descuento;
                $impuestoGeneral += $impuesto;
                $totalGeneral += $total;

                // Ajustar inventario (entrada por compra)
                InventoryService::adjustStock(
                    $producto->id,
                    $cantidad,
                    'compra',
                    // 'App\\Models\\ErpPurchase',
                    ErpPurchase::class,
                    $purchase->id,
                    $purchase->user_id
                );

                // (Opcional) actualizar precio de compra del producto
                $producto->purchase_price = $precio_unitario;
                $producto->save();
            }

            // Actualizar totales de la compra
            $purchase->update([
                'subtotal' => round($subtotalGeneral, 2),
                'descuento' => round($descuentoGeneral, 2),
                'impuesto' => round($impuestoGeneral, 2),
                'total' => round($totalGeneral, 2),
            ]);

            DB::commit();

            // $purchase->load('supplier', 'detalles.product');

            $purchase = ErpPurchase::with('supplier', 'detalles.product')
                ->withSum('detalles as nro_productos', 'cantidad')
                ->find($purchase->id);

            return response()->json([
                'success' => true,
                'message' => 'Compra registrada con éxito.',
                'data' => $purchase
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al registrar la compra. ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $purchase = ErpPurchase::with('supplier', 'detalles.product')
            ->withSum('detalles as nro_productos', 'cantidad')
            ->find($id);

        if (!$purchase) {
            return response()->json([
                'success' => false,
                'message' => 'Compra no encontrada.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $purchase
        ], 200);
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $purchase = ErpPurchase::noEliminados()     
                ->where('id', $id)
                ->with('detalles')
                ->first();

            if (!$purchase) {
                throw new \Exception("Compra no encontrada.");
            }

            foreach ($purchase->detalles as $detalle) {
                // Revertir el stock
                InventoryService::adjustStock(
                    $detalle->product_id,
                    $detalle->cantidad,
                    'ajuste_negativo',
                    ErpPurchase::class,
                    $purchase->id,
                    $purchase->user_id,
                    'Reversión por eliminación de compra'
                );
            }

            $purchase->update(['eliminado' => true]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Compra eliminada y stock revertido correctamente.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
