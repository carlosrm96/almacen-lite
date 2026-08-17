<?php

use App\Modules\Access\Http\Middleware\ScopeToOwnWarehouse;
use App\Modules\Tenancy\Http\Middleware\ForgetCurrentCompany;
use App\Modules\Tenancy\Http\Middleware\ResolveCurrentCompany;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
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

        // Delante de todo, incluida la autenticación: resolver el token pasa
        // por el scope de empresa, así que arrastrar el contexto de la petición
        // anterior dejaría a un token válido en 401.
        $middleware->api(prepend: [ForgetCurrentCompany::class]);

        // Laravel registra por defecto `redirectGuestsTo(fn () => route('login'))`
        // y `Authenticate` lo evalúa en cuanto la petición no pide JSON. Aquí no
        // hay sesiones ni ruta `login` con nombre, así que ese `route()` lanza
        // `RouteNotFoundException` dentro del middleware — antes de que el
        // manejador de excepciones pueda opinar — y convierte cualquier 401 en
        // un 500. Devolver `null` deja que la excepción llegue entera al
        // manejador, que ya sabe responder JSON.
        $middleware->redirectGuestsTo(fn () => null);

        // `tenant` va detrás de `auth:sanctum` en cada grupo de rutas, no como
        // append al grupo global de la API: el grupo global corre antes que el
        // middleware de ruta, y ahí el usuario del token aún no está resuelto.
        $middleware->alias([
            'tenant' => ResolveCurrentCompany::class,
            'scope.warehouse' => ScopeToOwnWarehouse::class,
        ]);

        // Laravel reordena el stack por su lista de prioridad, y `tenant` no
        // estaba en ella: `SubstituteBindings` resolvía `{warehouse}` antes de
        // que hubiera empresa de contexto, así que el binding no pasaba por el
        // scope y devolvía el almacén de otro negocio con un 200. Va detrás de
        // la autenticación (necesita el usuario) y delante del binding.
        $middleware->appendToPriorityList(
            after: AuthenticatesRequests::class,
            append: ResolveCurrentCompany::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // `*/v1/*` además de `v1/*` porque en producción la aplicación cuelga de
        // un prefijo (`/api`), y atar la guarda al prefijo de desarrollo deja al
        // despliegue real devolviendo HTML —o un 500— donde debería ir JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('v1/*', '*/v1/*'),
        );
    })->create();
