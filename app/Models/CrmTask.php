<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CrmTask extends Model
{
    use HasFactory;

    protected $table = 'crm_tasks';

    protected $fillable = [
        'opportunity_id',
        'titulo',
        'descripcion',
        'fecha_programada',
        'fecha_completada',
        'estado', // 'pendiente','completada','cancelada'
        'notas',
    ];

    protected $hidden = ['created_at','updated_at'];

    protected $casts = [
        'fecha_programada' => 'datetime',
        'fecha_completada' => 'datetime',
    ];

    protected $appends = ['estatus_color'];

    // RELACIONES
    public function opportunity()
    {
        return $this->belongsTo(CrmOpportunity::class, 'opportunity_id');
    }

    // SCOPES

    // Solo tareas pendientes (no completada, no cancelada, no vencida manualmente)
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    // Próxima tarea pendiente más cercana
    public function scopeProxima($query)
    {
        return $query->pendientes()->orderBy('fecha_programada', 'asc');
    }

    // Todas las tareas de una oportunidad
    public function scopeDeOpportunity($query, $id)
    {
        return $query->where('opportunity_id', $id);
    }

    // Tareas vencidas (pero aún con estado "pendiente")
    public function scopeVencidas($query)
    {
        return $query
            ->where('estado', 'pendiente')
            ->where('fecha_programada', '<', now());
    }

    // ATRIBUTOS PERSONALIZADOS
    public function getEstatusColorAttribute()
    {
        if ($this->estado === 'pendiente') {
            if ($this->fecha_programada->isToday()) return 'yellow';
            if ($this->fecha_programada->isFuture()) return 'green';
            if ($this->fecha_programada->isPast()) return 'red';
        }

        return 'gray';
    }
}
