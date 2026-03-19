<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErpStockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'cantidad',
        'tipo', //'inventario_inicial', 'compra', 'venta', 'ajuste_positivo', 'ajuste_negativo'
        'referencia_type',
        'referencia_id',
        'stock_resultante',
        'motivo',
        'user_id',
    ];

    protected $casts = [
        // 'created_at' => 'datetime:Y-m-d H:i:s',
        // 'created_at' => 'string',
        // 'updated_at' => 'datetime:Y-m-d H:i:s',
        // 'created_at' => 'string',
        'cantidad' => 'decimal:4',
        'stock_resultante' => 'decimal:4',
    ];

    // --- Relaciones ---
    public function product()
    {
        return $this->belongsTo(ErpProduct::class, 'product_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function referencia()
    {
        return $this->morphTo();
    }

    // --- Scopes ---
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }
}
