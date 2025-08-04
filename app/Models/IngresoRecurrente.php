<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IngresoRecurrente extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'ingresos_recurrentes';

    // Eloquent asume que cada tabla tiene una clave primaria con una columna llamada id.
    // Si éste no fuera el caso entonces hay que indicar cuál es nuestra clave primaria en la tabla:
    //protected $primaryKey = 'id';

    //public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'ingreso_id',
        'status',
        'titulo',
        'frecuencia',
        'hora',
        'fecha',
        'dia_semana',
        'dia_mes',
        'concepto',
        'date_last_run',
        'log_run',
        'registros'
    ];

    /*
        ingreso_id (id del ingreso)
        status 0=inactiva 1=activa
        tiutlo (titulo de la programacion)
        frecuencia 1=una_vez 2=semanal 3=mensual
        hora hh:mm
        fecha aaaa-mm-dd (fecha en la que se debe ejecutar el ingreso para frecuencia=1)
        dia_semana 0=domingo 1=lunes 2=martes 3=miercoles 4=jueves 5=viernes 6=sabado
        dia_mes 1-31
        concepto (descripcion del ingreso)
        date_last_run (fecha de ultima ejecucion)
        log_run (mensaje de ultima ejecucion)
        regitros ( array con ids de registros generados [] )
     */

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['created_at','updated_at'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'user_id' => 'integer',
        'ingreso_id' => 'integer',
        'status' => 'integer',
        'frecuencia' => 'integer',
        'dia_semana' => 'integer',
        'dia_mes' => 'integer',
    ];

    public function ingreso() {
        return $this->belongsTo(Ingreso::class, 'ingreso_id');
    }
}
