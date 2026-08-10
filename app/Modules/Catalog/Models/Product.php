<?php

namespace App\Modules\Catalog\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['nombre', 'precio_compra', 'precio_venta'];

    protected function casts(): array
    {
        return ['precio_compra' => 'float', 'precio_venta' => 'float'];
    }

    /** @return HasMany<ProductUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function baseProductUnit(): ?ProductUnit
    {
        return $this->units()->where('is_base', true)->first();
    }

    /**
     * Convierte una cantidad expresada en `$unit` a la unidad base del producto.
     *
     * @throws RuntimeException si la unidad no está asignada al producto
     */
    public function toBase(float $cantidad, Unit $unit): float
    {
        $asignada = $this->units->firstWhere('unit_id', $unit->id);

        if ($asignada === null) {
            throw new RuntimeException("La unidad {$unit->nombre} no está asignada al producto {$this->nombre}.");
        }

        return $cantidad * $unit->factor;
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
