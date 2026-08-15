<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Currency;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Warehouses\Models\Warehouse;
use Database\Seeders\CurrenciesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function usd(float $tasa = 420): Currency
    {
        return Currency::create([
            'codigo' => 'USD',
            'nombre' => 'Dólar estadounidense',
            'simbolo' => 'US$',
            'tasa' => $tasa,
        ]);
    }

    private function cup(): Currency
    {
        return Currency::create([
            'codigo' => 'CUP',
            'nombre' => 'Peso cubano',
            'simbolo' => '$',
            'tasa' => 1,
            'es_base' => true,
        ]);
    }

    public function test_el_seeder_deja_cup_como_base_y_usd_disponible(): void
    {
        $this->seed(CurrenciesSeeder::class);

        $this->assertSame('CUP', Currency::base()->codigo);
        $this->assertEqualsWithDelta(1.0, Currency::base()->tasa, 0.000001);
        $this->assertEqualsWithDelta(420.0, Currency::where('codigo', 'USD')->value('tasa'), 0.000001);
    }

    public function test_el_seeder_es_idempotente_y_no_pisa_una_tasa_ajustada_a_mano(): void
    {
        $this->seed(CurrenciesSeeder::class);
        Currency::where('codigo', 'USD')->update(['tasa' => 500]);

        $this->seed(CurrenciesSeeder::class);

        $this->assertSame(2, Currency::count());
        $this->assertEqualsWithDelta(500.0, Currency::where('codigo', 'USD')->value('tasa'), 0.000001);
    }

    public function test_la_moneda_base_siempre_se_guarda_con_tasa_uno(): void
    {
        // Igual que la unidad base tiene factor 1: una tasa distinta en la
        // moneda base reinterpretaría en silencio todos los importes guardados.
        $base = Currency::create([
            'codigo' => 'CUP',
            'nombre' => 'Peso cubano',
            'simbolo' => '$',
            'tasa' => 37.5,
            'es_base' => true,
        ]);

        $this->assertEqualsWithDelta(1.0, $base->fresh()->tasa, 0.000001);
    }

    public function test_el_listado_devuelve_la_moneda_base_primero(): void
    {
        $this->actingAsRole('admin');
        $this->seed(CurrenciesSeeder::class);

        $data = $this->getJson('/v1/currencies')->assertOk()->json('data');

        $this->assertSame('CUP', $data[0]['codigo']);
        $this->assertTrue($data[0]['es_base']);
        $this->assertSame('USD', $data[1]['codigo']);
    }

    public function test_el_vendedor_tambien_puede_consultar_las_monedas(): void
    {
        // Un precio sin moneda no es un precio: el vendedor necesita el listado.
        $almacen = Warehouse::factory()->create();
        $this->actingAsRole('vendedor', ['warehouse_id' => $almacen->id]);
        $this->seed(CurrenciesSeeder::class);

        $this->getJson('/v1/currencies')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_un_producto_sin_moneda_se_reporta_en_la_moneda_base(): void
    {
        $this->actingAsRole('admin');
        $this->cup();
        $product = Product::factory()->create();

        $this->getJson("/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.moneda.codigo', 'CUP')
            ->assertJsonPath('data.moneda.simbolo', '$');
    }

    public function test_un_producto_en_usd_se_reporta_con_su_moneda(): void
    {
        $this->actingAsRole('admin');
        $this->cup();
        $product = Product::factory()->create(['currency_id' => $this->usd()->id]);

        $this->getJson("/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.moneda.codigo', 'USD');
    }

    public function test_el_vendedor_ve_la_moneda_pero_sigue_sin_ver_el_precio_de_compra(): void
    {
        $almacen = Warehouse::factory()->create();
        $this->actingAsRole('vendedor', ['warehouse_id' => $almacen->id]);
        $this->cup();
        $product = Product::factory()->create(['currency_id' => $this->usd()->id]);

        $this->getJson("/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.moneda.codigo', 'USD')
            ->assertJsonMissingPath('data.precio_compra');
    }

    public function test_se_puede_crear_un_producto_indicando_su_moneda(): void
    {
        $this->actingAsRole('admin');
        $usd = $this->usd();
        $base = Unit::factory()->create(['nombre' => 'unidad', 'factor' => 1]);

        $this->postJson('/v1/products', [
            'nombre' => 'Aceite girasol 1L',
            'precio_compra' => 2.10,
            'precio_venta' => 4.50,
            'currency_id' => $usd->id,
            'base_unit_id' => $base->id,
        ])->assertCreated()->assertJsonPath('data.moneda.codigo', 'USD');

        $this->assertSame($usd->id, Product::where('nombre', 'Aceite girasol 1L')->value('currency_id'));
    }

    public function test_una_moneda_inexistente_se_rechaza(): void
    {
        $this->actingAsRole('admin');
        $base = Unit::factory()->create(['nombre' => 'unidad', 'factor' => 1]);

        $this->postJson('/v1/products', [
            'nombre' => 'Fantasma',
            'precio_compra' => 1,
            'precio_venta' => 2,
            'currency_id' => 9999,
            'base_unit_id' => $base->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('currency_id');
    }

    public function test_una_moneda_inactiva_se_rechaza(): void
    {
        $this->actingAsRole('admin');
        $inactiva = $this->usd();
        $inactiva->update(['activo' => false]);
        $base = Unit::factory()->create(['nombre' => 'unidad', 'factor' => 1]);

        $this->postJson('/v1/products', [
            'nombre' => 'Fantasma',
            'precio_compra' => 1,
            'precio_venta' => 2,
            'currency_id' => $inactiva->id,
            'base_unit_id' => $base->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('currency_id');
    }
}
