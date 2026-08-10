<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductUnitManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_admin_asigna_una_unidad_adicional_al_producto(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();
        $caja = Unit::factory()->create(['nombre' => 'caja', 'factor' => 24]);

        $this->postJson("/v1/products/{$product->id}/units", ['unit_id' => $caja->id])
            ->assertCreated()
            ->assertJsonPath('data.unit_id', $caja->id)
            ->assertJsonPath('data.is_base', false);

        $this->assertDatabaseHas('product_units', [
            'product_id' => $product->id, 'unit_id' => $caja->id, 'is_base' => false,
        ]);
    }

    public function test_no_se_puede_asignar_dos_veces_la_misma_unidad(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();
        $caja = Unit::factory()->create(['factor' => 24]);

        $this->postJson("/v1/products/{$product->id}/units", ['unit_id' => $caja->id])->assertCreated();
        $this->postJson("/v1/products/{$product->id}/units", ['unit_id' => $caja->id])
            ->assertStatus(422)->assertJsonValidationErrors('unit_id');
    }

    public function test_no_se_puede_desasignar_la_unidad_base(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();
        $baseUnitId = $product->baseProductUnit()->unit_id;

        $this->deleteJson("/v1/products/{$product->id}/units/{$baseUnitId}")
            ->assertStatus(422);

        $this->assertDatabaseHas('product_units', ['product_id' => $product->id, 'unit_id' => $baseUnitId]);
    }

    public function test_el_admin_puede_desasignar_una_unidad_no_base(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();
        $caja = Unit::factory()->create(['factor' => 24]);
        $product->units()->create(['unit_id' => $caja->id, 'is_base' => false]);

        $this->deleteJson("/v1/products/{$product->id}/units/{$caja->id}")->assertNoContent();

        $this->assertDatabaseMissing('product_units', ['product_id' => $product->id, 'unit_id' => $caja->id]);
    }

    public function test_no_se_puede_borrar_una_unidad_asignada_a_un_producto(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();
        $caja = Unit::factory()->create(['factor' => 24]);
        $product->units()->create(['unit_id' => $caja->id, 'is_base' => false]);

        $this->deleteJson("/v1/units/{$caja->id}")->assertStatus(422);
        $this->assertDatabaseHas('units', ['id' => $caja->id]);
    }

    public function test_el_producto_convierte_a_unidad_base(): void
    {
        $product = Product::factory()->create();
        $caja = Unit::factory()->create(['factor' => 24]);
        $product->units()->create(['unit_id' => $caja->id, 'is_base' => false]);

        $this->assertEqualsWithDelta(48.0, $product->fresh()->load('units')->toBase(2, $caja), 0.001);
    }

    public function test_to_base_no_falla_por_una_cache_de_unidades_obsoleta(): void
    {
        $product = Product::factory()->create();
        $product->load('units'); // caché poblada solo con la unidad base

        $caja = Unit::factory()->create(['factor' => 24]);
        $product->units()->create(['unit_id' => $caja->id, 'is_base' => false]);

        // $product->units sigue en caché sin $caja, pero toBase() no debe
        // lanzar: debe repetir la consulta al no encontrarla en memoria.
        $this->assertEqualsWithDelta(48.0, $product->toBase(2, $caja), 0.001);
    }

    public function test_convertir_con_una_unidad_no_asignada_lanza_excepcion(): void
    {
        $product = Product::factory()->create();
        $ajena = Unit::factory()->create(['factor' => 24]);

        $this->expectException(\RuntimeException::class);
        $product->load('units')->toBase(2, $ajena);
    }

    public function test_el_vendedor_no_puede_asignar_unidades(): void
    {
        $product = Product::factory()->create();
        $caja = Unit::factory()->create(['factor' => 24]);
        $this->actingAsRole('vendedor', ['warehouse_id' => Warehouse::factory()->create()->id]);

        $this->postJson("/v1/products/{$product->id}/units", ['unit_id' => $caja->id])->assertForbidden();
    }
}
