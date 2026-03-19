<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CfdiCliente extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'cfdi_clientes';

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
        'empresa_id',
        'status',
        'Rfc',
        'Nombre',
        'DomicilioFiscalReceptor',
        // 'ResidenciaFiscal',
        // 'NumRegIdTrib',
        'RegimenFiscalReceptor',
        'UsoCFDI',
        'contacto',
        'Email',
        'telefono',
        'direccion',
        'tipo_entidad', //'cliente', 'proveedor', 'ambos'
        'tipo_cliente', //'prospecto', 'cliente'
        'origen', //'crm', 'pos', 'erp', 'cfdi', 'api'
        'tipo_operacion', //para proveedores
        'eliminado',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['updated_at'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        // 'created_at' => 'datetime:Y-m-d H:i:s',
        // 'updated_at' => 'datetime:Y-m-d H:i:s',
        'user_id' => 'integer',
        'empresa_id' => 'integer',
        'status' => 'boolean',
        'eliminado' => 'boolean',
    ];

    // --- Relaciones ---

    public function empresa()
    {
        return $this->belongsTo(CfdiEmpresa::class, 'empresa_id');
    }

    public function mi_regimen_fiscal()
    {
        return $this->belongsTo(Cfdi40RegimenFiscal::class, 'RegimenFiscalReceptor');
    }

    public function mi_uso_cfdi()
    {
        return $this->belongsTo(Cfdi40UsoCfdi::class, 'UsoCFDI');
    }    

    // Relación con oportunidades (CRM)
    public function oportunidades()
    {
        return $this->hasMany(CrmOportunidad::class, 'cliente_id');
    }

    // public function compras()
    // {
    //     return $this->hasMany(ErpCompra::class, 'proveedor_id');
    // }

    // public function ventas()
    // {
    //     return $this->hasMany(CfdiComprobante::class, 'cliente_id');
    // }

    // --- Scopes ---
    public function scopeActivos($query)
    {
        return $query->where('status', true)->where('eliminado', false);
    }

    public function scopeNoEliminados($query)
    {
        return $query->where('eliminado', false);
    }

    public function scopeClientes($q) {
        return $q->where('tipo_cliente', 'cliente');
    }

    public function scopeProspectos($q) {
        return $q->where('tipo_cliente', 'prospecto');
    }

    public function scopeActivosNoEliminados($query)
    {
        return $query->where('status', true)
                    ->where('eliminado', false);
    }

    // --- Helpers ---
    public static function existeDuplicado($campo, $valor, $user_id, $idExcluir = null)
    {
        $query = self::noEliminados()
            ->where($campo, $valor)
            ->where('user_id', $user_id);

        if ($idExcluir) {
            $query->where('id', '<>', $idExcluir);
        }

        return $query->exists();
    }

}
