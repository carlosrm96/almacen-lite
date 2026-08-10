<?php

namespace Tests\Feature\Metrics;

use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_valor_del_inventario_se_calcula_a_coste_y_a_venta_por_almacen(): void
    {
        $this->actingAsRole('admin');
        $norte = Warehouse::factory()->create(['nombre' => 'Norte']);
        $sur = Warehouse::factory()->create(['nombre' => 'Sur']);
        $product = Product::factory()->create(['precio_compra' => 2.00, 'precio_venta' => 5.00]);

        Stock::factory()->for($product)->for($norte)->create(['cantidad' => 10]);
        Stock::factory()->for($product)->for($sur)->create(['cantidad' => 4]);

        $data = $this->getJson('/v1/metrics/inventory')->assertOk()->json('data');

        $porAlmacen = collect($data['valor_inventario'])->keyBy('warehouse_id');
        $this->assertSame('20.00', $porAlmacen[$norte->id]['a_coste']);
        $this->assertSame('50.00', $porAlmacen[$norte->id]['a_venta']);
        $this->assertSame('8.00', $porAlmacen[$sur->id]['a_coste']);
        $this->assertSame('28.00', $data['total_a_coste']);
        $this->assertSame('70.00', $data['total_a_venta']);
    }

    public function test_se_puede_filtrar_por_almacen(): void
    {
        $this->actingAsRole('admin');
        $norte = Warehouse::factory()->create();
        $sur = Warehouse::factory()->create();
        $product = Product::factory()->create(['precio_compra' => 2.00, 'precio_venta' => 5.00]);
        Stock::factory()->for($product)->for($norte)->create(['cantidad' => 10]);
        Stock::factory()->for($product)->for($sur)->create(['cantidad' => 4]);

        $data = $this->getJson("/v1/metrics/inventory?warehouse_id={$norte->id}")->assertOk()->json('data');

        $this->assertCount(1, $data['valor_inventario']);
        $this->assertSame('20.00', $data['total_a_coste']);
    }

    public function test_el_stock_bajo_usa_el_minimo_de_cada_fila(): void
    {
        $this->actingAsRole('admin');
        $almacen = Warehouse::factory()->create();
        $bajo = Product::factory()->create(['nombre' => 'Agua']);
        $sobrado = Product::factory()->create(['nombre' => 'Vino']);

        Stock::factory()->for($bajo)->for($almacen)->create(['cantidad' => 3, 'minimo' => 10]);
        Stock::factory()->for($sobrado)->for($almacen)->create(['cantidad' => 80, 'minimo' => 10]);

        $data = $this->getJson('/v1/metrics/inventory')->assertOk()->json('data');

        $this->assertCount(1, $data['stock_bajo']);
        $this->assertSame('Agua', $data['stock_bajo'][0]['nombre']);
        $this->assertSame('3.000', $data['stock_bajo'][0]['cantidad']);
    }

    public function test_el_stock_bajo_incluye_la_igualdad_con_el_minimo(): void
    {
        $this->actingAsRole('admin');
        $almacen = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Stock::factory()->for($product)->for($almacen)->create(['cantidad' => 10, 'minimo' => 10]);

        $this->getJson('/v1/metrics/inventory')->assertOk()->assertJsonCount(1, 'data.stock_bajo');
    }

    public function test_el_umbral_del_parametro_sustituye_al_minimo_de_cada_fila(): void
    {
        $this->actingAsRole('admin');
        $almacen = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Stock::factory()->for($product)->for($almacen)->create(['cantidad' => 30, 'minimo' => 0]);

        $this->getJson('/v1/metrics/inventory')->assertOk()->assertJsonCount(0, 'data.stock_bajo');
        $this->getJson('/v1/metrics/inventory?umbral=50')->assertOk()->assertJsonCount(1, 'data.stock_bajo');
    }

    public function test_el_stock_de_productos_eliminados_no_cuenta(): void
    {
        $this->actingAsRole('admin');
        $almacen = Warehouse::factory()->create();
        $product = Product::factory()->create(['precio_compra' => 2.00, 'precio_venta' => 5.00]);
        Stock::factory()->for($product)->for($almacen)->create(['cantidad' => 10]);
        $product->delete();

        $data = $this->getJson('/v1/metrics/inventory')->assertOk()->json('data');

        $this->assertSame('0.00', $data['total_a_coste']);
        $this->assertCount(0, $data['stock_bajo']);
    }

    public function test_el_vendedor_no_puede_ver_las_metricas_de_inventario(): void
    {
        $almacen = Warehouse::factory()->create();
        $this->actingAsRole('vendedor', ['warehouse_id' => $almacen->id]);

        $this->getJson('/v1/metrics/inventory')->assertForbidden();
    }
}
