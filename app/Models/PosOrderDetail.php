<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosOrderDetail extends Model
{
    use HasFactory;

    protected $table = 'pos_order_details';

    protected $fillable = [
        'order_id',
        'product_id', // relación directa a tus productos
        'cantidad',
        'precio_unitario',
        'porcentaje_desc',
        'porcentaje_impuesto', // IVA aplicado (0 o 16)
        'producto_nombre',
        'producto_codigo',
        'subtotal',
        'impuesto',
        'descuento',
        'total',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'precio_unitario' => 'decimal:4',
        'porcentaje_desc' => 'decimal:2',
        'porcentaje_impuesto' => 'decimal:2',
        'subtotal' => 'decimal:4',
        'impuesto' => 'decimal:4',
        'descuento' => 'decimal:4',
        'total' => 'decimal:4',
    ];

    // Relaciones
    public function orden()
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }

    public function producto()
    {
        return $this->belongsTo(ErpProduct::class, 'product_id');
    }
}
