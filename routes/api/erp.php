<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ErpProductController;
use App\Http\Controllers\ErpCategoryController;
use App\Http\Controllers\ErpStockMovementController;
use App\Http\Controllers\ErpSupplierController;
use App\Http\Controllers\ErpPurchaseController;

Route::group(['middleware' => ['jwt.verify']], function() {

    // ============================
    // CATEGORÍAS
    // ============================
    Route::prefix('categories')->group(function () {
        Route::get('/', [ErpCategoryController::class, 'index']);       // Listar categorías
        Route::post('/', [ErpCategoryController::class, 'store']);      // Crear categoría
        Route::get('/{id}', [ErpCategoryController::class, 'show']);    // Ver una categoría
        Route::put('/{id}', [ErpCategoryController::class, 'update']);  // Actualizar
        Route::delete('/{id}', [ErpCategoryController::class, 'destroy']); // Eliminar
    });

    // ============================
    // PRODUCTOS
    // ============================
    Route::prefix('products')->group(function () {
        Route::get('/', [ErpProductController::class, 'index']);       // Listar productos
        Route::post('/', [ErpProductController::class, 'store']);      // Crear producto
        Route::get('/{id}', [ErpProductController::class, 'show']);    // Ver un producto
        Route::put('/{id}', [ErpProductController::class, 'update']);  // Actualizar producto
        Route::delete('/{id}', [ErpProductController::class, 'destroy']); // Eliminar producto
    });

    // ============================
    // MOVIMIENTOS DE INVENTARIO
    // ============================
    Route::prefix('movements')->group(function () {
        Route::get('/', [ErpStockMovementController::class, 'index']); // Listar movimientos
        Route::post('/', [ErpStockMovementController::class, 'store']); // Registrar movimiento
        Route::get('/{id}', [ErpStockMovementController::class, 'show']); // Ver detalle de un movimiento
    });

    // ============================
    // PROVEEDORES
    // ============================
    Route::prefix('suppliers')->group(function () {
        Route::get('/', [ErpSupplierController::class, 'index']);       // Listar proveedores
        Route::post('/', [ErpSupplierController::class, 'store']);      // Crear proveedor
        Route::put('/{id}', [ErpSupplierController::class, 'update']);  // Actualizar proveedor
        Route::delete('/{id}', [ErpSupplierController::class, 'destroy']); // Eliminar proveedor
    });

    // ============================
    // COMPRAS
    // ============================
    Route::prefix('purchases')->group(function () {
        Route::get('/', [ErpPurchaseController::class, 'index']);       // Listar compras
        Route::post('/', [ErpPurchaseController::class, 'store']);      // Crear compra
        Route::get('/{id}', [ErpPurchaseController::class, 'show']);    // Ver una compra
        Route::delete('/{id}', [ErpPurchaseController::class, 'destroy']); // Eliminar compra
    });

});