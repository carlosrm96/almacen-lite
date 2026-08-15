<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
