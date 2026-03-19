<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use App\Models\User;
use App\Models\ErpProduct;
use App\Models\ErpCategory;
use App\Models\ErpStockMovement;

use App\Services\InventoryService;

use Exception;

use Carbon\Carbon;

class ErpStockMovementController extends Controller
{
    public function index(Request $request)
    {
        $query = ErpStockMovement::with('product');

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->filled('tipo') && $request->tipo !== 'todos') {
            $query->where('tipo', $request->tipo);
        }

        // if ($request->filled('fecha_inicio')) {
        //     $query->whereDate('created_at', '>=', $request->fecha_inicio);
        // }

        // if ($request->filled('fecha_fin')) {
        //     $query->whereDate('created_at', '<=', $request->fecha_fin);
        // }

        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();

            $query->whereBetween('created_at', [$start, $end]);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $coleccion = $query->get();

        // $coleccion = $query->get()->map(function($item) {
        //     // Formateamos la fecha exactamente como está en la BD
        //     $item->created_at = $item->getOriginal('created_at'); 
        //     return $item;
        // });

        return response()->json([
            'success' => true,
            'data'=>$coleccion
        ], 200);
    }

    public function store(Request $request)
    {

        // Primero comprobaremos si estamos recibiendo todos los campos.
        $validator = Validator::make($request->all(),[
            // 'product_id' => 'required|exists:erp_products,id',
            'product_id' => 'required|numeric',
            'cantidad' => 'required|numeric|min:0.0001',
            'tipo' => 'required|in:compra,venta,ajuste_positivo,ajuste_negativo',
            'referencia_type' => 'nullable|string',
            'referencia_id' => 'nullable|integer',
            'motivo' => 'nullable|string',
        ]);
        if ($validator->fails()) { 
            // Se devuelve un array errors con los errores encontrados y cabecera HTTP 422 Unprocessable Entity – [Entidad improcesable] Utilizada para errores de validación.
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data'=>$validator->errors(),
            ],422);
        }

        $product = ErpProduct::noEliminados()
            ->where('id', $request->input('product_id'))
            ->first();
        if (!$product)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'Producto no encontrado.'
            ], 404);
        }

        try {
            $registro = InventoryService::adjustStock(
                $request->input('product_id'),
                $request->input('cantidad'),
                $request->input('tipo'),
                $request->input('referencia_type') ?? null,
                $request->input('referencia_id') ?? null,
                // auth()->id() ?? null,
                $product->user_id ?? null,
                $request->input('motivo') ?? null
            );

            $registro->load('product');

            return response()->json([
                'success' => true,
                'message'=>'Registro creado con éxito.',
                'data'=>$registro
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al ajustar el inventario. '.$e->getMessage(),
                // 'error' => $e->getMessage()
            ], 500);
        }
        
    }

    public function show($id)
    {
        $registro = ErpStockMovement::with('product')->find($id);
        
        if (!$registro)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe el Registro con id '.$id
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'=>$registro
        ], 200);
    }
}
