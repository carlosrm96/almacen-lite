<?php

namespace Tests\Feature\Sales;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegisterSaleTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->warehouse = Warehouse::factory()->create();
    }

    private function producto(float $compra, float $venta, float $stock): Product
    {
        $product = Product::factory()->create(['precio_compra' => $compra, 'precio_venta' => $venta]);
        Stock::factory()->for($product)->for($this->warehouse)->create(['cantidad' => $stock]);

        return $product;
    }

    public function test_una_venta_descuenta_stock_y_devuelve_el_total(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 100);
        $pan = $this->producto(0.50, 1.20, 50);

        $this->postJson('/v1/sales', [
            'items' => [
                ['product_id' => $agua->id, 'cantidad' => 10],
                ['product_id' => $pan->id, 'cantidad' => 5],
            ],
        ])->assertCreated()
            // 10×0.90 + 5×1.20 = 9.00 + 6.00 = 15.00
            ->assertJsonPath('data.total', '15.00')
            ->assertJsonCount(2, 'data.items');

        $this->assertEqualsWithDelta(90.0, $agua->cantidadEn($this->warehouse->id), 0.001);
        $this->assertEqualsWithDelta(45.0, $pan->cantidadEn($this->warehouse->id), 0.001);
    }

    public function test_vender_en_una_unidad_no_base_descuenta_el_equivalente_en_base(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 100);
        $caja = Unit::factory()->create(['nombre' => 'caja', 'factor' => 24]);
        $agua->units()->create(['unit_id' => $caja->id, 'is_base' => false]);

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $agua->id, 'unit_id' => $caja->id, 'cantidad' => 2]],
        ])->assertCreated()
            // 2 cajas × 24 = 48 unidades base × 0.90 = 43.20
            ->assertJsonPath('data.total', '43.20')
            ->assertJsonPath('data.items.0.cantidad_base', '48.000');

        $this->assertEqualsWithDelta(52.0, $agua->cantidadEn($this->warehouse->id), 0.001);
    }

    public function test_una_unidad_no_asignada_al_producto_se_rechaza(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 100);
        $ajena = Unit::factory()->create(['factor' => 24]);

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $agua->id, 'unit_id' => $ajena->id, 'cantidad' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.unit_id');
    }

    public function test_stock_insuficiente_rechaza_la_venta_entera_y_no_toca_el_inventario(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 100);
        $pan = $this->producto(0.50, 1.20, 3);

        $this->postJson('/v1/sales', [
            'items' => [
                ['product_id' => $agua->id, 'cantidad' => 10],
                ['product_id' => $pan->id, 'cantidad' => 5],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Stock insuficiente')
            ->assertJsonPath('productos_afectados.0.product_id', $pan->id)
            ->assertJsonPath('productos_afectados.0.solicitado', '5.000')
            ->assertJsonPath('productos_afectados.0.disponible', '3.000')
            // Contrato del 422: `message`, `errors.items` y `productos_afectados`.
            ->assertJsonPath('errors.items.0', 'Stock insuficiente para 1 producto.');

        // Ni el ítem que sí tenía stock se descuenta.
        $this->assertEqualsWithDelta(100.0, $agua->cantidadEn($this->warehouse->id), 0.001);
        $this->assertEqualsWithDelta(3.0, $pan->cantidadEn($this->warehouse->id), 0.001);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
    }

    public function test_el_error_de_stock_lista_todos_los_productos_afectados(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $a = $this->producto(1, 2, 1);
        $b = $this->producto(1, 2, 1);

        $this->postJson('/v1/sales', [
            'items' => [
                ['product_id' => $a->id, 'cantidad' => 5],
                ['product_id' => $b->id, 'cantidad' => 5],
            ],
        ])->assertStatus(422)->assertJsonCount(2, 'productos_afectados');
    }

    public function test_el_mismo_producto_repetido_suma_para_comprobar_el_stock(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 10);

        $this->postJson('/v1/sales', [
            'items' => [
                ['product_id' => $agua->id, 'cantidad' => 6],
                ['product_id' => $agua->id, 'cantidad' => 6],
            ],
        ])->assertStatus(422)->assertJsonPath('productos_afectados.0.solicitado', '12.000');

        $this->assertEqualsWithDelta(10.0, $agua->cantidadEn($this->warehouse->id), 0.001);
    }

    public function test_vender_un_producto_sin_stock_en_ese_almacen_se_rechaza(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $product = Product::factory()->create();

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $product->id, 'cantidad' => 1]],
        ])->assertStatus(422)->assertJsonPath('productos_afectados.0.disponible', '0.000');
    }

    public function test_la_venta_guarda_el_snapshot_de_precios(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 100);

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $agua->id, 'cantidad' => 10]],
        ])->assertCreated();

        $agua->update(['precio_venta' => 5.00, 'precio_compra' => 3.00]);

        $this->assertDatabaseHas('sale_items', [
            'product_id' => $agua->id,
            'precio_venta_unit' => '0.90',
            'precio_compra_unit' => '0.40',
        ]);
    }

    public function test_no_se_puede_vender_un_producto_eliminado(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 100);
        $agua->delete();

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $agua->id, 'cantidad' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_la_venta_requiere_al_menos_un_item_con_cantidad_positiva(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 100);

        $this->postJson('/v1/sales', ['items' => []])
            ->assertStatus(422)->assertJsonValidationErrors('items');

        $this->postJson('/v1/sales', ['items' => [['product_id' => $agua->id, 'cantidad' => 0]]])
            ->assertStatus(422)->assertJsonValidationErrors('items.0.cantidad');
    }

    public function test_la_agregacion_de_lineas_del_mismo_producto_ocurre_tras_convertir_a_base(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        // Stock 50: 2 cajas (factor 24) + 10 unidades = 48 + 10 = 58 en base, que
        // debe superar el disponible. Si la suma ocurriera antes de convertir
        // (2 + 10 = 12) la venta pasaría erróneamente.
        $agua = $this->producto(0.40, 0.90, 50);
        $caja = Unit::factory()->create(['nombre' => 'caja', 'factor' => 24]);
        $agua->units()->create(['unit_id' => $caja->id, 'is_base' => false]);

        $this->postJson('/v1/sales', [
            'items' => [
                ['product_id' => $agua->id, 'unit_id' => $caja->id, 'cantidad' => 2],
                ['product_id' => $agua->id, 'cantidad' => 10],
            ],
        ])->assertStatus(422)->assertJsonPath('productos_afectados.0.solicitado', '58.000');

        $this->assertEqualsWithDelta(50.0, $agua->cantidadEn($this->warehouse->id), 0.001);
    }

    public function test_una_cantidad_menor_que_la_precision_de_la_columna_se_rechaza_con_un_422_limpio(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 100);

        // 0.00005 pasa `gt:0` pero es menor que el margen de tolerancia (0.0001)
        // usado al comparar disponibilidad: sin el mínimo de validación, llegaría
        // a intentar descontar una fila de stock inexistente y provocaría un 500.
        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $agua->id, 'cantidad' => 0.00005]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.cantidad');
    }

    public function test_registrar_una_venta_no_incurre_en_una_consulta_extra_por_linea(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        // Mismo producto en todas las líneas: por línea adicional el único coste
        // legítimo es su propio INSERT en `sale_items` más el `exists` de
        // validación sobre `product_id` (la fila de stock se agrega por
        // producto, no por línea). Antes de precargar `units.unit`, cada línea
        // sin `unit_id` disparaba además una consulta perezosa a `units`, así
        // que el coste por línea sería 3 en vez de 2.
        $agua = $this->producto(0.40, 0.90, 10000);

        // Precalienta la caché de roles/permisos (Spatie) para que no
        // distorsione el conteo: solo interesan las consultas de la venta.
        $this->postJson('/v1/sales', ['items' => [['product_id' => $agua->id, 'cantidad' => 1]]])
            ->assertCreated();

        DB::enableQueryLog();
        $this->postJson('/v1/sales', [
            'items' => [
                ['product_id' => $agua->id, 'cantidad' => 1],
                ['product_id' => $agua->id, 'cantidad' => 1],
            ],
        ])->assertCreated();
        $consultasConDosLineas = count(DB::getQueryLog());
        DB::flushQueryLog();

        $this->postJson('/v1/sales', [
            'items' => [
                ['product_id' => $agua->id, 'cantidad' => 1],
                ['product_id' => $agua->id, 'cantidad' => 1],
                ['product_id' => $agua->id, 'cantidad' => 1],
                ['product_id' => $agua->id, 'cantidad' => 1],
                ['product_id' => $agua->id, 'cantidad' => 1],
            ],
        ])->assertCreated();
        $consultasConCincoLineas = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 3 líneas de más deben costar exactamente 6 consultas más (su INSERT
        // más su `exists` de validación cada una), no 9: si quedara una
        // consulta perezosa por línea sin `unit_id`, el incremento sería mayor.
        $this->assertSame(
            6,
            $consultasConCincoLineas - $consultasConDosLineas,
            'Cada línea adicional del mismo producto no debe añadir una consulta perezosa a `units`.',
        );
    }
}
