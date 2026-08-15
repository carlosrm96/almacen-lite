<?php

namespace Tests\Feature\Sales;

use App\Modules\Catalog\Models\Currency;
use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un catálogo que mezcla CUP y USD no puede romper la agregación: los importes
 * agregados (`sales.total`, `sale_items.subtotal`) se guardan en moneda base.
 */
class MultiCurrencySaleTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private Currency $cup;

    private Currency $usd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::factory()->create();
        $this->cup = Currency::create([
            'codigo' => 'CUP', 'nombre' => 'Peso cubano', 'simbolo' => '$',
            'tasa' => 1, 'es_base' => true,
        ]);
        $this->usd = Currency::create([
            'codigo' => 'USD', 'nombre' => 'Dólar estadounidense', 'simbolo' => 'US$',
            'tasa' => 420,
        ]);
    }

    private function producto(float $compra, float $venta, float $stock, ?Currency $moneda = null): Product
    {
        $product = Product::factory()->create([
            'precio_compra' => $compra,
            'precio_venta' => $venta,
            'currency_id' => $moneda?->id,
        ]);
        Stock::factory()->for($product)->for($this->warehouse)->create(['cantidad' => $stock]);

        return $product;
    }

    public function test_vender_un_producto_en_usd_guarda_el_total_en_moneda_base(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $aceite = $this->producto(2.10, 4.50, 100, $this->usd);

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $aceite->id, 'cantidad' => 2]],
        ])->assertCreated()
            // 2 × 4.50 USD × 420 = 3 780.00 CUP
            ->assertJsonPath('data.total', '3780.00')
            ->assertJsonPath('data.moneda', 'CUP')
            // El precio unitario se conserva en la moneda con la que se vendió.
            ->assertJsonPath('data.items.0.precio_venta_unit', '4.50')
            ->assertJsonPath('data.items.0.moneda', 'USD')
            ->assertJsonPath('data.items.0.tasa_cambio', '420.000000')
            ->assertJsonPath('data.items.0.subtotal', '3780.00');
    }

    public function test_una_venta_que_mezcla_monedas_suma_todo_en_moneda_base(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(60, 150, 100, $this->cup);
        $aceite = $this->producto(2.10, 4.50, 100, $this->usd);

        $this->postJson('/v1/sales', [
            'items' => [
                ['product_id' => $agua->id, 'cantidad' => 3],
                ['product_id' => $aceite->id, 'cantidad' => 1],
            ],
        ])->assertCreated()
            // 3 × 150 CUP = 450; 1 × 4.50 USD × 420 = 1 890 → 2 340.00
            ->assertJsonPath('data.total', '2340.00');
    }

    public function test_un_producto_sin_moneda_se_vende_en_la_moneda_base(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(60, 150, 100);

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $agua->id, 'cantidad' => 2]],
        ])->assertCreated()
            ->assertJsonPath('data.total', '300.00')
            ->assertJsonPath('data.items.0.moneda', 'CUP')
            ->assertJsonPath('data.items.0.tasa_cambio', '1.000000');
    }

    public function test_cambiar_la_tasa_despues_no_altera_una_venta_ya_registrada(): void
    {
        // Misma regla que los precios: la devaluación de mañana no reescribe la
        // venta de ayer.
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $aceite = $this->producto(2.10, 4.50, 100, $this->usd);

        $saleId = $this->postJson('/v1/sales', [
            'items' => [['product_id' => $aceite->id, 'cantidad' => 2]],
        ])->assertCreated()->json('data.id');

        $this->usd->update(['tasa' => 1000]);

        $this->getJson("/v1/sales/{$saleId}")
            ->assertOk()
            ->assertJsonPath('data.total', '3780.00')
            ->assertJsonPath('data.items.0.tasa_cambio', '420.000000');
    }

    public function test_la_ganancia_de_las_metricas_convierte_a_moneda_base(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $aceite = $this->producto(2.10, 4.50, 100, $this->usd);

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $aceite->id, 'cantidad' => 2]],
        ])->assertCreated();

        $this->actingAsRole('admin');

        $data = $this->getJson('/v1/metrics/sales?period=daily')->assertOk()->json('data');

        // (4.50 − 2.10) × 2 × 420 = 2 016.00 CUP
        $this->assertSame('2016.00', $data['ganancia']);
        $this->assertSame('3780.00', $data['ingresos']);
        $this->assertSame('CUP', $data['moneda']);
    }

    public function test_el_valor_del_inventario_convierte_cada_moneda_a_la_base(): void
    {
        $this->actingAsRole('admin');
        $this->producto(60, 150, 5, $this->cup);      // 300 a coste, 750 a venta
        $this->producto(2.10, 4.50, 10, $this->usd);  // 8 820 a coste, 18 900 a venta

        $data = $this->getJson('/v1/metrics/inventory')->assertOk()->json('data');

        $this->assertSame('9120.00', $data['total_a_coste']);
        $this->assertSame('19650.00', $data['total_a_venta']);
        $this->assertSame('CUP', $data['moneda']);
    }

    public function test_un_producto_sin_moneda_se_valora_como_moneda_base(): void
    {
        $this->actingAsRole('admin');
        $this->producto(60, 150, 5);

        $data = $this->getJson('/v1/metrics/inventory')->assertOk()->json('data');

        $this->assertSame('300.00', $data['total_a_coste']);
        $this->assertSame('750.00', $data['total_a_venta']);
    }
}
