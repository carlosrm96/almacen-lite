<?php

use App\Modules\Access\Http\Middleware\ScopeToOwnWarehouse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Sin prefijo `api`: las rutas quedan como `v1/...`.
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();

        // Laravel registra por defecto `redirectGuestsTo(fn () => route('login'))`
        // y `Authenticate` lo evalúa en cuanto la petición no pide JSON. Aquí no
        // hay sesiones ni ruta `login` con nombre, así que ese `route()` lanza
        // `RouteNotFoundException` dentro del middleware — antes de que el
        // manejador de excepciones pueda opinar — y convierte cualquier 401 en
        // un 500. Devolver `null` deja que la excepción llegue entera al
        // manejador, que ya sabe responder JSON.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'scope.warehouse' => ScopeToOwnWarehouse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // `*/v1/*` además de `v1/*` porque en producción la aplicación cuelga de
        // un prefijo (`/api`), y atar la guarda al prefijo de desarrollo deja al
        // despliegue real devolviendo HTML —o un 500— donde debería ir JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('v1/*', '*/v1/*'),
        );
    })->create();
