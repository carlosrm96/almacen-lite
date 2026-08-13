<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Transfer;
use App\Modules\Warehouses\Models\Warehouse;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_seeder_deja_una_base_usable(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(2, Warehouse::count());
        $this->assertTrue(User::where('email', 'admin@almacen.test')->first()->isAdmin());

        $central = User::where('email', 'vendedor@almacen.test')->first();
        $this->assertTrue($central->isVendedor());
        $this->assertNotNull($central->warehouse_id);

        $norte = User::where('email', 'vendedor.norte@almacen.test')->first();
        $this->assertTrue($norte->isVendedor());
        $this->assertNotSame($central->warehouse_id, $norte->warehouse_id);
    }

    public function test_el_catalogo_tiene_productos_con_stock_en_ambos_almacenes(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(8, Product::count());
        // Cada producto con las dos unidades (base + caja).
        $this->assertSame(8 * 2, \DB::table('product_units')->count());
        // Stock en los dos almacenes por producto.
        $this->assertSame(8 * 2, Stock::count());
    }

    public function test_hay_al_menos_un_producto_bajo_minimo(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertTrue(
            Stock::whereColumn('cantidad', '<', 'minimo')->exists(),
            'Se espera al menos un stock por debajo de su mínimo para las métricas de inventario.'
        );
    }

    public function test_la_auditoria_registra_altas_de_catalogo_y_stock(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertTrue(AuditLog::where('accion', 'producto.creado')->exists());
        $this->assertTrue(AuditLog::where('accion', 'stock.fijado')->exists());
    }

    public function test_el_seeder_es_idempotente(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@almacen.test')->count());
        $this->assertSame(2, Warehouse::count());
        $this->assertSame(8, Product::count());
    }

    public function test_hay_ventas_repartidas_incluyendo_la_semana_actual(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThanOrEqual(12, Sale::count());
        // Ventas recientes (últimos 7 días) → las métricas semanales no salen vacías.
        // Ventana rodante en vez de semana ISO estricta para no depender de la hora
        // exacta a la que corre la suite (cerca del corte del lunes).
        $this->assertTrue(
            Sale::where('created_at', '>=', now()->subDays(7))->exists()
        );
        // Más de un vendedor con ventas → ventas_por_vendedor con varias filas.
        $this->assertGreaterThanOrEqual(2, Sale::query()->distinct()->count('user_id'));
    }

    public function test_hay_transferencias_auditadas(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThanOrEqual(3, Transfer::count());
        $this->assertTrue(AuditLog::where('accion', 'transferencia.realizada')->exists());
    }

    public function test_los_movimientos_son_idempotentes(): void
    {
        $this->seed(DemoSeeder::class);
        $ventas = Sale::count();
        $transferencias = Transfer::count();

        $this->seed(DemoSeeder::class);

        $this->assertSame($ventas, Sale::count());
        $this->assertSame($transferencias, Transfer::count());
    }
}
