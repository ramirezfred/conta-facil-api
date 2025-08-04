<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Carbon\Carbon;

class BotSistema extends Model
{
    use HasFactory;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'bot_sistema';

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
        'key',
        'active',
        'activated_at',
        'pdf_url',
        'file_create_at',
        'file_uri',
        'file_state',
        'cache_name',
        'cache_create_at',
        
    ];


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
    ];

    public static function getActiveKey()
    {
        // Obtener la clave activa
        $activeKey = self::where('active', true)->where('file_state', "ACTIVE")->first();

        if ($activeKey) {
            // Verificar si activated_at es un objeto Carbon, si no, convertirlo
            $activatedAt = Carbon::parse($activeKey->activated_at);

            // Verificar si lleva más de 5 días activa
            $oneWeekAgo = Carbon::now();
            $numDias = $activatedAt->diffInDays($oneWeekAgo) + 1;

            if ($numDias >= 5) {
                // Si lleva más de 5 días activa, desactivarla
                $activeKey->deactivateKey();

                // Activar la siguiente clave, excluyendo la clave recién desactivada
                return self::activateNextKey($activeKey->id);
            }

            return $activeKey->key; // Si sigue siendo válida, retornarla
        }

        // No hay clave activa, activar la primera disponible
        return self::activateNextKey();
    }

    public static function activateNextKey($excludeId = null)
    {
        // Buscar la próxima clave inactiva, excluyendo la que acabamos de desactivar
        $query = self::where('active', false)->where('file_state', "ACTIVE");
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $nextKey = $query->orderBy('activated_at', 'asc')->first();

        if (!$nextKey) {
            // Si no hay claves inactivas, reutilizar la única clave disponible
            $onlyKey = self::where('file_state', "ACTIVE")->first(); // Tomar cualquier clave
            if ($onlyKey) {
                $onlyKey->update(['active' => true, 'activated_at' => Carbon::now()->format('Y-m-d H:i:s')]);
                return $onlyKey->key;
            }

            throw new \Exception("No hay claves disponibles para activar.");
        }

        $nextKey->update(['active' => true, 'activated_at' => Carbon::now()->format('Y-m-d H:i:s')]);
        return $nextKey->key;
    }

    public function deactivateKey()
    {
        $this->update(['active' => false, 'activated_at' => null]);
    }

    

}
