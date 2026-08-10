<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Access\Enums\Role;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos mínimos para arrancar: dos almacenes, un admin, un vendedor,
 * las unidades base y tres productos con stock. Idempotente.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $central = Warehouse::firstOrCreate(['nombre' => 'Almacén Central']);
        $norte = Warehouse::firstOrCreate(['nombre' => 'Almacén Norte']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@almacen.test'],
            ['name' => 'Administrador', 'password' => Hash::make('secreto123')],
        );
        $admin->syncRoles([Role::Admin->value]);

        $vendedor = User::firstOrCreate(
            ['email' => 'vendedor@almacen.test'],
            ['name' => 'Vendedor Central', 'password' => Hash::make('secreto123'), 'warehouse_id' => $central->id],
        );
        $vendedor->syncRoles([Role::Vendedor->value]);

        $unidad = Unit::firstOrCreate(['nombre' => 'unidad'], ['factor' => 1]);
        $caja = Unit::firstOrCreate(['nombre' => 'caja'], ['factor' => 24]);

        $catalogo = [
            ['nombre' => 'Agua 1L', 'precio_compra' => 0.35, 'precio_venta' => 0.90, 'stock' => 480, 'minimo' => 100],
            ['nombre' => 'Refresco cola 33cl', 'precio_compra' => 0.40, 'precio_venta' => 1.10, 'stock' => 240, 'minimo' => 50],
            ['nombre' => 'Vino tinto crianza', 'precio_compra' => 5.20, 'precio_venta' => 12.90, 'stock' => 36, 'minimo' => 40],
        ];

        foreach ($catalogo as $fila) {
            $product = Product::firstOrCreate(
                ['nombre' => $fila['nombre']],
                ['precio_compra' => $fila['precio_compra'], 'precio_venta' => $fila['precio_venta']],
            );

            $product->units()->firstOrCreate(['unit_id' => $unidad->id], ['is_base' => true]);
            $product->units()->firstOrCreate(['unit_id' => $caja->id], ['is_base' => false]);

            Stock::firstOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $central->id],
                ['cantidad' => $fila['stock'], 'minimo' => $fila['minimo']],
            );
            Stock::firstOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $norte->id],
                ['cantidad' => 0, 'minimo' => $fila['minimo']],
            );
        }
    }
}
