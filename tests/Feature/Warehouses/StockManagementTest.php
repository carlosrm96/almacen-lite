<?php

namespace Tests\Feature\Warehouses;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_admin_fija_el_stock_de_un_producto_en_un_almacen(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $this->postJson("/v1/products/{$product->id}/stock", [
            'warehouse_id' => $warehouse->id,
            'cantidad' => 120,
            'minimo' => 20,
        ])->assertOk()
            ->assertJsonPath('data.cantidad', '120.000')
            ->assertJsonPath('data.minimo', '20.000');

        $this->assertDatabaseHas('stocks', [
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id, 'accion' => 'stock.fijado', 'warehouse_id' => $warehouse->id,
        ]);
    }

    public function test_fijar_stock_sustituye_la_cantidad_y_audita_el_valor_anterior(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        Stock::factory()->for($product)->for($warehouse)->create(['cantidad' => 50]);

        $this->postJson("/v1/products/{$product->id}/stock", [
            'warehouse_id' => $warehouse->id,
            'cantidad' => 30,
        ])->assertOk()->assertJsonPath('data.cantidad', '30.000');

        $this->assertSame(1, Stock::where('product_id', $product->id)->count());

        $log = AuditLog::where('accion', 'stock.fijado')->firstOrFail();
        $this->assertSame('50.000', $log->datos['anterior']);
        $this->assertSame('30.000', $log->datos['nuevo']);
    }

    public function test_el_stock_no_puede_ser_negativo(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $this->postJson("/v1/products/{$product->id}/stock", [
            'warehouse_id' => $warehouse->id, 'cantidad' => -5,
        ])->assertStatus(422)->assertJsonValidationErrors('cantidad');
    }

    public function test_el_admin_puede_crear_un_producto_con_stock_inicial(): void
    {
        $this->actingAsRole('admin');
        $base = Unit::factory()->base()->create();
        $warehouse = Warehouse::factory()->create();

        $this->postJson('/v1/products', [
            'nombre' => 'Agua 1L',
            'precio_compra' => 0.40,
            'precio_venta' => 0.90,
            'base_unit_id' => $base->id,
            'warehouse_id' => $warehouse->id,
            'cantidad' => 200,
        ])->assertCreated();

        $product = Product::firstOrFail();
        $this->assertEqualsWithDelta(200.0, $product->cantidadEn($warehouse->id), 0.001);
    }

    public function test_el_stock_inicial_exige_almacen_y_cantidad_juntos(): void
    {
        $this->actingAsRole('admin');
        $base = Unit::factory()->base()->create();
        $warehouse = Warehouse::factory()->create();

        $this->postJson('/v1/products', [
            'nombre' => 'X', 'precio_compra' => 1, 'precio_venta' => 2,
            'base_unit_id' => $base->id, 'warehouse_id' => $warehouse->id,
        ])->assertStatus(422)->assertJsonValidationErrors('cantidad');
    }

    public function test_el_vendedor_ve_la_cantidad_de_su_almacen_y_no_la_de_otros(): void
    {
        $product = Product::factory()->create();
        $suyo = Warehouse::factory()->create();
        $ajeno = Warehouse::factory()->create();
        Stock::factory()->for($product)->for($suyo)->create(['cantidad' => 7]);
        Stock::factory()->for($product)->for($ajeno)->create(['cantidad' => 999]);

        $this->actingAsRole('vendedor', ['warehouse_id' => $suyo->id]);

        $response = $this->getJson('/v1/products')->assertOk()
            ->assertJsonPath('data.0.cantidad', '7.000');

        $this->assertStringNotContainsString('999', $response->getContent());
    }

    public function test_el_vendedor_no_puede_fijar_stock(): void
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $this->actingAsRole('vendedor', ['warehouse_id' => $warehouse->id]);

        $this->postJson("/v1/products/{$product->id}/stock", [
            'warehouse_id' => $warehouse->id, 'cantidad' => 10,
        ])->assertForbidden();
    }

    public function test_no_se_puede_borrar_un_almacen_con_stock(): void
    {
        $this->actingAsRole('admin');
        $warehouse = Warehouse::factory()->create();
        Stock::factory()->for(Product::factory())->for($warehouse)->create(['cantidad' => 5]);

        $this->deleteJson("/v1/warehouses/{$warehouse->id}")->assertStatus(422);
        $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id]);
    }

    public function test_no_se_puede_borrar_un_almacen_con_usuarios_asignados(): void
    {
        $this->actingAsRole('admin');
        $warehouse = Warehouse::factory()->create();
        User::factory()->create(['warehouse_id' => $warehouse->id]);

        $this->deleteJson("/v1/warehouses/{$warehouse->id}")->assertStatus(422);
    }

    public function test_se_puede_borrar_un_almacen_vacio(): void
    {
        $this->actingAsRole('admin');
        $warehouse = Warehouse::factory()->create();

        $this->deleteJson("/v1/warehouses/{$warehouse->id}")->assertNoContent();
    }
}
