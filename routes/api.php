<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Los módulos se van registrando aquí:
    require __DIR__.'/../app/Modules/Tenancy/routes.php';
    require __DIR__.'/../app/Modules/Access/routes.php';
    require __DIR__.'/../app/Modules/Warehouses/routes.php';
    require __DIR__.'/../app/Modules/Audit/routes.php';
    require __DIR__.'/../app/Modules/Catalog/routes.php';
    require __DIR__.'/../app/Modules/Sales/routes.php';
    require __DIR__.'/../app/Modules/Metrics/routes.php';
});
