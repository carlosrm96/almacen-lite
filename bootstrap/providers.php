<?php

use App\Modules\Access\Providers\AccessServiceProvider;
use App\Modules\Audit\Providers\AuditServiceProvider;
use App\Modules\Catalog\Providers\CatalogServiceProvider;
use App\Modules\Sales\Providers\SalesServiceProvider;
use App\Modules\Tenancy\Providers\TenancyServiceProvider;
use App\Modules\Warehouses\Providers\WarehousesServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    WarehousesServiceProvider::class,
    AccessServiceProvider::class,
    AuditServiceProvider::class,
    CatalogServiceProvider::class,
    SalesServiceProvider::class,
];
