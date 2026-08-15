<?php

use App\Modules\Access\Http\Controllers\AuthController;
use App\Modules\Access\Http\Controllers\RegisterController;
use App\Modules\Access\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Las dos únicas rutas públicas de la API, y las dos que se prestan a fuerza
// bruta: van con un limitador más estrecho que el global.
Route::middleware('throttle:auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [RegisterController::class, 'store']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    Route::apiResource('users', UserController::class);
});
