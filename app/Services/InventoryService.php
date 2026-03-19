<?php

namespace App\Services;

use App\Models\ErpProduct;
use App\Models\ErpStockMovement;
use Illuminate\Support\Facades\DB;
use Exception;

class InventoryService
{
    /**
     * Ajusta stock en una transacción atómica y registra movimiento.
     *
     * @param int    $productId
     * @param float  $cantidad
     * @param string $tipo        // 'compra'|'venta'|'ajuste_positivo'|'ajuste_negativo'
     * @param string $referenciaType
     * @param int|null $referenciaId
     * @param int|null $userId
     * @param string|null $motivo
     *
     * @return ErpStockMovement
     *
     * @throws Exception si stock insuficiente en venta/ajuste_negativo
     */
    public static function adjustStock($productId, $cantidad, $tipo, $referenciaType = null, $referenciaId = null, $userId = null, $motivo = null)
    {
        return DB::transaction(function () use ($productId, $cantidad, $tipo, $referenciaType, $referenciaId, $userId, $motivo) {
            
            // $product = ErpProduct::lockForUpdate()->findOrFail($productId);

            $product = ErpProduct::noEliminados()
                ->where('id', $productId)
                ->lockForUpdate() // aplicar el bloqueo (FOR UPDATE)
                ->first();
            if (!$product)
            {
                throw new Exception("Producto no encontrado: ID {$productId}");
            }

            // Determinar efecto sobre stock
            $delta = 0;
            if (in_array($tipo, ['compra', 'ajuste_positivo'])) {
                $delta = $cantidad; // sumamos
            } elseif (in_array($tipo, ['venta', 'ajuste_negativo'])) {
                $delta = -1 * $cantidad; // restamos
            } else {
                throw new Exception("Tipo de movimiento no válido: {$tipo}");
            }

            // bccomp($num1, $num2, $precision)
            // Devuelve 0 si son iguales
            // Devuelve 1 si $num1 > $num2
            // Devuelve -1 si $num1 < $num2

            // Si vamos a restar, validar stock suficiente
            if ($delta < 0 && bccomp((string)$product->stock, (string)abs($delta), 4) < 0) {
                throw new Exception("Stock insuficiente para el producto: {$product->name}");
            }

            \Log::info('Ajuste de stock', [
                'product_id' => $productId,
                'cantidad' => $cantidad,
                'tipo' => $tipo,
                'stock_antes' => $product->stock,
            ]);

            // Actualizar stock
            $product->stock = bcadd((string)$product->stock, (string)$delta, 4);
            $product->save();

            \Log::info('Stock actualizado', [
                'stock_despues' => $product->stock,
            ]);

            // Registrar movimiento
            return ErpStockMovement::create([
                'product_id' => $productId,
                'cantidad' => $cantidad,
                'tipo' => $tipo,
                'referencia_type' => $referenciaType,
                'referencia_id' => $referenciaId,
                'motivo' => $motivo,
                'user_id' => $userId,
                'stock_resultante' => $product->stock,
            ]);
        });
    }
}
