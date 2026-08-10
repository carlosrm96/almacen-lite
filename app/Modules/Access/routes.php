<?php

use App\Modules\Access\Http\Controllers\AuthController;
use App\Modules\Access\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);

    // `store`/`update` reciben un FormRequest tipado: el contenedor lo valida
    // al resolver los parámetros del método, antes de que se ejecute su
    // cuerpo (y por tanto antes del `$this->authorize()` del controlador).
    // Sin este middleware, un vendedor sin permiso pero con datos inválidos
    // vería un 422 en vez de un 403. Se repite la comprobación de la Policy
    // aquí, en el pipeline, para que la autorización gane siempre a la
    // validación.
    Route::apiResource('users', UserController::class)
        ->middlewareFor('store', 'can:create,App\Models\User')
        ->middlewareFor('update', 'can:update,user');
});
