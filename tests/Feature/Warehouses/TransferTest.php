<?php

namespace Tests\Feature\Warehouses;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_admin_transfiere_stock_de_un_almacen_a_otro(): void
    {
        $admin = $this->actingAsRole('admin');
        $origen = Warehouse::factory()->create();
        $destino = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Stock::factory()->for($product)->for($origen)->create(['cantidad' => 100]);
        Stock::factory()->for($product)->for($destino)->create(['cantidad' => 10]);

        $this->postJson('/v1/transfers', [
            'product_id' => $product->id,
            'from_warehouse_id' => $origen->id,
            'to_warehouse_id' => $destino->id,
            'cantidad' => 30,
        ])->assertCreated()->assertJsonPath('data.cantidad_base', '30.000');

        $this->assertEqualsWithDelta(70.0, $product->cantidadEn($origen->id), 0.001);
        $this->assertEqualsWithDelta(40.0, $product->cantidadEn($destino->id), 0.001);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id, 'accion' => 'transferencia.realizada', 'auditable_id' => $product->id,
        ]);

        // El requisito real no es solo que se audite, sino que `datos` lleve el
        // origen y el destino: sin eso la auditoría no sirve para reconstruir
        // el movimiento.
        $log = AuditLog::where('accion', 'transferencia.realizada')->first();
        $this->assertSame($origen->id, $log->datos['desde']);
        $this->assertSame($destino->id, $log->datos['hacia']);
    }

    public function test_la_transferencia_crea_la_fila_de_stock_en_destino_si_no_existia(): void
    {
        $this->actingAsRole('admin');
        $origen = Warehouse::factory()->create();
        $destino = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Stock::factory()->for($product)->for($origen)->create(['cantidad' => 100]);

        $this->postJson('/v1/transfers', [
            'product_id' => $product->id,
            'from_warehouse_id' => $origen->id,
            'to_warehouse_id' => $destino->id,
            'cantidad' => 25,
        ])->assertCreated();

        $this->assertEqualsWithDelta(25.0, $product->cantidadEn($destino->id), 0.001);
    }

    public function test_transferir_en_una_unidad_no_base_convierte_a_base(): void
    {
        $this->actingAsRole('admin');
        $origen = Warehouse::factory()->create();
        $destino = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $caja = Unit::factory()->create(['nombre' => 'caja', 'factor' => 24]);
        $product->units()->create(['unit_id' => $caja->id, 'is_base' => false]);
        Stock::factory()->for($product)->for($origen)->create(['cantidad' => 100]);

        $this->postJson('/v1/transfers', [
            'product_id' => $product->id,
            'from_warehouse_id' => $origen->id,
            'to_warehouse_id' => $destino->id,
            'unit_id' => $caja->id,
            'cantidad' => 2,
        ])->assertCreated()->assertJsonPath('data.cantidad_base', '48.000');

        $this->assertEqualsWithDelta(52.0, $product->cantidadEn($origen->id), 0.001);
        $this->assertEqualsWithDelta(48.0, $product->cantidadEn($destino->id), 0.001);
    }

    public function test_no_se_puede_transferir_mas_de_lo_disponible(): void
    {
        $this->actingAsRole('admin');
        $origen = Warehouse::factory()->create();
        $destino = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Stock::factory()->for($product)->for($origen)->create(['cantidad' => 10]);

        $this->postJson('/v1/transfers', [
            'product_id' => $product->id,
            'from_warehouse_id' => $origen->id,
            'to_warehouse_id' => $destino->id,
            'cantidad' => 11,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Stock insuficiente')
            ->assertJsonPath('productos_afectados.0.disponible', '10.000');

        $this->assertEqualsWithDelta(10.0, $product->cantidadEn($origen->id), 0.001);
        $this->assertDatabaseCount('transfers', 0);
    }

    public function test_origen_y_destino_deben_ser_distintos(): void
    {
        $this->actingAsRole('admin');
        $almacen = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $this->postJson('/v1/transfers', [
            'product_id' => $product->id,
            'from_warehouse_id' => $almacen->id,
            'to_warehouse_id' => $almacen->id,
            'cantidad' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('to_warehouse_id');
    }

    public function test_el_admin_puede_listar_las_transferencias(): void
    {
        $this->actingAsRole('admin');
        $origen = Warehouse::factory()->create();
        $destino = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Stock::factory()->for($product)->for($origen)->create(['cantidad' => 100]);

        $this->postJson('/v1/transfers', [
            'product_id' => $product->id,
            'from_warehouse_id' => $origen->id,
            'to_warehouse_id' => $destino->id,
            'cantidad' => 5,
        ])->assertCreated();

        $this->getJson('/v1/transfers')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.from_warehouse_id', $origen->id);
    }

    public function test_el_vendedor_no_puede_transferir_ni_ver_transferencias(): void
    {
        $origen = Warehouse::factory()->create();
        $destino = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Stock::factory()->for($product)->for($origen)->create(['cantidad' => 100]);

        $this->actingAsRole('vendedor', ['warehouse_id' => $origen->id]);

        $this->postJson('/v1/transfers', [
            'product_id' => $product->id,
            'from_warehouse_id' => $origen->id,
            'to_warehouse_id' => $destino->id,
            'cantidad' => 5,
        ])->assertForbidden();

        $this->getJson('/v1/transfers')->assertForbidden();
    }

    public function test_no_se_puede_transferir_un_producto_eliminado(): void
    {
        $this->actingAsRole('admin');
        $origen = Warehouse::factory()->create();
        $destino = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Stock::factory()->for($product)->for($origen)->create(['cantidad' => 100]);
        $product->delete();

        $this->postJson('/v1/transfers', [
            'product_id' => $product->id,
            'from_warehouse_id' => $origen->id,
            'to_warehouse_id' => $destino->id,
            'cantidad' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('product_id');
    }

    public function test_una_unidad_no_asignada_al_producto_se_rechaza_con_un_422(): void
    {
        $this->actingAsRole('admin');
        $origen = Warehouse::factory()->create();
        $destino = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Stock::factory()->for($product)->for($origen)->create(['cantidad' => 100]);
        $ajena = Unit::factory()->create(['factor' => 24]);

        // Sin el `withValidator` equivalente al de `StoreSaleRequest`, esto
        // pasaba la validación y `Product::toBase()` lanzaba un `RuntimeException`
        // sin capturar, filtrando un 500 en vez de un 422 limpio.
        $this->postJson('/v1/transfers', [
            'product_id' => $product->id,
            'from_warehouse_id' => $origen->id,
            'to_warehouse_id' => $destino->id,
            'unit_id' => $ajena->id,
            'cantidad' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('unit_id');
    }

    public function test_una_cantidad_menor_que_la_precision_de_la_columna_se_rechaza_con_un_422_limpio(): void
    {
        $this->actingAsRole('admin');
        $origen = Warehouse::factory()->create();
        $destino = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Stock::factory()->for($product)->for($origen)->create(['cantidad' => 100]);

        $this->postJson('/v1/transfers', [
            'product_id' => $product->id,
            'from_warehouse_id' => $origen->id,
            'to_warehouse_id' => $destino->id,
            'cantidad' => 0.00005,
        ])->assertStatus(422)->assertJsonValidationErrors('cantidad');
    }
}
