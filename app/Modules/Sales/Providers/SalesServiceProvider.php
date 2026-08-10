<?php

namespace App\Modules\Sales\Providers;

use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Policies\SalePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class SalesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Sale::class, SalePolicy::class);
    }
}
