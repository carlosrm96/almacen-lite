<?php

namespace App\Modules\Tenancy\Providers;

use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Policies\CompanyPolicy;
use App\Modules\Tenancy\Support\CurrentCompany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // «scoped», no «singleton»: la empresa de contexto vive lo que la
        // petición y no debe sobrevivir a la siguiente ni entre tests.
        $this->app->scoped(CurrentCompany::class);
    }

    public function boot(): void
    {
        Gate::policy(Company::class, CompanyPolicy::class);
    }
}
