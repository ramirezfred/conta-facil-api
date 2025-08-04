<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ingreso extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'ingresos';

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
        'tipo_id',
        'total',
        'pdf',
        'factura_id',
        'flag_eliminado'
    ];

    //tipo_id 1=Contable 2=No Contable
    //se facturan solo los contables

    //factura_id = null no facturado  factura_id != null facturado

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'user_id' => 'integer',
        'tipo_id' => 'integer',
        'total' => 'double',
        'factura_id' => 'integer',
        'flag_eliminado' => 'integer',
    ];

    /*public function tipo()
    {
        return $this->belongsTo(CatGasto::class, 'tipo_id');
    }*/

    public function conceptos()
    {
        return $this->hasMany(IngresoConcepto::class, 'ingreso_id');
    }
}
