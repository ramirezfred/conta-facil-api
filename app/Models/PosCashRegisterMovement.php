<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosCashRegisterMovement extends Model
{
    use HasFactory;

    protected $table = 'pos_cash_register_movements';

    protected $fillable = [
        'cash_register_id',
        'order_id', //Solo si es devolución de una venta
        'user_id', // Vendedor/cajero
        'tipo', //'ingreso', 'egreso'
        'monto',
        'referencia', // referencia del movimiento
        'notas',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    // Relaciones
    public function caja()
    {
        return $this->belongsTo(PosCashRegister::class, 'cash_register_id');
    }

    public function orden()
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scopes
    public function scopeIngresos($query)
    {
        return $query->where('tipo', 'ingreso');
    }

    public function scopeEgresos($query)
    {
        return $query->where('tipo', 'egreso');
    }

    public function scopeDelDia($query, $fecha = null)
    {
        $fecha = $fecha ?? now()->toDateString();
        return $query->whereDate('created_at', $fecha);
    }

    public function scopeTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    // Métodos útiles
    // Métodos estáticos para registrar movimientos manuales
    public static function registrarIngreso($cajaId, $userId, $monto, $referencia = null, $notas = null)
    {
        return self::create([
            'cash_register_id' => $cajaId,
            'user_id' => $userId,
            'tipo' => 'ingreso',
            'monto' => $monto,
            'referencia' => $referencia ?? 'Ingreso de efectivo',
            'notas' => $notas,
        ]);
    }

    public static function registrarEgreso($cajaId, $userId, $monto, $referencia = null, $notas = null)
    {
        return self::create([
            'cash_register_id' => $cajaId,
            'user_id' => $userId,
            'tipo' => 'egreso',
            'monto' => $monto,
            'referencia' => $referencia ?? 'Retiro de efectivo',
            'notas' => $notas,
        ]);
    }
}
