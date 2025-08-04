<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    // Eloquent asume que cada tabla tiene una clave primaria con una columna llamada id.
    // Si éste no fuera el caso entonces hay que indicar cuál es nuestra clave primaria en la tabla:
    //protected $primaryKey = 'id';

    //public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'rol',
        'status',
        'telefono',
        'email', 
        'password',
        'last_login', 
        'nombre', 
        'flag_aprobado',
        'color_a',
        'color_b',
        'color_c',
        'header',
        'footer',
        'logo',
        'logo_allow_origin',
        'count_facturas',
        'count_timbres',
        'flag_algoritmo_factura',
        'tipo_algoritmo_factura', 
        'pdf_id',
        'pdf_url',  
        'imagen',
        'flag_eliminado',    
    ];

    //1=SuperAdmin, 2=Cliente

    //flag_algoritmo_factura = null sin aplicar flag_algoritmo_factura = 1 aplicado

    //tipo_algoritmo_factura = 1 semanal tipo_algoritmo_factura = 2 mensual 

    //count_timbres contador de timbres disponibles

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'rol' => 'integer',
        'status' => 'integer',
        'edad' => 'integer',
        'flag_aprobado' => 'integer',
        'count_facturas' => 'integer',
        'flag_eliminado' => 'integer',
        'flag_algoritmo_factura' => 'integer',
        'tipo_algoritmo_factura' => 'integer',
    ];

    // Rest omitted for brevity

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    public function empresa()
    {
        return $this->hasOne(CfdiEmpresa::class, 'user_id');
    }

    public function cfdi_empresa()
    {
        return $this->hasOne(CfdiEmpresa::class, 'user_id');
    }

    public function settings()
    {
        return $this->hasOne(UserSetting::class);
    }

    

}
