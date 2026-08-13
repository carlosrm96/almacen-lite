<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouses\Models\Stock;
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
}
