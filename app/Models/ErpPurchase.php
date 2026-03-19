<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErpPurchase extends Model
{
    use HasFactory;

    protected $table = 'erp_purchases';

    protected $fillable = [
        'supplier_id',
        'fecha_compra',
        'folio',
        'tipo_documento', //Factura Nota Ticket
        'metodo_pago',
        'forma_pago',
        'moneda',
        'tipo_cambio',
        'subtotal',
        'descuento',
        'impuesto',
        'total',
        'notas',
        'eliminado',
        'user_id'
    ];

    protected $casts = [
        'fecha_compra' => 'date',
        'eliminado' => 'boolean',
    ];

    /* =======================
       Relaciones
    ======================= */
    public function supplier()
    {
        return $this->belongsTo(CfdiCliente::class, 'supplier_id');
    }

    public function detalles()
    {
        return $this->hasMany(ErpPurchaseDetail::class, 'purchase_id');
    }

    /* =======================
       Scopes
    ======================= */
    public function scopeNoEliminados($query)
    {
        return $query->where('eliminado', false);
    }
}
