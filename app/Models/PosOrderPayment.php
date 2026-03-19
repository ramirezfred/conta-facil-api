<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosOrderPayment extends Model
{
    use HasFactory;

    protected $table = 'pos_order_payments';

    protected $fillable = [
        'order_id',
        'cash_register_id', // caja donde se registró la venta
        'tipo_pago', //'efectivo', 'tarjeta_credito', 'tarjeta_debito, 'transferencia', 'otro'
        'monto',
        'referencia', // número de autorización, folio, etc.
        'user_id', //Vendedor/cajero
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    // Relaciones
    public function orden()
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }

    public function caja()
    {
        return $this->belongsTo(PosCashRegister::class, 'cash_register_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scopes
    public function scopeEfectivo($query)
    {
        return $query->where('tipo_pago', 'efectivo');
    }

    public function scopeTarjeta($query)
    {
        return $query->whereIn('tipo_pago', ['tarjeta_credito', 'tarjeta_debito']);
    }

    public function scopeTransferencia($query)
    {
        return $query->where('tipo_pago', 'transferencia');
    }

    public function scopeDelDia($query, $fecha = null)
    {
        $fecha = $fecha ?? now()->toDateString();
        return $query->whereDate('created_at', $fecha);
    }

    public function scopeEntreFechas($query, $desde, $hasta)
    {
        return $query->whereBetween('created_at', [$desde, $hasta]);
    }

    public function esEfectivo()
    {
        return $this->tipo_pago === 'efectivo';
    }

}
