<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Lo que un negocio no debe ver del otro.
 *
 * El escenario siempre es el mismo: `comoOtraEmpresa()` monta datos ajenos y
 * el test comprueba que desde esta empresa no se llega a ellos. Un recurso de
 * otra empresa responde `404`, no `403`: el scope lo hace invisible, y
 * confirmar que existe ya sería una fuga.
 *
 * Ver docs/superpowers/specs/2026-08-17-multi-empresa-y-registro-design.md
 */
class CompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_listados_solo_traen_lo_de_la_propia_empresa(): void
    {
        $this->actingAsRole('admin');
        Warehouse::factory()->create(['nombre' => 'Central']);
        // Cada producto arrastra su unidad base, así que este negocio acaba con
        // una unidad — la suya, no las del otro.
        Product::factory()->create(['nombre' => 'Arroz']);

        $this->comoOtraEmpresa(function (): void {
            Warehouse::factory()->count(3)->create();
            Product::factory()->count(4)->create();
        });

        $this->getJson('/v1/warehouses')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/v1/units')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/v1/products')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_un_almacen_de_otra_empresa_no_se_puede_ver_ni_tocar(): void
    {
        $this->actingAsRole('admin');

        $ajeno = $this->comoOtraEmpresa(fn (): Warehouse => Warehouse::factory()->create());

        $this->getJson("/v1/warehouses/{$ajeno->id}")->assertNotFound();
        $this->putJson("/v1/warehouses/{$ajeno->id}", ['nombre' => 'Mío'])->assertNotFound();
        $this->deleteJson("/v1/warehouses/{$ajeno->id}")->assertNotFound();

        $this->assertDatabaseHas('warehouses', ['id' => $ajeno->id, 'nombre' => $ajeno->nombre]);
    }

    public function test_un_producto_de_otra_empresa_no_se_puede_ver_ni_tocar(): void
    {
        $this->actingAsRole('admin');

        $ajeno = $this->comoOtraEmpresa(fn (): Product => Product::factory()->create());

        $this->getJson("/v1/products/{$ajeno->id}")->assertNotFound();
        $this->putJson("/v1/products/{$ajeno->id}", ['nombre' => 'Mío'])->assertNotFound();
        $this->deleteJson("/v1/products/{$ajeno->id}")->assertNotFound();
    }

    public function test_una_venta_de_otra_empresa_no_se_puede_ver(): void
    {
        $this->actingAsRole('admin');

        $ajena = $this->comoOtraEmpresa(fn (): Sale => Sale::factory()->create());

        $this->getJson("/v1/sales/{$ajena->id}")->assertNotFound();
        $this->getJson('/v1/sales')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_no_se_puede_atar_un_vendedor_al_almacen_de_otra_empresa(): void
    {
        // La regla `exists` consulta la tabla en crudo: sin acotarla por
        // empresa, bastaría con adivinar el id del almacén ajeno.
        $this->actingAsRole('admin');

        $ajeno = $this->comoOtraEmpresa(fn (): Warehouse => Warehouse::factory()->create());

        $this->postJson('/v1/users', [
            'name' => 'Beto', 'email' => 'beto@almacen.test', 'password' => 'secreto123',
            'rol' => 'vendedor', 'warehouse_id' => $ajeno->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('warehouse_id');
    }

    public function test_no_se_puede_fijar_stock_en_un_almacen_de_otra_empresa(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();

        $ajeno = $this->comoOtraEmpresa(fn (): Warehouse => Warehouse::factory()->create());

        $this->postJson("/v1/products/{$product->id}/stock", [
            'warehouse_id' => $ajeno->id, 'cantidad' => 10,
        ])->assertUnprocessable()->assertJsonValidationErrors('warehouse_id');
    }

    public function test_no_se_puede_transferir_hacia_un_almacen_de_otra_empresa(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();
        $propio = Warehouse::factory()->create();

        $ajeno = $this->comoOtraEmpresa(fn (): Warehouse => Warehouse::factory()->create());

        $this->postJson('/v1/transfers', [
            'product_id' => $product->id,
            'from_warehouse_id' => $propio->id,
            'to_warehouse_id' => $ajeno->id,
            'cantidad' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('to_warehouse_id');
    }

    public function test_dos_empresas_pueden_repetir_el_nombre_de_un_almacen_o_una_unidad(): void
    {
        // Los únicos son por empresa: que un negocio llame «Central» a su
        // almacén no puede impedírselo a otro.
        $this->actingAsRole('admin');

        $this->comoOtraEmpresa(function (): void {
            Warehouse::factory()->create(['nombre' => 'Central']);
            Unit::factory()->create(['nombre' => 'Caja']);
        });

        $this->postJson('/v1/warehouses', ['nombre' => 'Central'])->assertCreated();
        $this->postJson('/v1/units', ['nombre' => 'Caja', 'factor' => 12])->assertCreated();
    }

    public function test_las_metricas_no_cuentan_las_ventas_de_otra_empresa(): void
    {
        Carbon::setTestNow('2026-03-11 12:00:00');

        $admin = $this->actingAsRole('admin');
        $this->venta($admin, Warehouse::factory()->create(), Product::factory()->create(['precio_venta' => 3.00]), 10);

        $this->comoOtraEmpresa(function (): void {
            $otroAdmin = User::factory()->create();
            $this->venta($otroAdmin, Warehouse::factory()->create(), Product::factory()->create(['precio_venta' => 100.00]), 50);
        });

        $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')
            ->assertOk()
            ->assertJsonPath('data.numero_ventas', 1)
            ->assertJsonPath('data.ingresos', '30.00');

        Carbon::setTestNow();
    }

    public function test_la_auditoria_no_muestra_los_movimientos_de_otra_empresa(): void
    {
        $this->actingAsRole('admin');
        $this->postJson('/v1/warehouses', ['nombre' => 'Central'])->assertCreated();

        $propios = $this->getJson('/v1/audit-logs')->assertOk()->json('meta.total');

        $this->comoOtraEmpresa(function (): void {
            $warehouse = Warehouse::factory()->create();

            AuditLog::create([
                'user_id' => User::factory()->create()->id,
                'accion' => 'almacen.creado',
                'auditable_type' => $warehouse::class,
                'auditable_id' => $warehouse->id,
            ]);
        });

        $this->getJson('/v1/audit-logs')->assertOk()->assertJsonPath('meta.total', $propios);
    }

    private function venta(User $usuario, Warehouse $almacen, Product $product, float $cantidad): void
    {
        $subtotal = round($product->precio_venta * $cantidad, 2);

        $sale = Sale::factory()->for($almacen)->for($usuario)->create(['total' => $subtotal]);

        $sale->items()->create([
            'product_id' => $product->id,
            'unit_id' => $product->baseProductUnit()->unit_id,
            'cantidad' => $cantidad,
            'cantidad_base' => $cantidad,
            'precio_venta_unit' => $product->precio_venta,
            'precio_compra_unit' => $product->precio_compra,
            'subtotal' => $subtotal,
        ]);
    }
}
