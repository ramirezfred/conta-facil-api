<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Exception;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

use Mail;
use Session;
use Redirect;
use Swift_SmtpTransport;
use Swift_Mailer;

use App\Models\User;
use App\Models\PosCashRegister;
use App\Models\PosOrder;
use App\Models\PosOrderDetail;
use App\Models\PosOrderPayment;
use App\Models\PosCashRegisterMovement;
use App\Models\ErpProduct;
use App\Models\ErpCategory;
use App\Models\CfdiCliente;
use App\Models\CfdiEmpresa;
use App\Models\CrmQuote;

use Carbon\Carbon;

use App\Services\InventoryService;
use App\Services\UtilitiesService;

date_default_timezone_set('America/Mexico_City');

class PosOrderController extends Controller
{
    /**
     * Listar órdenes
     */
    public function index(Request $request)
    {
        // Validación
        // $request->validate([
        //     'fecha_inicio' => 'nullable|date',
        //     'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
        //     'user_id' => 'nullable|exists:users,id',
        //     'status' => 'nullable|in:pagada,cancelada,pendiente',
        //     'facturada' => 'nullable|boolean',
        //     'tipo_pago' => 'nullable|in:efectivo,tarjeta_debito,tarjeta_credito,transferencia,otro'
        // ]);

        $query = PosOrder::with([
                'contacto:id,Email,Rfc,Nombre',
                'user:id,email',
                'caja:id,nombre'
            ])
            ->withSum('detalles as nro_productos', 'cantidad')
            ->noEliminados();

        // Filtro por status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Por defecto no mostrar pendientes
            $query->where('status', '!=', 'pendiente');
        }

        // Filtro por usuario
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filtro por fechas
        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }

        // Filtro por facturada
        if ($request->has('facturada')) {
            // if ($request->facturada) {
            //     $query->facturadas();
            // } else {
            //     $query->sinFacturar();
            // }
            $query->where('facturada', $request->boolean('facturada'));
        }

        // Filtro por método de pago
        // if ($request->filled('tipo_pago')) {
        //     $query->whereHas('pagos', function($q) use ($request) {
        //         $q->where('tipo_pago', $request->tipo_pago);
        //     });
        // }

        // Filtro por caja
        if ($request->filled('cash_register_id')) {
            $query->where('cash_register_id', $request->cash_register_id);
        }

        $ordenes = $query->orderBy('created_at', 'desc')->get();

        // Calcular totales del período (solo ordenes pagadas)
        $ordenesPagadas = $ordenes->where('status', 'pagada');
        
        $totales = [
            'total_ventas' => $ordenesPagadas->sum('total'),
            'total_ordenes' => $ordenesPagadas->count(),
            'ticket_promedio' => $ordenesPagadas->count() > 0 
                ? $ordenesPagadas->avg('total') 
                : 0,
            'productos_vendidos' => $ordenesPagadas->sum('nro_productos'),
            'ordenes_canceladas' => $ordenes->where('status', 'cancelada')->count(),
            'ordenes_facturadas' => $ordenesPagadas->where('facturada', true)->count(),
            'subtotal' => $ordenesPagadas->sum('subtotal'),
            'descuentos' => $ordenesPagadas->sum('descuento'),
            'impuestos' => $ordenesPagadas->sum('impuesto'),
        ];

        // // Ventas por método de pago
        // $ventasPorMetodo = [];
        // foreach ($ordenesPagadas as $orden) {
        //     foreach ($orden->pagos as $pago) {
        //         if (!isset($ventasPorMetodo[$pago->tipo_pago])) {
        //             $ventasPorMetodo[$pago->tipo_pago] = 0;
        //         }
        //         $ventasPorMetodo[$pago->tipo_pago] += $pago->monto;
        //     }
        // }

        return response()->json([
            'success' => true,
            'totales' => $totales,
            'data' => $ordenes,
            // 'ventas_por_metodo' => $ventasPorMetodo
        ], 200);
    }

    /**
     * Obtener órdenes pendientes (del CRM)
     */
    public function pending()
    {
        $ordenes = PosOrder::pendientes()
            ->desdeCrm()
            ->with(['detalles.producto', 'contacto', 'quote'])
            ->noEliminados()
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data'=>$ordenes
        ], 200);
    }

    /**
     * Crear orden desde CRM (sin pago inmediato - queda pendiente)
     */
    public function storeFromCrm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quote_id' => 'required|exists:crm_quotes,id',
            'opportunity_id' => 'required|exists:crm_opportunities,id',
            'contacto_id' => 'required|exists:cfdi_clientes,id',
            'detalles' => 'required|array|min:1',
            'detalles.*.product_id' => 'required|exists:erp_products,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.porcentaje_desc' => 'nullable|numeric|min:0|max:100',
            'detalles.*.porcentaje_impuesto' => 'nullable|numeric|min:0|max:100',
            'notas' => 'nullable|string',
            'user_id' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data' => $validator->errors()
            ], 422);
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

        DB::beginTransaction();
        try {
            // Generar folio
            $folio = $this->generarFolio($request->input('user_id'));

            // Crear orden pendiente
            $orden = PosOrder::create([
                'folio' => $folio,
                'quote_id' => $request->quote_id,
                'opportunity_id' => $request->opportunity_id,
                'contacto_id' => $request->contacto_id,
                'user_id' => $request->input('user_id'),
                'status' => 'pendiente',
                'notas' => $request->notas
            ]);

            $subtotalGeneral = $descuentoGeneral = $impuestoGeneral = $totalGeneral = 0;

            // Agregar items
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

                // Guardar detalle de orden
                $detalleOrden = PosOrderDetail::create([
                    'order_id' => $orden->id,
                    'product_id' => $producto->id,
                    'producto_nombre' => $producto->name,
                    'producto_codigo' => $producto->sku,
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
            }

            // Actualizar totales de la orden
            $orden->update([
                'subtotal' => round($subtotalGeneral, 2),
                'descuento' => round($descuentoGeneral, 2),
                'impuesto' => round($impuestoGeneral, 2),
                'total' => round($totalGeneral, 2)
            ]);

            // TODO: Actualizar estado de cotización y oportunidad
            // $quote = CrmQuote::find($request->quote_id);
            // $quote->order_id = $orden->id;
            // $quote->estado = 'aceptada';
            // $quote->save();
            //
            // $opportunity = CrmOpportunity::find($request->opportunity_id);
            // $opportunity->order_id = $orden->id;
            // $opportunity->etapa = 'ganada';
            // $opportunity->probabilidad = '100%';
            // $opportunity->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Orden creada desde CRM, pendiente de pago',
                'data' => $orden->load(['detalles.producto', 'user', 'contacto', 'quote', 'opportunity'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al crear orden. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear orden (venta nueva) CON pago inmediato
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'cash_register_id' => 'required|exists:pos_cash_registers,id',
            // 'contacto_id' => 'nullable|exists:cfdi_clientes,id',
            'contacto_id' => 'required|numeric',
            'detalles' => 'required|array|min:1',
            'detalles.*.product_id' => 'required|exists:erp_products,id',
            'detalles.*.cantidad' => 'required|numeric|min:0.01',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.porcentaje_desc' => 'nullable|numeric|min:0|max:100',
            'detalles.*.porcentaje_impuesto' => 'nullable|numeric|min:0|max:100',
            'pagos' => 'required|array|min:1',
            'pagos.*.tipo_pago' => 'required|in:efectivo,tarjeta_debito,tarjeta_credito,transferencia,cheque,otro',
            'pagos.*.monto' => 'required|numeric|min:0',
            'pagos.*.referencia' => 'nullable|string',
            'notas' => 'nullable|string',
            'user_id' => 'required|numeric',    
            'quote_id' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data' => $validator->errors()
            ], 422);
        }

        // $user = User::buscarPorId($request->input('user_id'));
        $user = User::whereNull('flag_eliminado')
            ->where('id', $request->input('user_id'))
            ->with('cfdi_empresa')
            ->first();
        if (!$user)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'Usuario no encontrado.'
            ], 404);
        }

        if ($user->status != 1)
        {
            return response()->json([
                'success' => false,
                'message'=>'Emisor inhabilitado para generar ventas.'
            ], 400);
        }

        if (!$user->cfdi_empresa)
        {
            return response()->json([
                'success' => false,
                'message'=>'Empresa no encontrada.'
            ], 404);
        }

        $empresa = $user->cfdi_empresa;

        // --- Validar datos de Emisor ---
        $camposRequeridosEmisor = [
            'Rfc', 'RazonSocial', 'RegimenFiscal',
            'CP', 'cer', 'key', 'pass'
        ];

        foreach ($camposRequeridosEmisor as $campo) {
            if (empty($empresa->$campo)) {
                $message = 'Para crear una venta, primero debes configurar tus datos de emisor de CFDI.';
                return response()->json([
                    'success' => false,
                    'message'=>$message
                ], 400);
            }
        }

        // Validar cliente/contacto
        if($request->input('contacto_id') === 0){
            $contacto = CfdiCliente::noEliminados()
                ->where('Rfc', 'XAXX010101000')
                ->where('empresa_id', $empresa->id)
                ->first();
            if (!$contacto)
            {
                // Crear contacto genérico
                $contacto = CfdiCliente::create([
                    'empresa_id'=>$empresa->id,
                    'status'=>true,
                    'Rfc'=>"XAXX010101000",
                    'Nombre'=>"PUBLICO EN GENERAL",
                    'DomicilioFiscalReceptor'=>$empresa->CP,
                    'ResidenciaFiscal'=>null,
                    'NumRegIdTrib'=>null,
                    'RegimenFiscalReceptor'=>"616", //Sin obligaciones fiscales
                    'UsoCFDI'=>"24", //Sin efectos fiscales.
                    'Email'=>$user->email,
                    'user_id'=>$user->id,
                    'tipo_entidad'=>'cliente',
                    'tipo_cliente'=>'cliente',
                    'origen'=>'pos',
                ]);
            }
        }else{
            $contacto = CfdiCliente::noEliminados()
                ->where('id', $request->input('contacto_id'))
                ->first();
            if (!$contacto)
            {
                // Devolvemos error codigo http 404
                return response()->json([
                    'success' => false,
                    'message'=>'No existe el Cliente con id '.$request->input('contacto_id')
                ], 404);
            }
        }

        if($request->input('quote_id')){
            $quote = CrmQuote::find($request->input('quote_id'));

            if (!$quote)
            {
                // Devolvemos error codigo http 404
                return response()->json([
                    'success' => false,
                    'message'=>'No existe la cotización con id '.$request->input('quote_id')
                ], 404);
            }

            // impedir procesar dos veces una cotización 
            if ($quote->processed_at) {
                return response()->json([
                    'success' => false,
                    'message' => 'La cotización ya fue procesada en el POS.'
                ], 422);
            }
        }
        
        // Validar que la caja esté abierta
        $caja = PosCashRegister::find($request->cash_register_id);

        if (!$caja)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe la Caja con id '.$request->cash_register_id
            ], 404);
        }

        if (!$caja->estaAbierta()) {
            return response()->json([
                'success' => false,
                'message' => 'La caja está cerrada'
            ], 400);
        }

        // Validar que la caja sea del usuario actual
        if ($caja->user_id_apertura !== $request->input('user_id')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para usar esta caja'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Generar folio
            $folio = $this->generarFolio($request->input('user_id'));

            // Crear orden
            $orden = PosOrder::create([
                'folio' => $folio,
                'contacto_id' => $contacto->id,
                'user_id' => $request->input('user_id'),
                'cash_register_id' => $caja->id,
                'status' => 'pendiente',
                'notas' => $request->notas
            ]);
                
            $subtotalGeneral = $descuentoGeneral = $impuestoGeneral = $totalGeneral = 0;

            // Agregar items
            foreach ($request->detalles as $detalle) {
                $producto = ErpProduct::find($detalle['product_id']);

                if (!$producto) {
                    throw new \Exception("El producto con ID {$detalle['product_id']} no existe.");
                }

                //si no es un servicio
                if(!$producto->is_service){
                    // Validar stock disponible
                    if (isset($producto->stock) && $producto->stock < $detalle['cantidad']) {
                        throw new \Exception("Stock insuficiente para el producto: {$producto->name}");
                    }
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

                // Guardar detalle de orden
                $detalleOrden = PosOrderDetail::create([
                    'order_id' => $orden->id,
                    'product_id' => $producto->id,
                    'producto_nombre' => $producto->name, 
                    'producto_codigo' => $producto->sku ?? null, 
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

                //si no es un servicio
                if(!$producto->is_service){
                    // Ajustar inventario (salida por venta)
                    InventoryService::adjustStock(
                        $producto->id,
                        $cantidad,
                        'venta',
                        // 'App\\Models\\PosOrder',
                        PosOrder::class,
                        $orden->id,
                        $orden->user_id
                    );
                }
            }

            // Actualizar totales de la orden
            $orden->update([
                'subtotal' => round($subtotalGeneral, 2),
                'descuento' => round($descuentoGeneral, 2),
                'impuesto' => round($impuestoGeneral, 2),
                'total' => round($totalGeneral, 2)
            ]);

            // Validar que el monto total de pagos sea suficiente
            $totalPagos = collect($request->pagos)->sum('monto');
            if ($totalPagos < $orden->total) {
                throw new \Exception("El monto de los pagos es insuficiente. Total orden: {$orden->total}, Total pagos: {$totalPagos}");
            }

            // Calcular cambio
            $cambio = $totalPagos - $orden->total;

            $efectivo = collect($request->pagos)
                ->where('tipo_pago', 'efectivo')
                ->sum('monto');

            if ($cambio > $efectivo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cambio no puede ser mayor al efectivo pagado.'
                ], 422);
            }

            // Registrar pagos
            foreach ($request->pagos as $pago) {
                $orden->pagos()->create([
                    'tipo_pago' => $pago['tipo_pago'],
                    'monto' => $pago['monto'],
                    'referencia' => $pago['referencia'] ?? null,
                    'user_id' => $request->input('user_id'),
                    'cash_register_id' => $caja->id
                ]);
            }

            // Actualizar orden con totales
            $orden->update([
                'total_recibido' => $totalPagos,
                'cambio' => $cambio
            ]);

            // Marcar como pagada
            $orden->marcarComoPagada($caja->id);

            // Si viene de una cotización, marcarla como procesada
            if($request->input('quote_id')){

                $quote->processed_at = now();
                $quote->save();

                // Actualizar orden con datos de cotización
                $orden->update([
                    'quote_id' => $request->input('quote_id'),
                    'opportunity_id' => $quote->opportunity_id
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Venta realizada exitosamente',
                'data' => $orden->load(['detalles.producto', 'pagos', 'user', 'contacto', 'caja']),
                'cambio' => number_format(($totalPagos - $orden->total), 2)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la venta. ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver detalle de orden
     */
    public function show($id)
    {
        $orden = PosOrder::with([
            'detalles.producto',
            'pagos',
            'user',
            'caja',
            'contacto',
            'comprobante',
            'opportunity:id,titulo',
            'quote:id,folio'
        ])->find($id);

        if (!$orden) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $orden
        ]);
    }

    /**
     * Procesar pago de orden (para órdenes pendientes del CRM)
     */
    public function processPayment(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'cash_register_id' => 'required|exists:pos_cash_registers,id',
            'pagos' => 'required|array|min:1',
            'pagos.*.tipo_pago' => 'required|in:efectivo,tarjeta_debito,tarjeta_credito,transferencia,cheque,otro',
            'pagos.*.monto' => 'required|numeric|min:0',
            'pagos.*.referencia' => 'nullable|string',
            'user_id' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'data' => $validator->errors()
            ], 422);
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

        $orden = PosOrder::find($id);

        if (!$orden) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada.'
            ], 404);
        }

        // Validar que esté pendiente
        if ($orden->status !== 'pendiente') {
            return response()->json([
                'success' => false,
                'message' => 'La orden no está pendiente de pago'
            ], 400);
        }

        // Validar que la caja esté abierta
        $caja = PosCashRegister::find($request->cash_register_id);

        if (!$caja) {
            return response()->json([
                'success' => false,
                'message' => 'Caja no encontrada.'
            ], 404);
        }

        if (!$caja->estaAbierta()) {
            return response()->json([
                'success' => false,
                'message' => 'La caja está cerrada'
            ], 400);
        }

        // Validar que la caja sea del usuario actual
        if ($caja->user_id_apertura !== $request->input('user_id')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para usar esta caja'
            ], 403);
        }

        DB::beginTransaction();
        try {
            // Validar stock disponible antes de procesar
            foreach ($orden->detalles as $detalle) {
                $producto = $detalle->producto;

                //si no es un servicio
                if(!$producto->is_service){
                    if (isset($producto->stock) && $producto->stock < $detalle->cantidad) {
                        throw new \Exception("Stock insuficiente para el producto: {$producto->name}");
                    }
                }
            }

            // Validar que el monto total de pagos sea suficiente
            $totalPagos = collect($request->pagos)->sum('monto');
            if ($totalPagos < $orden->total) {
                throw new \Exception("El monto de los pagos es insuficiente. Total orden: {$orden->total}, Total pagos: {$totalPagos}");
            }

            // Calcular cambio
            $cambio = $totalPagos - $orden->total;

            $efectivo = collect($request->pagos)
                ->where('tipo_pago', 'efectivo')
                ->sum('monto');

            if ($cambio > $efectivo) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cambio no puede ser mayor al efectivo pagado.'
                ], 422);
            }

            // Registrar pagos
            foreach ($request->pagos as $pago) {
                $orden->pagos()->create([
                    'tipo_pago' => $pago['tipo_pago'],
                    'monto' => $pago['monto'],
                    'referencia' => $pago['referencia'] ?? null,
                    'user_id' => $request->input('user_id'),
                    'cash_register_id' => $caja->id
                ]);
            }

            // Actualizar orden con totales
            $orden->update([
                'total_recibido' => $totalPagos,
                'cambio' => $cambio
            ]);

            // Marcar como pagada y asignar caja
            $orden->marcarComoPagada($caja->id);

            // TODO: Descontar inventario
            foreach ($orden->detalles as $detalle) {
                $producto = $detalle->producto;

                //si no es un servicio
                if(!$producto->is_service){
                    // Ajustar inventario (salida por venta)
                    InventoryService::adjustStock(
                        $producto->id,
                        $detalle->cantidad,
                        'venta',
                        // 'App\\Models\\PosOrder',
                        PosOrder::class,
                        $orden->id,
                        $orden->user_id
                    );
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pago procesado exitosamente',
                'data' => $orden->fresh()->load(['pagos', 'detalles']),
                'cambio' => $totalPagos - $orden->total
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar pago. '. $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancelar orden
     */
    public function cancel(Request $request, $id)
    {
        $orden = PosOrder::find($id);

        if (!$orden) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada.'
            ], 404);
        }

        if ($orden->status === 'cancelada') {
            return response()->json([
                'success' => false,
                'message' => 'La orden ya está cancelada'
            ], 400);
        }

        if ($orden->status === 'pagada') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede cancelar una orden pagada'
            ], 400);
        }

        $orden->cancelar($request->motivo);

        return response()->json([
            'message' => 'Orden cancelada',
            'data' => $orden
        ]);
    }

    /**
     * Generar folio
     */
    private function generarFolio($user_id)
    {
        $contador = PosOrder::where('user_id', $user_id)
            ->count() + 1;

        $folio = 'POS-' . str_pad($user_id, 3, '0', STR_PAD_LEFT)
               . str_pad($contador, 4, '0', STR_PAD_LEFT);

        return $folio;
    }

    /**
     * Reimprimir ticket de una orden
     */
    public function ticket($id)
    {
        $orden = PosOrder::find($id);

        if (!$orden) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada.'
            ], 404);
        }

        if(!$orden->pdf){
            // Generar PDF
            $urlPdf = $this->comprobantePdf($orden->id);
            $orden->pdf = $urlPdf;
            $orden->save();
        }

        return response()->json([
            'success' => true,
            'data' => $orden->pdf
        ]);
    }

    /**
     * Estadísticas de ventas
     */
    public function stats(Request $request)
    {
        $query = PosOrder::where('status', 'pagada');

        // Filtrar por caja
        if ($request->has('cash_register_id')) {
            $query->where('cash_register_id', $request->cash_register_id);
        }

        // Filtrar por fecha
        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        } else {
            // Por defecto, del día actual
            $query->whereDate('created_at', today());
        }

        $ordenes = $query->get();
        $totalVentas = $ordenes->sum('total');
        $cantidadOrdenes = $ordenes->count();

        // Ventas por tipo de pago
        $ventasPorTipoPago = PosOrderPayment::whereIn('order_id', $ordenes->pluck('id'))
            ->selectRaw('tipo_pago, COUNT(*) as cantidad, SUM(monto) as total')
            ->groupBy('tipo_pago')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_ventas' => $totalVentas,
                'cantidad_ordenes' => $cantidadOrdenes,
                'ticket_promedio' => $cantidadOrdenes > 0 ? $totalVentas / $cantidadOrdenes : 0,
                'ventas_por_tipo_pago' => $ventasPorTipoPago,
            ]
        ]);
    }

    /**
     * Productos más vendidos
     */
    public function topProducts(Request $request)
    {
        $query = PosOrderDetail::with('producto')
            ->whereHas('orden', function($q) {
                $q->where('status', 'pagada');
            });

        // Filtrar por fecha
        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();
            
            $query->whereHas('orden', function($q) use ($start, $end) {
                $q->whereBetween('created_at', [$start, $end]);
            });
        }

        $topProductos = $query
            ->selectRaw('product_id, producto_nombre, SUM(cantidad) as total_vendido, SUM(total) as ingresos')
            ->groupBy('product_id', 'producto_nombre')
            ->orderByDesc('total_vendido')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $topProductos
        ]);
    }

    public function comprobantePdf($id)
    {
        set_time_limit(500);

        $orden = PosOrder::with('contacto', 'detalles.producto')
            ->withSum('detalles as nro_productos', 'cantidad')
            ->find($id);

        $usuario = User::find($orden->user_id);
        $rgb = UtilitiesService::hexToRgb('#4285cb');

        $data = [
            'r' => $rgb['r'],
            'g' => $rgb['g'],
            'b' => $rgb['b'],
            'header' => $usuario->header,
            'footer' => $usuario->footer,

            'emisor' => $usuario,
            'data' => $orden,
        ];

        //$pdf = Pdf::loadView('comprobantes.venta', $data);
        // Crea una instancia de Pdf y establece el tamaño de papel en hoja carta
        $pdf = Pdf::loadView('comprobantes.venta', $data)->setPaper('letter');
        $pdfContent = $pdf->output();

        // Genera un nombre de archivo único
        $nombreArchivo = 'pdf_' . uniqid() . '.pdf';

        // Guarda el PDF en la carpeta "public" del directorio raíz
        Storage::disk('public_root')->put('pdfs/comprobantes/'.$nombreArchivo, $pdf->output());

        // Obtiene la URL del archivo guardado
        $url = asset('pdfs/comprobantes/' . $nombreArchivo);

        return $url;
    }

    /**
     * Enviar email de una orden
     */
    public function sendEmail(Request $request, $id)
    {
        $email = $request->input('email');

        // Validar formato del email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'message' => 'El email proporcionado no es válido.'
            ], 400);
        }

        $orden = PosOrder::find($id);

        if (!$orden) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada.'
            ], 404);
        }

        if(!$orden->pdf){
            // Generar PDF
            $urlPdf = $this->comprobantePdf($orden->id);
            $orden->pdf = $urlPdf;
            $orden->save();
        }

        // Verificar que exista el PDF antes de enviar
        if (!$orden->pdf) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo generar el PDF de la factura.'
            ], 500);
        }

        try {
            $this->emailFactura($id,$email); 
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar email. ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email enviado exitosamente.',
        ]);
    }

    public function emailFactura($id, $email)
    {
        $orden = PosOrder::with('contacto', 'detalles.producto')
            ->withSum('detalles as nro_productos', 'cantidad')
            ->find($id);

        $details = [

            'logo' => 'https://apicontafacil.internow.com.mx/images_uploads/logos/logo_base.png',

            'color_a' => '#4285cb',

            'color_b' => '#ffffff',

            'color_c' => '#ffffff',

            'Nombre' => $orden->contacto->Nombre,

            'Rfc' => null,

        ];

        $attachment1 = $orden->pdf;
        $attachment2 = null;

        $asunto = 'Nueva factura '.$orden->folio;

        \Mail::to($email)->send(new \App\Mail\NuevaFacturaEmail($details,$attachment1,$attachment2,$asunto));

        return 1;

    }

}
