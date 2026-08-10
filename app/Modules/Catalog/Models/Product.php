<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Warehouses\Models\Stock;
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

    /** @return HasMany<Stock, $this> */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function cantidadEn(int $warehouseId): float
    {
        return (float) ($this->stocks()->where('warehouse_id', $warehouseId)->value('cantidad') ?? 0);
    }

    /**
     * Convierte una cantidad expresada en `$unit` a la unidad base del producto.
     *
     * Si la relación `units` ya está cargada se busca ahí primero (sin
     * consulta adicional: es el camino habitual en un bucle de venta sobre
     * productos cargados con `Product::with('units')`). Solo si esa
     * búsqueda no encuentra la unidad se repite la consulta directamente
     * contra la base de datos, por si la caché quedó obsoleta (p. ej. la
     * unidad se asignó al producto después de cargarlo); así se evita
     * lanzar una excepción falsa sin añadir una consulta al caso normal.
     *
     * @throws RuntimeException si la unidad no está asignada al producto
     */
    public function toBase(float $cantidad, Unit $unit): float
    {
        $asignada = $this->relationLoaded('units')
            ? $this->units->firstWhere('unit_id', $unit->id)
            : null;

        if ($asignada === null) {
            $asignada = $this->units()->where('unit_id', $unit->id)->first();
        }

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
