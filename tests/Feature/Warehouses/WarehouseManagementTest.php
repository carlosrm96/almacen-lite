<?php

namespace Tests\Feature\Warehouses;

use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouses\Models\Stock;
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

    public function test_no_se_puede_borrar_un_almacen_que_solo_aparece_en_una_transferencia(): void
    {
        // Un almacén puede quedar sin stock (todo transferido fuera) y sin
        // usuarios ni ventas, y aun así seguir referenciado por `transfers`
        // como origen o destino (`restrictOnDelete` en ambas columnas). Antes
        // de esta guarda, `$warehouse->delete()` llegaba a ejecutarse y la
        // base de datos respondía con un 500 en vez de un 422 controlado.
        $admin = $this->actingAsRole('admin');
        $origen = Warehouse::factory()->create();
        $destino = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Stock::factory()->for($product)->for($origen)->create(['cantidad' => 100]);

        $this->postJson('/v1/transfers', [
            'product_id' => $product->id,
            'from_warehouse_id' => $origen->id,
            'to_warehouse_id' => $destino->id,
            'cantidad' => 100,
        ])->assertCreated();

        // El origen queda con cantidad 0: `$tieneStock` (cantidad > 0) es falso.
        $this->assertEqualsWithDelta(0.0, $product->cantidadEn($origen->id), 0.001);

        $this->deleteJson("/v1/warehouses/{$origen->id}")->assertStatus(422);
        $this->deleteJson("/v1/warehouses/{$destino->id}")->assertStatus(422);
    }
}
