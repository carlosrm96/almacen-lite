<?php

namespace App\Modules\Warehouses\Models;

use App\Modules\Tenancy\Models\Concerns\BelongsToCompany;
use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = ['nombre', 'activo'];

    /**
     * Valor por defecto en memoria, espejo del default de la migración,
     * para que el modelo recién creado ya lleve 'activo' aunque no se envíe.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'activo' => true,
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    protected static function newFactory(): WarehouseFactory
    {
        return WarehouseFactory::new();
    }
}
