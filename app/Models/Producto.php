<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

        /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'productos';

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
        'nombre',
        'descripcion',
        'precio',
        'stock',
        'ClaveProdServ',
        'ClaveUnidad',
        'Unidad',
        'flag_eliminado',
    ];

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
        // 'precio' => 'double',
        'stock' => 'integer',
        'flag_eliminado' => 'integer',
    ];

    public function mi_clave_prod_serv()
    {
        return $this->belongsTo(Cfdi40ProductoServicio::class, 'ClaveProdServ');
    }

    public function mi_clave_unidad()
    {
        return $this->belongsTo(Cfdi40ClaveUnidad::class, 'ClaveUnidad');
    }
}
