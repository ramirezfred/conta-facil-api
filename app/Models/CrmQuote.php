<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmQuote extends Model
{
    use HasFactory;

    protected $table = 'crm_quotes';

    protected $fillable = [
        'opportunity_id', 
        'folio', 
        'fecha_emision', 
        'fecha_vencimiento',
        'subtotal', 
        'descuento', 
        'impuesto', 
        'total', 
        'estado', // borrador,enviada,aceptada,rechazada
        'notas', 
        'pdf',
        'processed_at', // fecha cuando se procesó en POS
        'eliminado'
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'processed_at' => 'datetime',
        'eliminado' => 'boolean',
    ];

    /* =======================
       Relaciones
    ======================= */
    public function opportunity()
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }

    public function detalles()
    {
        return $this->hasMany(CrmQuoteDetail::class, 'quote_id');
    }

    /* =======================
       Scopes
    ======================= */
    public function scopeNoEliminados($query)
    {
        return $query->where('eliminado', false);
    }

    // Relación entre cotización y oportunidad (MATRIZ OFICIAL)

    // Acción en Cotización                         | Estado Cotización pasa a:       |	Etapa Oportunidad cambia a:	    | Notas
    // Crear cotización                             | borrador                        |	propuesta	                    | Se está preparando propuesta
    // Enviar cotización                            | enviada                         |	negociación	                    | El cliente ya recibió el ofrecimiento
    // Cliente pide cambios                         | borrador (o enviada otra vez)   |	revisión	                    | Se están ajustando detalles
    // Cliente acepta                               | aceptada                        |	ganada	                        | Venta cerrada
    // Cliente rechaza                              | rechazada                       |	perdida	                        | No se cerró
    // Oportunidad pospuesta                        | —                               |	pospuesta	                    | Cotización queda sin cambios
    // Oportunidad perdida (manual por vendedor)    | perdida                         |	—	                            | Cotización puede quedar como rechazada o borrador
    // Oportunidad ganada (manual sin cotización)   | ganada                          |	—	                            | Caso especial, sin cotización
}
