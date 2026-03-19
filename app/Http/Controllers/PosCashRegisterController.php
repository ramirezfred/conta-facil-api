<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;
use Exception;

use App\Models\User;
use App\Models\PosCashRegister;
use App\Models\PosOrder;
use App\Models\PosOrderDetail;
use App\Models\PosOrderPayment;
use App\Models\PosCashRegisterMovement;
use App\Models\ErpProduct;

use Carbon\Carbon;

date_default_timezone_set('America/Mexico_City');

class PosCashRegisterController extends Controller
{
    /**
     * Listar cajas registradoras
     */
    public function index(Request $request)
    {
        $query = PosCashRegister::with(['userApertura', 'userCierre']);

        // Filtrar por estado
        if ($request->has('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtrar por usuario
        if ($request->has('user_id')) {
            $query->where('user_id_apertura', $request->user_id);
        }

        $cajas = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $cajas
        ]);
    }

    /**
     * Obtener caja actual abierta del usuario
     */
    public function currentOpen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'errors' => $validator->errors(),
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

        $caja = PosCashRegister::abiertas()
            ->where('user_id_apertura', $request->user_id)
            // ->with(['movimientos', 'ordenes', 'pagos'])
            ->first();

        if (!$caja) {
            return response()->json([
                'success' => false,
                'message' => 'No hay caja abierta',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $caja,
            'total_ventas' => $caja->totalVentas(),
            // 'total_efectivo' => $caja->totalEfectivoRecibido(),
            'monto_esperado' => $caja->calcularMontoEsperado()
        ]);
    }

    /**
     * Abrir caja
     */
    public function open(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|numeric',
            'nombre' => 'required|string',
            'monto_inicial' => 'required|numeric|min:0',
            'notas_apertura' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'errors' => $validator->errors(),
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

        // Verificar que no tenga otra caja abierta
        $cajaAbierta = PosCashRegister::abiertas()
            ->where('user_id_apertura', $request->input('user_id'))
            ->first();

        if ($cajaAbierta) {
            return response()->json([
                'success' => false,
                'message' => 'Ya tienes una caja abierta',
                'data' => $cajaAbierta
            ], 400);
        }

        $caja = PosCashRegister::create([
            'nombre' => $request->nombre,
            'estado' => 'abierta',
            'fecha_apertura' => now(),
            'monto_inicial' => $request->monto_inicial,
            'notas_apertura' => $request->notas_apertura,
            'user_id_apertura' => $request->input('user_id')
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Caja abierta exitosamente',
            'data' => $caja
        ], 201);
    }

    /**
     * Cerrar caja
     */
    public function close(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|numeric',
            'monto_final' => 'required|numeric|min:0',
            'notas_cierre' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'errors' => $validator->errors(),
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

        $caja = PosCashRegister::find($id);

        if (!$caja)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe la Caja con id '.$id
            ], 404);
        }

        // Validar que esté abierta
        if ($caja->estado !== 'abierta') {
            return response()->json([
                'success' => false,
                'message' => 'La caja ya está cerrada'
            ], 400);
        }

        // Validar que sea del usuario actual
        if ($caja->user_id_apertura !== $request->input('user_id')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para cerrar esta caja'
            ], 403);
        }

        $caja->cerrar($request->input('user_id'), $request->monto_final, $request->notas_cierre);

        return response()->json([
            'success' => true,
            'message' => 'Caja cerrada exitosamente',
            'data' => $caja->fresh(),
            'arqueo' => [
                'monto_inicial' => $caja->monto_inicial,
                'monto_esperado' => $caja->monto_esperado,
                'monto_final' => $caja->monto_final,
                'diferencia' => $caja->diferencia,
                'tiene_diferencia' => $caja->tieneDiferencia()
            ]
        ]);
    }

    /**
     * Ver detalle de una caja
     */
    public function show($id)
    {
        $caja = PosCashRegister::with([
            'userApertura',
            'userCierre',
            'movimientos.orden',
            'ordenes.detalles',
            'pagos'
        ])->find($id);

        if (!$caja)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe la Caja con id '.$id
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $caja,
            'estadisticas' => [
                'total_ventas' => $caja->totalVentas(),
                // 'total_efectivo' => $caja->totalEfectivoRecibido(),
                'monto_esperado' => $caja->calcularMontoEsperado()
            ]
        ]);
    }

    /**
     * Registrar movimiento de caja (retiro/depósito)
     */
    public function addMovement(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|numeric',
            'tipo' => 'required|in:ingreso,egreso',
            'monto' => 'required|numeric|min:0.01',
            'referencia' => 'nullable|string|max:255',
            'notas' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $caja = PosCashRegister::find($id);

        if (!$caja)
        {
            // Devolvemos error codigo http 404
            return response()->json([
                'success' => false,
                'message'=>'No existe la Caja con id '.$id
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
                'message' => 'No tienes permiso para modificar esta caja'
            ], 403);
        }

        if ($request->monto <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'El monto debe ser mayor a cero.',
            ], 422);
        }

        $montoEsperadoActual = $caja->calcularMontoEsperado();

        // Si es un EGRESO, validar que no exceda el monto esperado
        if ($request->tipo === 'egreso') {
            $montoSolicitado = $request->monto;

            if ($montoSolicitado > $montoEsperadoActual) {
                return response()->json([
                    'success' => false,
                    'message' => 'El monto de retiro (' . number_format($montoSolicitado, 2) . ') excede el saldo de efectivo esperado actual en la caja (' . number_format($montoEsperadoActual, 2) . ').',
                ], 422); // 422 Unprocessable Entity
            }
        }

        $movimiento = PosCashRegisterMovement::create([
            'cash_register_id' => $caja->id,
            'user_id' => $request->input('user_id'),
            'tipo' => $request->tipo,
            'monto' => $request->monto,
            'referencia' => $request->referencia,
            'notas' => $request->notas
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Movimiento registrado',
            'data' => $movimiento,
            'nuevo_monto_esperado' => $caja->calcularMontoEsperado()
        ], 201);
    }

    /**
     * Obtener resumen detallado de la caja
     */
    public function summary($id)
    {
        $caja = PosCashRegister::with([
            'userApertura',
            'userCierre',
            'movimientos.user',
            'ordenes' => function($query) {
                $query->where('status', 'pagada');
            },
            'pagos'
        ])->find($id);

        if (!$caja) {
            return response()->json([
                'success' => false,
                'message' => 'No existe la Caja con id ' . $id
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'caja' => $caja,
                'reporte' => $caja->reporteDetallado(),
                'movimientos_manuales' => $caja->detalleMovimientosManuales(),
                'ventas_por_tipo_pago' => $caja->totalPorTipoPago(),
            ]
        ]);
    }

    /**
     * Listar movimientos de una caja específica
     */
    public function movements(Request $request, $id)
    {
        $caja = PosCashRegister::find($id);

        if (!$caja) {
            return response()->json([
                'success' => false,
                'message' => 'No existe la Caja con id ' . $id
            ], 404);
        }

        $query = $caja->movimientos()->with(['user', 'orden']);

        // Filtrar por tipo
        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        // Filtrar por fecha
        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();
            $query->whereBetween('created_at', [$start, $end]);
        }

        $movimientos = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $movimientos,
            'totales' => [
                'ingresos' => $caja->movimientos()->where('tipo', 'ingreso')->sum('monto'),
                'egresos' => $caja->movimientos()->where('tipo', 'egreso')->sum('monto'),
            ]
        ]);
    }

    /**
     * Obtener todas las ventas de una caja
     */
    public function sales($id)
    {
        $caja = PosCashRegister::find($id);

        if (!$caja) {
            return response()->json([
                'success' => false,
                'message' => 'No existe la Caja con id ' . $id
            ], 404);
        }

        $ordenes = $caja->ordenes()
            ->with(['detalles.producto', 'user', 'contacto', 'pagos'])
            ->where('status', 'pagada')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $ordenes,
            'estadisticas' => [
                'total_ordenes' => $ordenes->count(),
                'total_ventas' => $caja->totalVentas(),
                'ticket_promedio' => $ordenes->count() > 0 ? $caja->totalVentas() / $ordenes->count() : 0,
            ]
        ]);
    }

    /**
     * Obtener efectivo actual en caja (mientras está abierta)
     */
    public function currentBalance($id)
    {
        $caja = PosCashRegister::find($id);

        if (!$caja) {
            return response()->json([
                'success' => false,
                'message' => 'No existe la Caja con id ' . $id
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'caja_id' => $caja->id,
                'nombre' => $caja->nombre,
                'estado' => $caja->estado,
                'monto_inicial' => $caja->monto_inicial,
                'efectivo_actual' => $caja->efectivoActual(),
                'esta_abierta' => $caja->estaAbierta(),
            ]
        ]);
    }

    /**
     * Reporte rápido para dashboard
     */
    public function dashboard(Request $request)
    {
        $userId = $request->user_id;

        // 1. Obtener la caja actual del usuario
        $cajaActual = PosCashRegister::abiertas()
            ->where('user_id_apertura', $userId)
            ->first();

        // Inicializamos las variables de estadísticas para la sesión
        $ventasSesion = 0;
        $ordenesSesion = 0;

        if ($cajaActual) {
            $querySesion = PosOrder::where('status', 'pagada')
            ->where('cash_register_id', $cajaActual->id);
            
            // Calcular estadísticas 
            $ventasSesion = (clone $querySesion)->sum('total');
            $ordenesSesion = (clone $querySesion)->count();
        }
        
        // Si no hay caja, las estadísticas de la sesión se quedan en 0, lo cual es correcto.

        return response()->json([
            'success' => true,
            'data' => [
                'caja_actual' => $cajaActual ? [
                    'id' => $cajaActual->id,
                    'nombre' => $cajaActual->nombre,
                    'fecha_apertura' => $cajaActual->fecha_apertura,
                    'monto_inicial' => $cajaActual->monto_inicial,
                    'efectivo_actual' => $cajaActual->efectivoActual(), 
                ] : null,
                'estadisticas_sesion' => [ 
                    'ventas_totales' => $ventasSesion,
                    'numero_ordenes' => $ordenesSesion,
                    'ticket_promedio' => $ordenesSesion > 0 ? $ventasSesion / $ordenesSesion : 0,
                ]
            ]
        ]);
    }

    /**
    * Historial de cajas cerradas
    */
    public function historial(Request $request)
    {

        $query = PosCashRegister::cerradas()
            ->with([
                'userApertura:id,email',
                'userCierre:id,email',
                'ordenes' => function($q) {
                    $q->where('status', 'pagada')
                    ->with('pagos');
                },
                'movimientos'
            ]);

        if ($request->filled(['fecha_inicio', 'fecha_fin'])) {
            $start = Carbon::parse($request->fecha_inicio)->startOfDay();
            $end   = Carbon::parse($request->fecha_fin)->endOfDay();
            $query->whereBetween('fecha_cierre', [$start, $end]);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id_apertura', $request->user_id);
        }

        $cajas = $query
            ->orderBy('fecha_cierre', 'desc')
            ->get(); 

        return response()->json([
            'success' => true,
            'data' => $cajas->map(function($caja) {
                return [
                    'id' => $caja->id,
                    'nombre' => $caja->nombre,
                    'fecha_apertura' => $caja->fecha_apertura,
                    'fecha_cierre' => $caja->fecha_cierre,
                    'user_apertura' => $caja->userApertura->email,
                    'user_cierre' => $caja->userCierre->email,
                    'reporte' => $caja->reporteDetallado()
                ];
            })
        ]);
    }
}
