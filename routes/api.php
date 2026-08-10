<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Los módulos se van registrando aquí:
    // require __DIR__.'/../app/Modules/Access/routes.php';
});
