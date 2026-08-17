<?php

use App\Modules\Access\Http\Controllers\AuthController;
use App\Modules\Access\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// La única ruta pública de este módulo, y de las que más se prestan a fuerza
// bruta: va con un limitador más estrecho que el global. El registro es la
// otra, y vive en Tenancy: lo que crea es una empresa.
Route::middleware('throttle:auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
});

Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::apiResource('users', UserController::class);
});
