<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErpProduct extends Model
{
    use HasFactory;

    protected $table = 'erp_products';

    protected $fillable = [
        'name',
        'sku',
        'description',
        'purchase_price',
        'sale_price',
        'impuesto', //IVA 0%, 16%
        'stock',
        'stock_minimum',
        'ClaveProdServ',
        'ClaveUnidad',
        'Unidad',
        'is_service',
        'status',
        'eliminado',
        'category_id',
        'user_id',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'is_service' => 'boolean',
        'status' => 'boolean',
        'eliminado' => 'boolean',
        'stock' => 'decimal:4',
        'stock_minimum' => 'decimal:4',
        'purchase_price' => 'decimal:4',
        'sale_price' => 'decimal:4',
        'impuesto' => 'decimal:4',
        'ClaveProdServ' => 'integer',
        'ClaveUnidad' => 'integer',
    ];

    protected $appends = ['estado'];

    // -------------------------
    // Accessor: estado
    // -------------------------
    public function getEstadoAttribute()
    {
        if ($this->stock == 0) {
            return 'Sin Stock';
        } elseif ($this->stock_minimum !== null && $this->stock <= $this->stock_minimum) {
            return 'Stock Bajo';
        } else {
            return 'Normal';
        }
    }

    // --- Relaciones ---
    public function category()
    {
        return $this->belongsTo(ErpCategory::class, 'category_id');
    }

    public function stockMovements()
    {
        return $this->hasMany(ErpStockMovement::class, 'product_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function mi_clave_prod_serv()
    {
        return $this->belongsTo(Cfdi40ProductoServicio::class, 'ClaveProdServ');
    }

    public function mi_clave_unidad()
    {
        return $this->belongsTo(Cfdi40ClaveUnidad::class, 'ClaveUnidad');
    }

    // --- Scopes ---
    public function scopeActivos($query)
    {
        return $query->where('status', true)->where('eliminado', false);

        // Ejemplo uso
        // Obtener todos los productos activos
        // $productosActivos = ErpProduct::activos()->get();
    }

    public function scopeNoEliminados($query)
    {
        return $query->where('eliminado', false);
    }
}
