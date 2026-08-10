<?php

namespace Tests\Feature\Catalog;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_admin_crea_un_producto_con_su_unidad_base(): void
    {
        $admin = $this->actingAsRole('admin');
        $base = Unit::factory()->base()->create();

        $this->postJson('/v1/products', [
            'nombre' => 'Agua 1L',
            'precio_compra' => 0.40,
            'precio_venta' => 0.90,
            'base_unit_id' => $base->id,
        ])->assertCreated()
            ->assertJsonPath('data.nombre', 'Agua 1L')
            ->assertJsonPath('data.precio_compra', '0.40');

        $product = Product::first();
        $this->assertTrue($product->baseProductUnit()->is_base);
        $this->assertSame($base->id, $product->baseProductUnit()->unit_id);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'accion' => 'producto.creado',
            'auditable_id' => $product->id,
        ]);
    }

    public function test_la_unidad_base_debe_tener_factor_1(): void
    {
        $this->actingAsRole('admin');
        $caja = Unit::factory()->create(['factor' => 24]);

        $this->postJson('/v1/products', [
            'nombre' => 'Agua 1L',
            'precio_compra' => 0.40,
            'precio_venta' => 0.90,
            'base_unit_id' => $caja->id,
        ])->assertStatus(422)->assertJsonValidationErrors('base_unit_id');
    }

    public function test_los_precios_son_obligatorios_y_no_negativos(): void
    {
        $this->actingAsRole('admin');
        $base = Unit::factory()->base()->create();

        $this->postJson('/v1/products', ['nombre' => 'X', 'base_unit_id' => $base->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['precio_compra', 'precio_venta']);

        $this->postJson('/v1/products', [
            'nombre' => 'X', 'base_unit_id' => $base->id,
            'precio_compra' => -1, 'precio_venta' => 2,
        ])->assertStatus(422)->assertJsonValidationErrors('precio_compra');
    }

    public function test_al_actualizar_un_producto_queda_registro_de_los_campos_cambiados(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_venta' => 1.00]);

        $this->putJson("/v1/products/{$product->id}", ['precio_venta' => 1.50])
            ->assertOk()->assertJsonPath('data.precio_venta', '1.50');

        $log = AuditLog::where('accion', 'producto.actualizado')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(['precio_venta' => ['antes' => '1.00', 'despues' => '1.50']], $log->datos);
    }

    public function test_el_borrado_es_logico_y_queda_auditado(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create();

        $this->deleteJson("/v1/products/{$product->id}")->assertNoContent();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'accion' => 'producto.eliminado',
            'auditable_id' => $product->id,
        ]);
        $this->getJson('/v1/products')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_el_vendedor_no_ve_el_precio_de_compra(): void
    {
        Product::factory()->create(['nombre' => 'Agua 1L', 'precio_compra' => 0.40, 'precio_venta' => 0.90]);
        $this->actingAsRole('vendedor', ['warehouse_id' => Warehouse::factory()->create()->id]);

        $response = $this->getJson('/v1/products')->assertOk();

        $response->assertJsonPath('data.0.nombre', 'Agua 1L')
            ->assertJsonPath('data.0.precio_venta', '0.90');
        $this->assertArrayNotHasKey('precio_compra', $response->json('data.0'));
        $this->assertStringNotContainsString('0.40', $response->getContent());
    }

    public function test_el_vendedor_no_puede_crear_editar_ni_borrar_productos(): void
    {
        // Se crea primero la unidad base para que Product::factory() la
        // reutilice vía firstWhere('factor', 1) y no colisione con el
        // nombre único 'unidad' al crear una segunda unidad base.
        $base = Unit::factory()->base()->create();
        $product = Product::factory()->create();
        $this->actingAsRole('vendedor', ['warehouse_id' => Warehouse::factory()->create()->id]);

        $this->postJson('/v1/products', [
            'nombre' => 'X', 'precio_compra' => 1, 'precio_venta' => 2, 'base_unit_id' => $base->id,
        ])->assertForbidden();
        $this->putJson("/v1/products/{$product->id}", ['precio_venta' => 5])->assertForbidden();
        $this->deleteJson("/v1/products/{$product->id}")->assertForbidden();
    }
}
