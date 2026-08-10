<?php

namespace Tests\Feature\Metrics;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalesMetricsDetailTest extends TestCase
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

    private function venta(User $usuario, Product $product, float $cantidadBase, string $cuando): Sale
    {
        $subtotal = round($product->precio_venta * $cantidadBase, 2);

        $sale = Sale::factory()->for($this->almacen)->for($usuario)->create([
            'total' => $subtotal,
            'created_at' => $cuando,
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

        return $sale;
    }

    public function test_la_serie_diaria_tiene_24_puntos_por_hora(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);
        $this->venta($admin, $product, 5, '2026-03-11 09:30:00');  // 10.00
        $this->venta($admin, $product, 10, '2026-03-11 09:45:00'); // 20.00

        $response = $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')->assertOk();

        $this->assertCount(24, $response->json('data.serie'));
        $this->assertSame('09', $response->json('data.serie.9.etiqueta'));
        $this->assertSame('30.00', $response->json('data.serie.9.ingresos'));
        $this->assertSame(2, $response->json('data.serie.9.numero_ventas'));
        $this->assertSame('0.00', $response->json('data.serie.0.ingresos'));
    }

    public function test_la_serie_semanal_tiene_7_puntos_por_dia(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);
        $this->venta($admin, $product, 5, '2026-03-09 09:00:00'); // lunes

        $response = $this->getJson('/v1/metrics/sales?period=weekly&date=2026-03-11')->assertOk();

        $this->assertCount(7, $response->json('data.serie'));
        $this->assertSame('2026-03-09', $response->json('data.serie.0.etiqueta'));
        $this->assertSame('10.00', $response->json('data.serie.0.ingresos'));
    }

    public function test_la_serie_mensual_tiene_un_punto_por_dia_del_mes(): void
    {
        $this->actingAsRole('admin');

        $response = $this->getJson('/v1/metrics/sales?period=monthly&date=2026-03-11')->assertOk();

        $this->assertCount(31, $response->json('data.serie'));
        $this->assertSame('2026-03-01', $response->json('data.serie.0.etiqueta'));
    }

    public function test_el_top_de_productos_ordena_por_unidades_y_por_ingresos(): void
    {
        $admin = $this->actingAsRole('admin');
        $barato = Product::factory()->create(['nombre' => 'Agua', 'precio_compra' => 0.10, 'precio_venta' => 1.00]);
        $caro = Product::factory()->create(['nombre' => 'Vino', 'precio_compra' => 5.00, 'precio_venta' => 20.00]);

        $this->venta($admin, $barato, 100, '2026-03-11 09:00:00'); // 100 unidades, 100.00
        $this->venta($admin, $caro, 10, '2026-03-11 10:00:00');    // 10 unidades, 200.00

        $response = $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')->assertOk();

        $this->assertSame('Agua', $response->json('data.top_productos.por_unidades.0.nombre'));
        $this->assertSame('100.000', $response->json('data.top_productos.por_unidades.0.unidades'));
        $this->assertSame('Vino', $response->json('data.top_productos.por_ingresos.0.nombre'));
        $this->assertSame('200.00', $response->json('data.top_productos.por_ingresos.0.ingresos'));
    }

    public function test_las_ventas_por_vendedor_se_desglosan_por_usuario(): void
    {
        $admin = $this->actingAsRole('admin');
        $ana = User::factory()->create(['name' => 'Ana', 'warehouse_id' => $this->almacen->id]);
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);

        $this->venta($ana, $product, 10, '2026-03-11 09:00:00'); // 20.00
        $this->venta($ana, $product, 5, '2026-03-11 10:00:00');  // 10.00
        $this->venta($admin, $product, 1, '2026-03-11 11:00:00'); // 2.00

        $response = $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')->assertOk();

        $porVendedor = collect($response->json('data.ventas_por_vendedor'))->keyBy('user_id');
        $this->assertSame('30.00', $porVendedor[$ana->id]['ingresos']);
        $this->assertSame(2, $porVendedor[$ana->id]['numero_ventas']);
        $this->assertSame('2.00', $porVendedor[$admin->id]['ingresos']);
    }

    public function test_la_comparativa_calcula_la_variacion_frente_al_periodo_anterior(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);

        $this->venta($admin, $product, 50, '2026-03-10 09:00:00'); // ayer: 100.00
        $this->venta($admin, $product, 75, '2026-03-11 09:00:00'); // hoy: 150.00

        $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')
            ->assertOk()
            ->assertJsonPath('data.comparativa.ingresos_anterior', '100.00')
            ->assertJsonPath('data.comparativa.variacion_ingresos', 50.0)
            ->assertJsonPath('data.comparativa.variacion_numero_ventas', 0.0);
    }

    public function test_si_el_periodo_anterior_no_tuvo_ventas_la_variacion_es_null(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);
        $this->venta($admin, $product, 10, '2026-03-11 09:00:00');

        $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')
            ->assertOk()
            ->assertJsonPath('data.comparativa.ingresos_anterior', '0.00')
            ->assertJsonPath('data.comparativa.variacion_ingresos', null);
    }
}
