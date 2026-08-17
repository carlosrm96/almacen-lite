<?php

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Listeners\SeedCurrenciesOnCompanyRegistered;
use App\Modules\Catalog\Models\Currency;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Policies\CurrencyPolicy;
use App\Modules\Catalog\Policies\ProductPolicy;
use App\Modules\Catalog\Policies\UnitPolicy;
use App\Modules\Tenancy\Events\CompanyRegistered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // «scoped»: una resolución por petición, y no sobrevive entre
        // peticiones ni entre tests. La consulta va protegida porque este
        // binding puede resolverse con la tabla aún sin crear (p. ej. durante
        // `migrate:fresh`) o sin conexión a la base de datos.
        $this->app->scoped(Currency::BASE_BINDING, function (): Currency {
            try {
                if (! Schema::hasTable('currencies')) {
                    return Currency::baseDesdeConfig();
                }

                return Currency::query()->where('es_base', true)->first()
                    ?? Currency::baseDesdeConfig();
            } catch (Throwable) {
                return Currency::baseDesdeConfig();
            }
        });
    }

    public function boot(): void
    {
        Event::listen(CompanyRegistered::class, SeedCurrenciesOnCompanyRegistered::class);

        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Currency::class, CurrencyPolicy::class);
    }
}
