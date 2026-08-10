<?php

namespace Tests\Feature\Sales;

use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_vendedor_vende_siempre_en_su_almacen_aunque_pida_otro(): void
    {
        $suyo = Warehouse::factory()->create();
        $ajeno = Warehouse::factory()->create();
        $product = Product::factory()->create(['precio_venta' => 2.00]);
        Stock::factory()->for($product)->for($suyo)->create(['cantidad' => 10]);
        Stock::factory()->for($product)->for($ajeno)->create(['cantidad' => 10]);

        $this->actingAsRole('vendedor', ['warehouse_id' => $suyo->id]);

        $this->postJson('/v1/sales', [
            'warehouse_id' => $ajeno->id,
            'items' => [['product_id' => $product->id, 'cantidad' => 4]],
        ])->assertCreated()->assertJsonPath('data.warehouse_id', $suyo->id);

        $this->assertEqualsWithDelta(6.0, $product->cantidadEn($suyo->id), 0.001);
        $this->assertEqualsWithDelta(10.0, $product->cantidadEn($ajeno->id), 0.001);
    }

    public function test_el_admin_debe_indicar_el_almacen(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $product->id, 'cantidad' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('warehouse_id');
    }

    public function test_el_vendedor_solo_ve_las_ventas_de_su_almacen(): void
    {
        $suyo = Warehouse::factory()->create();
        $ajeno = Warehouse::factory()->create();
        Sale::factory()->for($suyo)->create();
        Sale::factory()->for($ajeno)->create();

        $this->actingAsRole('vendedor', ['warehouse_id' => $suyo->id]);

        $this->getJson('/v1/sales')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.warehouse_id', $suyo->id);
    }

    public function test_el_vendedor_no_puede_ver_una_venta_de_otro_almacen(): void
    {
        $suyo = Warehouse::factory()->create();
        $ajena = Sale::factory()->for(Warehouse::factory()->create())->create();

        $this->actingAsRole('vendedor', ['warehouse_id' => $suyo->id]);

        $this->getJson("/v1/sales/{$ajena->id}")->assertForbidden();
    }

    public function test_el_admin_ve_las_ventas_de_todos_los_almacenes(): void
    {
        Sale::factory()->count(3)->create();
        $this->actingAsRole('admin');

        $this->getJson('/v1/sales')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_la_venta_registra_el_usuario_que_la_genero(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['precio_venta' => 3.00]);
        Stock::factory()->for($product)->for($warehouse)->create(['cantidad' => 10]);

        $vendedor = $this->actingAsRole('vendedor', ['warehouse_id' => $warehouse->id]);

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $product->id, 'cantidad' => 2]],
        ])->assertCreated()->assertJsonPath('data.user_id', $vendedor->id);
    }

    public function test_no_se_puede_borrar_un_almacen_con_ventas(): void
    {
        $warehouse = Warehouse::factory()->create();
        Sale::factory()->for($warehouse)->create();
        $this->actingAsRole('admin');

        $this->deleteJson("/v1/warehouses/{$warehouse->id}")->assertStatus(422);
    }
}
