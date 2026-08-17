<?php

use App\Modules\Tenancy\Http\Controllers\CompanyController;
use App\Modules\Tenancy\Http\Controllers\RegisterController;
use Illuminate\Support\Facades\Route;

// Pública y sin contexto de empresa: es la que la crea. Con el limitador
// estrecho de las rutas públicas, que se prestan a fuerza bruta.
Route::middleware('throttle:auth')->group(function (): void {
    Route::post('register', [RegisterController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::get('company', [CompanyController::class, 'show']);
    Route::put('company', [CompanyController::class, 'update']);
});
