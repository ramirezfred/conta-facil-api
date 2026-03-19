<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErpPurchaseDetail extends Model
{
    use HasFactory;

    protected $table = 'erp_purchase_details';

    protected $fillable = [
        'purchase_id',
        'product_id',
        'cantidad',
        'precio_unitario',
        'porcentaje_desc',
        'porcentaje_impuesto',
        'descuento',
        'impuesto',
        'subtotal',
        'total'
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'precio_unitario' => 'decimal:4',
        'porcentaje_desc' => 'decimal:2',
        'porcentaje_impuesto' => 'decimal:2',
        'descuento' => 'decimal:4',
        'impuesto' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'total' => 'decimal:4'
    ];

    /* =======================
       Relaciones
    ======================= */
    public function product()
    {
        return $this->belongsTo(ErpProduct::class, 'product_id');
    }

    public function purchase()
    {
        return $this->belongsTo(ErpPurchase::class, 'purchase_id');
    }
}
