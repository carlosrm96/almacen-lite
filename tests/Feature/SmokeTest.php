<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_ruta_v1_inexistente_responde_json_y_no_html(): void
    {
        $this->getJson('/v1/no-existe')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_una_peticion_sin_token_responde_401_aunque_no_pida_json(): void
    {
        // Sin `Accept: application/json`, Laravel trata el
        // `AuthenticationException` como una petición web y redirige a la ruta
        // con nombre `login`, que en una API sin sesiones no existe: el 401 se
        // convierte en 500. Lo evita `shouldRenderJsonWhen` en
        // `bootstrap/app.php`.
        Route::middleware('auth:sanctum')->get('v1/sonda', fn () => 'ok');

        $this->get('/v1/sonda')->assertUnauthorized();
    }

    public function test_el_json_de_error_no_depende_del_prefijo_de_montaje(): void
    {
        // En producción la app cuelga de `/api`, así que la ruta que ve Laravel
        // es `api/v1/...`. La guarda no puede atarse al prefijo de desarrollo o
        // el despliegue real devuelve 500 donde debería devolver 401.
        Route::middleware('auth:sanctum')->get('api/v1/sonda', fn () => 'ok');

        $this->get('/api/v1/sonda')
            ->assertUnauthorized()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_el_healthcheck_responde(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_la_zona_horaria_de_la_aplicacion_es_la_habana(): void
    {
        $this->assertSame('America/Havana', config('app.timezone'));
    }

    public function test_la_moneda_base_por_defecto_es_el_peso_cubano(): void
    {
        $this->assertSame('CUP', config('almacen.moneda_base'));
    }
}
