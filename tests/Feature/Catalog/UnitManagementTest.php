<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Unit;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_admin_puede_crear_una_unidad_con_nombre_y_factor(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/v1/units', ['nombre' => 'caja', 'factor' => 24])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'caja')
            ->assertJsonPath('data.factor', '24.000');

        $this->assertDatabaseHas('units', ['nombre' => 'caja']);
    }

    public function test_el_nombre_es_unico_y_el_factor_debe_ser_positivo(): void
    {
        $this->actingAsRole('admin');
        Unit::factory()->create(['nombre' => 'caja']);

        $this->postJson('/v1/units', ['nombre' => 'caja', 'factor' => 12])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');

        $this->postJson('/v1/units', ['nombre' => 'palé', 'factor' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('factor');

        $this->postJson('/v1/units', ['nombre' => 'palé', 'factor' => -3])
            ->assertStatus(422)->assertJsonValidationErrors('factor');
    }

    public function test_el_admin_puede_listar_actualizar_y_borrar_unidades(): void
    {
        $this->actingAsRole('admin');
        $unit = Unit::factory()->create(['nombre' => 'caja', 'factor' => 24]);

        $this->getJson('/v1/units')->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/v1/units/{$unit->id}", ['factor' => 12])
            ->assertOk()->assertJsonPath('data.factor', '12.000');

        $this->deleteJson("/v1/units/{$unit->id}")->assertNoContent();
        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
    }

    public function test_el_vendedor_no_puede_gestionar_unidades(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => Warehouse::factory()->create()->id]);
        $unit = Unit::factory()->create();

        $this->getJson('/v1/units')->assertForbidden();
        $this->postJson('/v1/units', ['nombre' => 'x', 'factor' => 2])->assertForbidden();
        $this->getJson("/v1/units/{$unit->id}")->assertForbidden();
        $this->putJson("/v1/units/{$unit->id}", ['factor' => 3])->assertForbidden();
        $this->deleteJson("/v1/units/{$unit->id}")->assertForbidden();
    }
}
