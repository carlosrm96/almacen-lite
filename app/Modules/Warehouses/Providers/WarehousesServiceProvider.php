<?php

namespace App\Modules\Warehouses\Providers;

use App\Modules\Warehouses\Models\Warehouse;
use App\Modules\Warehouses\Policies\WarehousePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class WarehousesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Warehouse::class, WarehousePolicy::class);
    }
}
