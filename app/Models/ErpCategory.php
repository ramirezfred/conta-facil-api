<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErpCategory extends Model
{
    use HasFactory;

    protected $table = 'erp_categories';

    protected $fillable = [
        'name',
        'description',
        'status',
        'eliminado',
        'user_id',
    ];

    protected $hidden = ['created_at','updated_at'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'status' => 'boolean',
        'eliminado' => 'boolean',
    ];

    // --- Relaciones ---
    public function products()
    {
        return $this->hasMany(ErpProduct::class, 'category_id');
    }

    // --- Scopes ---
    public function scopeActivas($query)
    {
        return $query->where('status', true)->where('eliminado', false);
    }

    public function scopeNoEliminados($query)
    {
        return $query->where('eliminado', false);
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
