<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Doctoralia extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'doctoralia';

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
        'status',
        'count_vistas',
        'profesion',
        'nombre',
        'cedula',
        'telefono',
        'direccion',
        'lat',
        'lng',
        'costo_asesoria',
        'imagen', //logo del despacho
        'ccf',
        'foto',
        'flag_eliminado',
    ];

    //ccf = pdf de constacia de situacion fiscal

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
        'status' => 'integer',
        'count_vistas' => 'double',
        'costo_asesoria' => 'double',
        'flag_eliminado' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function galeria()
    {
        return $this->hasMany(Galeria::class, 'doctor_id');
    }

    public function especialidades()
    {
        return $this->hasMany(Especialidad::class, 'doctor_id');
    }

    public function servicios()
    {
        return $this->hasMany(Servicio::class, 'doctor_id');
    }

    public function certificaciones()
    {
        return $this->hasMany(Certificado::class, 'doctor_id');
    }

    public function opiniones()
    {
        return $this->hasMany(Opinion::class, 'doctor_id');
    }

}
