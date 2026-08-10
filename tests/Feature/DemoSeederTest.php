<?php

namespace Tests\Feature;

use App\Models\User;
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
        $this->assertTrue(User::where('email', 'vendedor@almacen.test')->first()->isVendedor());
        $this->assertNotNull(User::where('email', 'vendedor@almacen.test')->first()->warehouse_id);
        $this->assertGreaterThan(0, Product::count());
        $this->assertGreaterThan(0, Stock::count());
    }

    public function test_el_seeder_es_idempotente(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@almacen.test')->count());
        $this->assertSame(2, Warehouse::count());
    }
}
