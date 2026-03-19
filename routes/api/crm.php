<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\CrmContactController;
use App\Http\Controllers\CrmOpportunityController;
use App\Http\Controllers\CrmQuoteController;
use App\Http\Controllers\CrmTaskController;

Route::group(['middleware' => ['jwt.verify']], function() {

    // ============================
    // CONTACTOS
    // ============================
    Route::prefix('contacts')->group(function () {
        Route::get('/', [CrmContactController::class, 'index']);       
        Route::get('/proveedores', [CrmContactController::class, 'indexProveedores']); 
        Route::get('/clientes', [CrmContactController::class, 'indexClientes']);  
        Route::post('/', [CrmContactController::class, 'store']);      
        Route::put('/{id}', [CrmContactController::class, 'update']);  
        Route::delete('/{id}', [CrmContactController::class, 'destroy']); 
    });

    // ============================
    // OPORTUNIDADES
    // ============================
    Route::prefix('opportunities')->group(function () {
        Route::get('/', [CrmOpportunityController::class, 'index']);
        Route::get('/get/estadisticas', [CrmOpportunityController::class, 'estadisticas']);
        Route::post('/', [CrmOpportunityController::class, 'store']);
        Route::get('/{id}', [CrmOpportunityController::class, 'show']);
        Route::put('/{id}', [CrmOpportunityController::class, 'update']);
        Route::delete('/{id}', [CrmOpportunityController::class, 'destroy']);
        Route::put('/{id}/etapa', [CrmOpportunityController::class, 'cambiarEtapa']);
    });

    // ============================
    // COTIZACIONES
    // ============================
    Route::prefix('quotes')->group(function () {
        Route::get('/', [CrmQuoteController::class, 'index']);       
        Route::post('/', [CrmQuoteController::class, 'store']);  
        Route::get('/{id}', [CrmQuoteController::class, 'show']);    
        Route::put('/{id}', [CrmQuoteController::class, 'update']);  
        Route::delete('/{id}', [CrmQuoteController::class, 'destroy']); 
        Route::get('/pdf/{id}', [CrmQuoteController::class, 'comprobantePdf']);
        Route::put('/{id}/estado', [CrmQuoteController::class, 'cambiarEstado']);
        Route::get('/quote_to_pos/{id}', [CrmQuoteController::class, 'cargarCotizacionToPos']);
    });

    Route::prefix('tasks')->group(function () {
    
        Route::get('/opportunity/{id}', [CrmTaskController::class, 'index']);

        Route::post('/', [CrmTaskController::class, 'store']);
        Route::get('/{id}', [CrmTaskController::class, 'show']);
        Route::put('/{id}', [CrmTaskController::class, 'update']);
        Route::delete('/{id}', [CrmTaskController::class, 'destroy']);

        Route::post('/{id}/completar', [CrmTaskController::class, 'completar']);
        Route::post('/{id}/cancelar', [CrmTaskController::class, 'cancelar']);
    });

});