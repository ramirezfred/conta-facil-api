<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrmOpportunity extends Model
{
    use HasFactory;

    protected $table = 'crm_opportunities';

    protected $fillable = [
        'user_id',
        'contacto_id',
        'titulo',
        'descripcion',
        'monto_estimado',
        'fuente_lead',
        'etapa',
        'probabilidad',
        'fecha_cierre_estimada',
        'comentarios',
        'eliminado',
    ];

    //etapa         //'nueva', 'propuesta', 'negociacion', 'revision', 'pospuesta', 'ganada', 'perdida'
    //probabilidad  //'5%',    '40%',       '60%',         '75%',       '25%',      '100%',   '0%'

    //fuente_lead
    // leadSources = [
    //     { value: 'referido', label: 'Referido' },
    //     { value: 'redes', label: 'Redes sociales' },
    //     { value: 'web', label: 'Página web' },
    //     { value: 'llamada', label: 'Llamada telefónica' },
    //     { value: 'email', label: 'Correo electrónico' },
    //     { value: 'otro', label: 'Otro' },
    // ];

    protected $casts = [
        'eliminado' => 'boolean',
        'fecha_cierre_estimada' => 'date',
        'monto_estimado' => 'decimal:2',
    ];

    // === Relaciones ===

    /**
     * Usuario dueño de la oportunidad
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Cliente o prospecto asociado
     */
    public function contacto()
    {
        return $this->belongsTo(CfdiCliente::class, 'contacto_id');
    }

    public function quote()
    {
        return $this->hasOne(CrmQuote::class, 'opportunity_id');
    }

    public function tasks()
    {
        return $this->hasMany(CrmTask::class, 'opportunity_id');
    }

    public function nextTask() {
        return $this->hasOne(CrmTask::class, 'opportunity_id')
            ->pendientes()
            ->orderBy('fecha_programada', 'asc');
    }

    // === Scopes ===

    public function scopeNoEliminados($query)
    {
        return $query->where('eliminado', false);
    }

    /**
     * Filtra por etapa específica
     */
    public function scopeEtapa($query, $etapa)
    {
        return $query->where('etapa', $etapa);
    }

    /**
     * Filtra por fuente de lead (redes, referido, etc.)
     */
    public function scopeFuente($query, $fuente)
    {
        return $query->where('fuente_lead', $fuente);
    }
}
