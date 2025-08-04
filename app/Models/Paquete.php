<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paquete extends Model
{
    use HasFactory;

        /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'paquetes';

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
        'nombre',
        'descripcion',
        'precio',
        'status',
        'tipo',
        'cantidad',
        'imagen',
        'flag_eliminado'
    ];

    //tipo 1=paquete de timbres 2=paquete de asesorias 3=paquete de servicios

    //cantidad de timbres

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'precio' => 'double',
        'status' => 'integer',
        'tipo' => 'integer',
        'cantidad' => 'integer',
        'flag_eliminado' => 'integer',
    ];
}
