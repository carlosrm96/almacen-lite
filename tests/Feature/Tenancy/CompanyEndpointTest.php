<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET`/`PUT /v1/company`: la empresa de quien llama. No lleva id en la ruta —
 * pedir la de otro no es que dé 403, es que no hay forma de pedirla.
 */
class CompanyEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_admin_ve_su_empresa(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/v1/company')
            ->assertOk()
            ->assertJsonPath('data.id', $this->company->id)
            ->assertJsonPath('data.nombre', $this->company->nombre);
    }

    public function test_el_vendedor_ve_su_empresa_pero_no_la_renombra(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => Warehouse::factory()->create()->id]);

        $this->getJson('/v1/company')->assertOk()->assertJsonPath('data.id', $this->company->id);

        $this->putJson('/v1/company', ['nombre' => 'Mío Ahora'])->assertForbidden();

        $this->assertDatabaseHas('companies', ['id' => $this->company->id, 'nombre' => $this->company->nombre]);
    }

    public function test_el_admin_renombra_su_empresa(): void
    {
        $this->actingAsRole('admin');

        $this->putJson('/v1/company', ['nombre' => 'Bodega Renombrada'])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Bodega Renombrada');

        $this->assertDatabaseHas('companies', ['id' => $this->company->id, 'nombre' => 'Bodega Renombrada']);
    }

    public function test_el_nombre_es_obligatorio_al_renombrar(): void
    {
        $this->actingAsRole('admin');

        $this->putJson('/v1/company', ['nombre' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nombre');
    }

    public function test_desactivar_la_empresa_no_se_puede_pedir_desde_la_api(): void
    {
        // `activo` no está en las reglas: dejar que el admin se desactive a sí
        // mismo lo dejaría fuera de su propia instalación sin vuelta atrás.
        $this->actingAsRole('admin');

        $this->putJson('/v1/company', ['nombre' => 'Sigue Viva', 'activo' => false])->assertOk();

        $this->assertDatabaseHas('companies', ['id' => $this->company->id, 'activo' => true]);
    }

    public function test_sin_autenticar_no_hay_empresa(): void
    {
        $this->getJson('/v1/company')->assertUnauthorized();
    }
}
