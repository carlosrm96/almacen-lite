<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Moneda en la que puede expresarse el precio de un producto.
 *
 * `tasa` es cuántas unidades de la moneda base vale 1 unidad de esta moneda.
 * La moneda base tiene siempre tasa 1 — igual que la unidad base tiene factor
 * 1 —, y el modelo lo fuerza al guardar para que un error de administración no
 * reinterprete en silencio todos los importes ya guardados.
 */
class Currency extends Model
{
    /** Clave del binding «scoped» donde se memoriza la moneda base. */
    public const BASE_BINDING = 'almacen.moneda.base';

    protected $fillable = ['codigo', 'nombre', 'simbolo', 'tasa', 'es_base', 'activo'];

    protected function casts(): array
    {
        return ['tasa' => 'float', 'es_base' => 'bool', 'activo' => 'bool'];
    }

    protected static function booted(): void
    {
        static::saving(function (Currency $currency): void {
            if ($currency->es_base) {
                $currency->tasa = 1.0;
            }
        });

        // Cambiar o borrar una moneda puede cambiar cuál es la base.
        static::saved(fn () => self::olvidarBase());
        static::deleted(fn () => self::olvidarBase());
    }

    /**
     * La moneda base: la fila con `es_base`.
     *
     * Se memoriza en un binding «scoped» del contenedor (no en una propiedad
     * estática) para que se resuelva una sola vez por petición y no sobreviva
     * entre peticiones ni entre tests.
     *
     * Si aún no hay ninguna fila —base de datos migrada pero sin sembrar—
     * devuelve una instancia sin persistir construida desde
     * `config('almacen.moneda_base')`, para que los Resources puedan responder.
     */
    public static function base(): Currency
    {
        return app()->make(self::BASE_BINDING);
    }

    public static function olvidarBase(): void
    {
        app()->forgetInstance(self::BASE_BINDING);
    }

    /** Instancia de respaldo cuando `currencies` aún no tiene moneda base. */
    public static function baseDesdeConfig(): Currency
    {
        $codigo = (string) config('almacen.moneda_base');

        return new self([
            'codigo' => $codigo,
            'nombre' => $codigo,
            'simbolo' => $codigo,
            'tasa' => 1.0,
            'es_base' => true,
            'activo' => true,
        ]);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
