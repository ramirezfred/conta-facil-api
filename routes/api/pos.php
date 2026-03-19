<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PosCashRegisterController;
use App\Http\Controllers\PosOrderController;
use App\Http\Controllers\PosProductController;

Route::group(['middleware' => ['jwt.verify']], function() {

    // ============================================
    // CAJAS REGISTRADORAS
    // ============================================
    Route::prefix('cajas')->group(function () {
        // Listar cajas
        Route::get('/', [PosCashRegisterController::class, 'index']);
        
        // Obtener caja actual abierta del usuario
        Route::get('/current', [PosCashRegisterController::class, 'currentOpen']);
        
        // Ver detalle de caja
        Route::get('/{id}', [PosCashRegisterController::class, 'show']);
        
        // Abrir caja
        Route::post('/abrir', [PosCashRegisterController::class, 'open']);
        
        // Cerrar caja
        Route::post('/{id}/cerrar', [PosCashRegisterController::class, 'close']);
        
        // Registrar movimiento (retiro/depósito)
        Route::post('/{id}/movimientos', [PosCashRegisterController::class, 'addMovement']);
    
        Route::get('/{id}/resumen', [PosCashRegisterController::class, 'summary']);
        Route::get('/{id}/movimientos', [PosCashRegisterController::class, 'movements']);
        Route::get('/{id}/ventas', [PosCashRegisterController::class, 'sales']);
        Route::get('/{id}/balance', [PosCashRegisterController::class, 'currentBalance']);
        Route::get('/dashboard/stats', [PosCashRegisterController::class, 'dashboard']);
        Route::get('/historial/cerradas', [PosCashRegisterController::class, 'historial']);
    });

    // ============================================
    // ÓRDENES / VENTAS
    // ============================================
    Route::prefix('ordenes')->group(function () {
        // Listar órdenes
        Route::get('/', [PosOrderController::class, 'index']);
        
        // Órdenes pendientes (del CRM)
        Route::get('/pendientes', [PosOrderController::class, 'pending']);
        
        // Ver detalle de orden
        Route::get('/{id}', [PosOrderController::class, 'show']);
        
        // Crear nueva orden (venta directa en POS con pago inmediato)
        Route::post('/', [PosOrderController::class, 'store']);
        
        // Crear orden desde CRM (sin pago, queda pendiente)
        Route::post('/desde-crm', [PosOrderController::class, 'storeFromCrm']);
        
        // Procesar pago de orden pendiente (del CRM)
        Route::post('/{id}/procesar-pago', [PosOrderController::class, 'processPayment']);
        
        // Cancelar orden
        Route::post('/{id}/cancelar', [PosOrderController::class, 'cancel']);
        
        Route::get('/{id}/ticket', [PosOrderController::class, 'ticket']); 
        Route::get('/estadisticas/ventas', [PosOrderController::class, 'stats']); 
        Route::get('/estadisticas/top-productos', [PosOrderController::class, 'topProducts']); 
        Route::post('/{id}/email', [PosOrderController::class, 'sendEmail']);
    });

    // ============================================
    // PRODUCTOS (para búsqueda en POS)
    // ============================================
    // Route::prefix('productos')->group(function () {
    //     // Buscar productos
    //     Route::get('/buscar', [PosProductController::class, 'search']);
    // });

    // ============================================
    // REPORTES (opcional, para después)
    // ============================================
    // Route::prefix('reportes')->group(function () {
    //     Route::get('/ventas-dia', [PosReportController::class, 'ventasDelDia']);
    //     Route::get('/ventas-mes', [PosReportController::class, 'ventasDelMes']);
    //     Route::get('/arqueo-caja/{id}', [PosReportController::class, 'arqueoCaja']);
    // });

});