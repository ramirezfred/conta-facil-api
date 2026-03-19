<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosOrder extends Model
{
    use HasFactory;

    protected $table = 'pos_orders';

    protected $fillable = [
        'folio',
        'quote_id', // Si viene de CRM
        'opportunity_id', // Trazabilidad con CRM
        'contacto_id', // referencia a CfdiCliente (cliente o prospecto)
        'user_id', // Vendedor/cajero
        'cash_register_id', // caja donde se registró la venta
        'subtotal',
        'descuento',
        'impuesto',
        'total',
        'total_recibido', 
        'cambio',         
        'status', //'pendiente', 'pagada', 'cancelada'
        'facturada', //Si ya se facturó
        'comprobante_id', //Relación con factura
        'notas',
        'pdf',
        'eliminado',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'impuesto' => 'decimal:2',
        'total' => 'decimal:2',
        'total_recibido' => 'decimal:2', 
        'cambio' => 'decimal:2',         
        'facturada' => 'boolean',
        'eliminado' => 'boolean',
    ];

    // Relaciones
    public function detalles()
    {
        return $this->hasMany(PosOrderDetail::class, 'order_id');
    }

    public function pagos()
    {
        return $this->hasMany(PosOrderPayment::class, 'order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function caja()
    {
        return $this->belongsTo(PosCashRegister::class, 'cash_register_id');
    }

    public function contacto()
    {
        return $this->belongsTo(CfdiCliente::class, 'contacto_id');
    }

    public function comprobante()
    {
        return $this->belongsTo(CfdiComprobante::class, 'comprobante_id');
    }

    public function quote()
    {
        return $this->belongsTo(CrmQuote::class, 'quote_id');
    }

    public function opportunity()
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }

    public function movimientosCaja()
    {
        return $this->hasMany(PosCashRegisterMovement::class, 'order_id');
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('status', 'pendiente');
    }

    public function scopePagadas($query)
    {
        return $query->where('status', 'pagada');
    }

    public function scopeCanceladas($query)
    {
        return $query->where('status', 'cancelada');
    }

    public function scopeNoEliminados($query)
    {
        return $query->where('eliminado', false);
    }

    public function scopeFacturadas($query)
    {
        return $query->where('facturada', true);
    }

    public function scopeSinFacturar($query)
    {
        return $query->where('facturada', false);
    }

    public function scopeDelDia($query, $fecha = null)
    {
        $fecha = $fecha ?? now()->toDateString();
        return $query->whereDate('created_at', $fecha);
    }

    public function scopeDelMes($query, $mes = null, $anio = null)
    {
        $mes = $mes ?? now()->month;
        $anio = $anio ?? now()->year;
        return $query->whereMonth('created_at', $mes)
                     ->whereYear('created_at', $anio);
    }

    public function scopeDesdeCrm($query)
    {
        return $query->whereNotNull('quote_id');
    }

    // Métodos útiles

    public function marcarComoPagada($cajaId = null)
    {
        $this->status = 'pagada';
        if ($cajaId) {
            $this->cash_register_id = $cajaId;
        }
        $this->save();

        return $this;
    }

    public function cancelar($motivo = null)
    {
        $this->status = 'cancelada';
        if ($motivo) {
            $this->notas = ($this->notas ? $this->notas . "\n" : '') . "Cancelada: " . $motivo;
        }
        $this->save();

        return $this;
    }

    public function facturar($comprobanteId)
    {
        $this->facturada = true;
        $this->comprobante_id = $comprobanteId;
        $this->save();

        return $this;
    }

    public function pagosEfectivo()
    {
        return $this->pagos()->where('tipo_pago', 'efectivo');
    }

    public function pagosNoEfectivo()
    {
        return $this->pagos()->where('tipo_pago', '!=', 'efectivo');
    }

    public function agregarPago($tipo, $monto, $userId, $cajaId, $ref = null)
    {
        $tipos = ['efectivo', 'tarjeta_credito', 'tarjeta_debito', 'transferencia', 'otro'];
        if (!in_array($tipo, $tipos)) {
            throw new \Exception("Tipo de pago inválido");
        }

        return $this->pagos()->create([
            'tipo_pago' => $tipo,
            'monto' => $monto,
            'user_id' => $userId,
            'cash_register_id' => $cajaId,
            'referencia' => $ref,
        ]);
    }

 
}
