<?php

namespace Database\Seeders;

use App\Modules\Catalog\Models\Currency;
use Illuminate\Database\Seeder;

/**
 * Monedas con las que opera el negocio. Idempotente: no pisa una moneda ya
 * existente, para no deshacer una tasa ajustada a mano.
 *
 * Ver docs/superpowers/specs/2026-08-14-monedas-y-zona-horaria-cuba-design.md
 */
class CurrenciesSeeder extends Seeder
{
    /** @var array<string, array{nombre: string, simbolo: string}> */
    private const MONEDAS = [
        'CUP' => ['nombre' => 'Peso cubano', 'simbolo' => '$'],
        'USD' => ['nombre' => 'Dólar estadounidense', 'simbolo' => 'US$'],
    ];

    public function run(): void
    {
        $base = (string) config('almacen.moneda_base');

        foreach (self::MONEDAS as $codigo => $datos) {
            $esBase = $codigo === $base;

            Currency::firstOrCreate(['codigo' => $codigo], [
                ...$datos,
                // La moneda base tiene tasa 1 por definición; el modelo lo
                // fuerza igualmente al guardar.
                'tasa' => $esBase ? 1.0 : (float) (config("almacen.tasas.{$codigo}") ?? 1.0),
                'es_base' => $esBase,
                'activo' => true,
            ]);
        }

        // El binding «scoped» pudo resolverse antes de que existiera la fila.
        Currency::olvidarBase();
    }
}
