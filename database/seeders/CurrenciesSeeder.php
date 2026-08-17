<?php

namespace Database\Seeders;

use App\Modules\Catalog\Actions\SeedCompanyCurrencies;
use App\Modules\Catalog\Listeners\SeedCurrenciesOnCompanyRegistered;
use Illuminate\Database\Seeder;

/**
 * Monedas de la empresa de contexto.
 *
 * Ya no corre desde `DatabaseSeeder`: desde que las monedas son de cada
 * negocio, sembrarlas sin empresa no significa nada. Las siembra
 * {@see SeedCurrenciesOnCompanyRegistered} al
 * registrarse; este seeder queda para `DemoSeeder` y para los tests, que sí
 * fijan una empresa antes de llamarlo.
 */
class CurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        app(SeedCompanyCurrencies::class)->handle();
    }
}
