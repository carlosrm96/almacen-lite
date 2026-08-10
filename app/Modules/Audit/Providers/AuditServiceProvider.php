<?php

namespace App\Modules\Audit\Providers;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Policies\AuditLogPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
    }
}
