<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\User;

class PosCashRegister extends Model
{
    use HasFactory;

    protected $table = 'pos_cash_registers';

    protected $fillable = [
        'nombre',
        'estado', //'abierta', 'cerrada'
        'fecha_apertura',
        'monto_inicial',
        'notas_apertura',
        'fecha_cierre',
        'monto_final',
        'notas_cierre',
        'monto_esperado', //Monto esperado según ventas
        'diferencia', //Diferencia en arqueo
        'user_id_apertura',
        'user_id_cierre',
    ];

    protected $casts = [
        'fecha_apertura' => 'datetime',
        'fecha_cierre' => 'datetime',
        'monto_inicial' => 'decimal:2',
        'monto_final' => 'decimal:2',
        'monto_esperado' => 'decimal:2',
        'diferencia' => 'decimal:2',
        'user_id_apertura' => 'integer',
        'user_id_cierre' => 'integer',
    ];

    // Relaciones
    public function userApertura()
    {
        return $this->belongsTo(User::class, 'user_id_apertura');
    }

    public function userCierre()
    {
        return $this->belongsTo(User::class, 'user_id_cierre');
    }

    public function movimientos()
    {
        return $this->hasMany(PosCashRegisterMovement::class, 'cash_register_id');
    }

    public function ordenes()
    {
        return $this->hasMany(PosOrder::class, 'cash_register_id');
    }

    public function pagos()
    {
        return $this->hasMany(PosOrderPayment::class, 'cash_register_id');
    }

    // Scopes
    public function scopeAbiertas($query)
    {
        return $query->where('estado', 'abierta');
    }

    public function scopeCerradas($query)
    {
        return $query->where('estado', 'cerrada');
    }

    public function scopeDelUsuario($query, $userId)
    {
        return $query->where('user_id_apertura', $userId);
    }

    // Métodos útiles
    public function abrir($userId, $montoInicial, $notas = null)
    {
        $this->estado = 'abierta';
        $this->fecha_apertura = now();
        $this->monto_inicial = $montoInicial;
        $this->notas_apertura = $notas;
        $this->user_id_apertura = $userId;
        $this->save();

        return $this;
    }

    public function cerrar($userId, $montoFinal, $notas = null)
    {
        $this->estado = 'cerrada';
        $this->fecha_cierre = now();
        $this->monto_final = $montoFinal;
        $this->notas_cierre = $notas;
        $this->user_id_cierre = $userId;
        
        $this->monto_esperado = $this->calcularMontoEsperado();
        $this->diferencia = $montoFinal - $this->monto_esperado;
        
        $this->save();

        return $this;
    }

    /**
     * Calcular efectivo esperado en caja
     */
    public function calcularMontoEsperado()
    {
        // Efectivo de ventas (ajustado por cambio)
        $efectivoVentas = $this->calcularEfectivoDeVentas();
        
        // Movimientos manuales
        $ingresosEfectivo = $this->movimientos()
            ->where('tipo', 'ingreso')
            ->sum('monto');
        
        $egresosEfectivo = $this->movimientos()
            ->where('tipo', 'egreso')
            ->sum('monto');
        
        return $this->monto_inicial + $efectivoVentas + $ingresosEfectivo - $egresosEfectivo;
    }

    /**
     * Calcular efectivo neto de ventas 
     */
    public function calcularEfectivoDeVentas()
    {
        $ordenes = $this->ordenes()->where('status', 'pagada')->with('pagos')->get();
        $efectivoTotal = 0;

        foreach ($ordenes as $orden) {
            $pagos = $orden->pagos;
            $cantidadPagos = $pagos->count();
            
            if ($cantidadPagos === 1) {
                // UN SOLO PAGO
                $pago = $pagos->first();
                
                if ($pago->tipo_pago === 'efectivo') {
                    // Efectivo menos cambio
                    $efectivoTotal += ($pago->monto - $orden->cambio);
                }
                // Si no es efectivo, no suma nada
                
            } else {
                // PAGOS MIXTOS
                $pagoEfectivo = $pagos->where('tipo_pago', 'efectivo')->first();
                $cambio = $orden->cambio;
                
                foreach ($pagos as $pago) {
                    if ($pago->tipo_pago === 'efectivo') {
                        // Efectivo: se le restará el cambio al final
                        $efectivoTotal += $pago->monto;
                    }
                }
                
                // Restar cambio
                if ($pagoEfectivo) {
                    // Si hubo pago en efectivo, restar cambio del efectivo
                    $efectivoTotal -= $cambio;
                } else if ($cambio > 0) {
                    // Si no hubo efectivo pero sí cambio, significa que se dio cambio de otro método
                    // (esto es raro, pero tu lógica lo contempla)
                    // No hacer nada, porque no afecta el efectivo
                }
            }
        }

        return $efectivoTotal;
    }

    // Métodos de totales
    public function totalVentas()
    {
        return $this->ordenes()
            ->where('status', 'pagada')
            ->sum('total');
    }

    public function efectivoActual()
    {
        if ($this->estado === 'cerrada') {
            return $this->monto_final;
        }
        
        return $this->calcularMontoEsperado();
    }

    /**
     * Total por tipo de pago
     */
    public function totalPorTipoPago()
    {
        $ordenes = $this->ordenes()->where('status', 'pagada')->with('pagos')->get();
        $totales = [];

        foreach ($ordenes as $orden) {
            $pagos = $orden->pagos;
            $cantidadPagos = $pagos->count();
            
            if ($cantidadPagos === 1) {
                // UN SOLO PAGO
                $pago = $pagos->first();
                $tipoPago = $pago->tipo_pago;
                
                // Total = monto - cambio
                $total = $pago->monto - $orden->cambio;
                
                if (!isset($totales[$tipoPago])) {
                    $totales[$tipoPago] = 0;
                }
                $totales[$tipoPago] += $total;
                
            } else {
                // PAGOS MIXTOS
                $pagoEfectivo = $pagos->where('tipo_pago', 'efectivo')->first();
                $cambio = $orden->cambio;
                
                // Sumar cada tipo de pago
                foreach ($pagos as $pago) {
                    $tipoPago = $pago->tipo_pago;
                    
                    if (!isset($totales[$tipoPago])) {
                        $totales[$tipoPago] = 0;
                    }
                    $totales[$tipoPago] += $pago->monto;
                }
                
                // Ajustar cambio
                if ($pagoEfectivo) {
                    // Restar cambio del efectivo
                    $totales['efectivo'] -= $cambio;
                } else if ($cambio > 0) {
                    // Si no hay efectivo, restar del mayor monto
                    arsort($totales);
                    $mayorTipo = array_key_first($totales);
                    $totales[$mayorTipo] -= $cambio;
                }
            }
        }

        return $totales;
    }

    // Métodos de estado
    public function estaAbierta()
    {
        return $this->estado === 'abierta';
    }

    public function tieneDiferencia()
    {
        return $this->diferencia != 0;
    }

    // Reportes

    /**
     * Reporte detallado
     */
    public function reporteDetallado()
    {
        $ventasPorTipo = $this->totalPorTipoPago();
        
        // Movimientos manuales de efectivo
        $ingresosEfectivo = $this->movimientos()
            ->where('tipo', 'ingreso')
            ->sum('monto');
        
        $egresosEfectivo = $this->movimientos()
            ->where('tipo', 'egreso')
            ->sum('monto');
        
        // Efectivo esperado
        $ventasEfectivo = $ventasPorTipo['efectivo'] ?? 0;
        $efectivoEsperado = $this->monto_inicial + $ventasEfectivo + $ingresosEfectivo - $egresosEfectivo;
        
        return [
            'base_inicial' => $this->monto_inicial,
            
            // Ventas por tipo de pago (ya ajustadas con cambio)
            'ventas_efectivo' => $ventasEfectivo,
            'ventas_tarjeta_debito' => $ventasPorTipo['tarjeta_debito'] ?? 0,
            'ventas_tarjeta_credito' => $ventasPorTipo['tarjeta_credito'] ?? 0,
            'ventas_transferencias' => $ventasPorTipo['transferencia'] ?? 0,
            'ventas_otras' => $ventasPorTipo['otro'] ?? 0,
            
            // Movimientos manuales
            'ingresos_efectivo' => $ingresosEfectivo,
            'egresos_efectivo' => $egresosEfectivo,
            
            // Totales
            'total_ventas' => $this->totalVentas(),
            'efectivo_esperado' => $efectivoEsperado,
            'efectivo_contado' => $this->monto_final ?? 0,
            'diferencia' => ($this->monto_final ?? 0) - $efectivoEsperado,
        ];
    }

    public function detalleMovimientosManuales()
    {
        return [
            'ingresos' => $this->movimientos()
                ->where('tipo', 'ingreso')
                ->select('id', 'monto', 'referencia', 'notas', 'created_at', 'user_id')
                ->with('user:id,nombre')
                ->get(),
            
            'egresos' => $this->movimientos()
                ->where('tipo', 'egreso')
                ->select('id', 'monto', 'referencia', 'notas', 'created_at', 'user_id')
                ->with('user:id,nombre')
                ->get(),
            
            'total_ingresos' => $this->movimientos()->where('tipo', 'ingreso')->sum('monto'),
            'total_egresos' => $this->movimientos()->where('tipo', 'egreso')->sum('monto'),
        ];
    }

    
}
