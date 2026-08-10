<?php

use App\Modules\Access\Providers\AccessServiceProvider;
use App\Modules\Audit\Providers\AuditServiceProvider;
use App\Modules\Warehouses\Providers\WarehousesServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    WarehousesServiceProvider::class,
    AccessServiceProvider::class,
    AuditServiceProvider::class,
];
