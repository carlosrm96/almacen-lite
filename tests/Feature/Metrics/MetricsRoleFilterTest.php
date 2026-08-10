<?php

namespace Tests\Feature\Metrics;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MetricsRoleFilterTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $almacen;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-11 12:00:00');
        $this->almacen = Warehouse::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function venta(User $usuario, Product $product, float $cantidadBase, string $cuando): void
    {
        $subtotal = round($product->precio_venta * $cantidadBase, 2);

        $sale = Sale::factory()->for($this->almacen)->for($usuario)->create([
            'total' => $subtotal, 'created_at' => $cuando,
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'unit_id' => $product->baseProductUnit()->unit_id,
            'cantidad' => $cantidadBase,
            'cantidad_base' => $cantidadBase,
            'precio_venta_unit' => $product->precio_venta,
            'precio_compra_unit' => $product->precio_compra,
            'subtotal' => $subtotal,
        ]);
    }

    public function test_el_admin_recibe_el_informe_completo(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/v1/metrics/sales?period=weekly')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'ingresos', 'numero_ventas', 'unidades_vendidas', 'ticket_promedio',
                'ganancia', 'top_productos', 'ventas_por_vendedor', 'comparativa', 'serie',
            ]]);
    }

    public function test_el_vendedor_no_recibe_ganancia_top_ni_comparativa(): void
    {
        $vendedor = $this->actingAsRole('vendedor', ['warehouse_id' => $this->almacen->id]);

        $data = $this->getJson('/v1/metrics/sales?period=weekly')->assertOk()->json('data');

        $this->assertArrayHasKey('ingresos', $data);
        $this->assertArrayHasKey('ticket_promedio', $data);
        $this->assertArrayHasKey('serie', $data);
        $this->assertArrayNotHasKey('ganancia', $data);
        $this->assertArrayNotHasKey('top_productos', $data);
        $this->assertArrayNotHasKey('comparativa', $data);
    }

    public function test_el_vendedor_solo_ve_sus_propias_ventas_en_el_desglose_por_vendedor(): void
    {
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);
        $otro = User::factory()->create(['name' => 'Otro', 'warehouse_id' => $this->almacen->id]);
        $this->venta($otro, $product, 50, '2026-03-11 09:00:00'); // 100.00

        $vendedor = $this->actingAsRole('vendedor', ['warehouse_id' => $this->almacen->id]);
        $this->venta($vendedor, $product, 5, '2026-03-11 10:00:00'); // 10.00

        $data = $this->getJson('/v1/metrics/sales?period=weekly')->assertOk()->json('data');

        // Los totales del almacén sí incluyen las ventas de sus compañeros.
        $this->assertSame('110.00', $data['ingresos']);
        // El desglose por vendedor, no.
        $this->assertCount(1, $data['ventas_por_vendedor']);
        $this->assertSame($vendedor->id, $data['ventas_por_vendedor'][0]['user_id']);
        $this->assertSame('10.00', $data['ventas_por_vendedor'][0]['ingresos']);
    }

    public function test_ninguna_respuesta_al_vendedor_contiene_precio_de_compra(): void
    {
        $product = Product::factory()->create(['precio_compra' => 7.77, 'precio_venta' => 20.00]);
        $vendedor = $this->actingAsRole('vendedor', ['warehouse_id' => $this->almacen->id]);
        $this->venta($vendedor, $product, 3, '2026-03-11 10:00:00');

        $response = $this->getJson('/v1/metrics/sales?period=weekly')->assertOk();

        $this->assertStringNotContainsString('7.77', $response->getContent());
        $this->assertStringNotContainsString('precio_compra', $response->getContent());
    }
}
