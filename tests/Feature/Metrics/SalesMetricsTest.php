<?php

namespace Tests\Feature\Metrics;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalesMetricsTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $almacen;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-11 12:00:00'); // miércoles
        $this->almacen = Warehouse::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Crea una venta ya cerrada con una línea, sin pasar por la API.
     */
    private function venta(
        Warehouse $almacen,
        User $usuario,
        Product $product,
        float $cantidadBase,
        string $cuando,
    ): Sale {
        $subtotal = round($product->precio_venta * $cantidadBase, 2);

        $sale = Sale::factory()->for($almacen)->for($usuario)->create([
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

    public function test_metrica_diaria_agrega_ingresos_ventas_unidades_ganancia_y_ticket(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 3.00]);

        $this->venta($this->almacen, $admin, $product, 10, '2026-03-11 09:00:00'); // 30.00
        $this->venta($this->almacen, $admin, $product, 5, '2026-03-11 18:00:00');  // 15.00
        $this->venta($this->almacen, $admin, $product, 100, '2026-03-10 09:00:00'); // otro día

        $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')
            ->assertOk()
            ->assertJsonPath('data.periodo', 'daily')
            ->assertJsonPath('data.ingresos', '45.00')
            ->assertJsonPath('data.numero_ventas', 2)
            ->assertJsonPath('data.unidades_vendidas', '15.000')
            // (3.00 − 1.00) × 15 = 30.00
            ->assertJsonPath('data.ganancia', '30.00')
            ->assertJsonPath('data.ticket_promedio', '22.50');
    }

    public function test_metrica_semanal_cubre_de_lunes_a_domingo(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);

        $this->venta($this->almacen, $admin, $product, 10, '2026-03-09 09:00:00'); // lunes
        $this->venta($this->almacen, $admin, $product, 10, '2026-03-15 23:00:00'); // domingo
        $this->venta($this->almacen, $admin, $product, 10, '2026-03-16 00:30:00'); // lunes siguiente

        $this->getJson('/v1/metrics/sales?period=weekly&date=2026-03-11')
            ->assertOk()
            ->assertJsonPath('data.numero_ventas', 2)
            ->assertJsonPath('data.ingresos', '40.00');
    }

    public function test_metrica_mensual_cubre_el_mes_natural(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);

        $this->venta($this->almacen, $admin, $product, 5, '2026-03-01 00:00:00');
        $this->venta($this->almacen, $admin, $product, 5, '2026-03-31 23:59:00');
        $this->venta($this->almacen, $admin, $product, 5, '2026-04-01 00:00:00');

        $this->getJson('/v1/metrics/sales?period=monthly&date=2026-03-11')
            ->assertOk()->assertJsonPath('data.numero_ventas', 2);
    }

    public function test_sin_ventas_los_agregados_son_cero_y_el_ticket_no_divide_por_cero(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')
            ->assertOk()
            ->assertJsonPath('data.ingresos', '0.00')
            ->assertJsonPath('data.numero_ventas', 0)
            ->assertJsonPath('data.ticket_promedio', '0.00');
    }

    public function test_filtrar_por_almacen_aisla_los_datos_y_sin_filtro_son_globales(): void
    {
        $admin = $this->actingAsRole('admin');
        $otro = Warehouse::factory()->create();
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);

        $this->venta($this->almacen, $admin, $product, 10, '2026-03-11 09:00:00'); // 20.00
        $this->venta($otro, $admin, $product, 5, '2026-03-11 09:00:00');            // 10.00

        $this->getJson("/v1/metrics/sales?period=daily&date=2026-03-11&warehouse_id={$this->almacen->id}")
            ->assertOk()->assertJsonPath('data.ingresos', '20.00');

        $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')
            ->assertOk()->assertJsonPath('data.ingresos', '30.00');
    }

    public function test_sin_date_se_usa_el_momento_actual(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);
        $this->venta($this->almacen, $admin, $product, 4, '2026-03-11 10:00:00');

        $this->getJson('/v1/metrics/sales?period=daily')
            ->assertOk()->assertJsonPath('data.ingresos', '8.00');
    }

    public function test_un_periodo_invalido_se_rechaza(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/v1/metrics/sales?period=anual')
            ->assertStatus(422)->assertJsonValidationErrors('period');
    }

    public function test_el_vendedor_solo_puede_pedir_la_semana(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->almacen->id]);

        $this->getJson('/v1/metrics/sales?period=weekly')->assertOk();
        $this->getJson('/v1/metrics/sales?period=daily')->assertForbidden();
        $this->getJson('/v1/metrics/sales?period=monthly')->assertForbidden();
    }

    public function test_el_vendedor_solo_ve_su_almacen_aunque_pida_otro(): void
    {
        $ajeno = Warehouse::factory()->create();
        $otroUsuario = User::factory()->create();
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);

        $this->venta($this->almacen, $otroUsuario, $product, 3, '2026-03-11 10:00:00'); // 6.00
        $this->venta($ajeno, $otroUsuario, $product, 50, '2026-03-11 10:00:00');         // 100.00

        $this->actingAsRole('vendedor', ['warehouse_id' => $this->almacen->id]);

        $this->getJson("/v1/metrics/sales?period=weekly&warehouse_id={$ajeno->id}")
            ->assertOk()
            ->assertJsonPath('data.warehouse_id', $this->almacen->id)
            ->assertJsonPath('data.ingresos', '6.00');
    }
}
