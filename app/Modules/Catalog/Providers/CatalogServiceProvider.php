<?php

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Policies\UnitPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Unit::class, UnitPolicy::class);
    }
}
