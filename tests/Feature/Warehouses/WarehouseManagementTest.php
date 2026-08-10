<?php

namespace Tests\Feature\Warehouses;

use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_admin_puede_crear_un_almacen(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/v1/warehouses', ['nombre' => 'Almacén Central'])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Almacén Central')
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('warehouses', ['nombre' => 'Almacén Central', 'activo' => true]);
    }

    public function test_el_nombre_del_almacen_es_obligatorio_y_unico(): void
    {
        $this->actingAsRole('admin');
        Warehouse::factory()->create(['nombre' => 'Central']);

        $this->postJson('/v1/warehouses', [])->assertStatus(422)->assertJsonValidationErrors('nombre');
        $this->postJson('/v1/warehouses', ['nombre' => 'Central'])->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    public function test_el_admin_puede_listar_ver_actualizar_y_borrar_almacenes(): void
    {
        $this->actingAsRole('admin');
        $warehouse = Warehouse::factory()->create(['nombre' => 'Norte']);

        $this->getJson('/v1/warehouses')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/v1/warehouses/{$warehouse->id}")->assertOk()->assertJsonPath('data.nombre', 'Norte');

        $this->putJson("/v1/warehouses/{$warehouse->id}", ['nombre' => 'Sur', 'activo' => false])
            ->assertOk()->assertJsonPath('data.nombre', 'Sur')->assertJsonPath('data.activo', false);

        $this->deleteJson("/v1/warehouses/{$warehouse->id}")->assertNoContent();
        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
    }

    public function test_el_vendedor_no_puede_gestionar_almacenes(): void
    {
        $this->actingAsRole('vendedor');
        $warehouse = Warehouse::factory()->create();

        $this->postJson('/v1/warehouses', ['nombre' => 'X'])->assertForbidden();
        $this->putJson("/v1/warehouses/{$warehouse->id}", ['nombre' => 'X'])->assertForbidden();
        $this->deleteJson("/v1/warehouses/{$warehouse->id}")->assertForbidden();
        $this->getJson('/v1/warehouses')->assertForbidden();
    }

    public function test_sin_token_no_se_accede(): void
    {
        $this->getJson('/v1/warehouses')->assertUnauthorized();
    }
}
