<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Access\Enums\Role;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Models\Currency;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Sales\Actions\RegisterSale;
use App\Modules\Sales\Models\Sale;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Support\CurrentCompany;
use App\Modules\Warehouses\Actions\SetProductStock;
use App\Modules\Warehouses\Actions\TransferStock;
use App\Modules\Warehouses\Models\Warehouse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dataset de demo completo: almacenes, usuarios (admin + dos vendedores),
 * catálogo con stock, ventas repartidas en el tiempo, transferencias y
 * auditoría. Idempotente. Ver
 * docs/superpowers/specs/2026-08-13-demoseeder-completo-design.md
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        // Todo lo que sigue cuelga de una empresa: fijarla como contexto hace
        // que `BelongsToCompany` rellene el `company_id` de cada fila y que las
        // consultas idempotentes (`firstOrCreate`) miren solo dentro de ella.
        $this->seedEmpresa();

        $this->call(CurrenciesSeeder::class);

        [$central, $norte] = $this->seedAlmacenes();
        [$admin, $vendedorCentral, $vendedorNorte] = $this->seedUsuarios($central, $norte);
        [$unidad, $caja] = $this->seedUnidades();

        // El catálogo se crea vía Actions (auditadas) y `Product::create` no es
        // idempotente: se guarda tras comprobar que no hay productos aún.
        if (Product::count() === 0) {
            $this->seedCatalogo($admin, $central, $norte, $unidad, $caja);
        }

        // Ventas y transferencias: `RegisterSale`/`TransferStock` no son
        // idempotentes (crean fila y mueven stock), así que se siembran solo si
        // aún no hay ventas.
        if (Sale::count() === 0) {
            $this->seedMovimientos($admin, $vendedorCentral, $vendedorNorte, $central, $norte, $unidad, $caja);
        }
    }

    private function seedEmpresa(): void
    {
        $company = Company::firstOrCreate(['nombre' => 'Negocio Demo']);

        app(CurrentCompany::class)->set($company);
    }

    /** @return array{0: Warehouse, 1: Warehouse} */
    private function seedAlmacenes(): array
    {
        return [
            Warehouse::firstOrCreate(['nombre' => 'Almacén Central']),
            Warehouse::firstOrCreate(['nombre' => 'Almacén Norte']),
        ];
    }

    /** @return array{0: User, 1: User, 2: User} */
    private function seedUsuarios(Warehouse $central, Warehouse $norte): array
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@almacen.test'],
            ['name' => 'Administrador', 'password' => Hash::make('secreto123')],
        );
        $admin->syncRoles([Role::Admin->value]);

        $vendedorCentral = User::firstOrCreate(
            ['email' => 'vendedor@almacen.test'],
            ['name' => 'Vendedor Central', 'password' => Hash::make('secreto123'), 'warehouse_id' => $central->id],
        );
        $vendedorCentral->syncRoles([Role::Vendedor->value]);

        $vendedorNorte = User::firstOrCreate(
            ['email' => 'vendedor.norte@almacen.test'],
            ['name' => 'Vendedor Norte', 'password' => Hash::make('secreto123'), 'warehouse_id' => $norte->id],
        );
        $vendedorNorte->syncRoles([Role::Vendedor->value]);

        return [$admin, $vendedorCentral, $vendedorNorte];
    }

    /** @return array{0: Unit, 1: Unit} */
    private function seedUnidades(): array
    {
        return [
            Unit::firstOrCreate(['nombre' => 'unidad'], ['factor' => 1]),
            Unit::firstOrCreate(['nombre' => 'caja'], ['factor' => 24]),
        ];
    }

    private function seedCatalogo(User $admin, Warehouse $central, Warehouse $norte, Unit $unidad, Unit $caja): void
    {
        $createProduct = app(CreateProduct::class);
        $setStock = app(SetProductStock::class);

        $monedas = Currency::pluck('id', 'codigo');

        // El catálogo mezcla las dos monedas a propósito: lo local se vende en
        // CUP y lo importado en USD, que es como opera el negocio. Los precios
        // en USD son de un orden de magnitud distinto — no son un error.
        //
        // stock: [almacén => [cantidad, minimo]]. El Ron queda bajo mínimo en
        // Central (36 < 40) a propósito, para las métricas de inventario.
        $catalogo = [
            ['nombre' => 'Agua 1L',              'moneda' => 'CUP', 'compra' => 60,   'venta' => 150,  'central' => [480, 100], 'norte' => [200, 50]],
            ['nombre' => 'Refresco cola 33cl',   'moneda' => 'CUP', 'compra' => 90,   'venta' => 220,  'central' => [240, 50],  'norte' => [120, 50]],
            ['nombre' => 'Ron añejo 0.7L',       'moneda' => 'USD', 'compra' => 4.50, 'venta' => 9.00, 'central' => [36, 40],   'norte' => [20, 40]],
            ['nombre' => 'Cerveza rubia 33cl',   'moneda' => 'CUP', 'compra' => 110,  'venta' => 250,  'central' => [600, 100], 'norte' => [300, 100]],
            ['nombre' => 'Jugo de naranja 1L',   'moneda' => 'CUP', 'compra' => 130,  'venta' => 300,  'central' => [150, 40],  'norte' => [60, 40]],
            ['nombre' => 'Aceite girasol 1L',    'moneda' => 'USD', 'compra' => 2.10, 'venta' => 4.50, 'central' => [90, 30],   'norte' => [30, 30]],
            ['nombre' => 'Leche en polvo 1kg',   'moneda' => 'USD', 'compra' => 5.00, 'venta' => 9.50, 'central' => [300, 80],  'norte' => [100, 80]],
            ['nombre' => 'Café molido 250g',     'moneda' => 'CUP', 'compra' => 350,  'venta' => 700,  'central' => [120, 50],  'norte' => [40, 50]],
        ];

        foreach ($catalogo as $fila) {
            $product = $createProduct->handle(
                $admin,
                [
                    'nombre' => $fila['nombre'],
                    'precio_compra' => $fila['compra'],
                    'precio_venta' => $fila['venta'],
                    'currency_id' => $monedas[$fila['moneda']],
                ],
                $unidad->id,
            );

            // CreateProduct solo engancha la unidad base; añadimos la caja.
            $product->units()->firstOrCreate(['unit_id' => $caja->id], ['is_base' => false]);

            // Stock (con mínimo) en ambos almacenes, vía la Action auditada.
            $setStock->handle($admin, $product, $central->id, (float) $fila['central'][0], (float) $fila['central'][1]);
            $setStock->handle($admin, $product, $norte->id, (float) $fila['norte'][0], (float) $fila['norte'][1]);
        }
    }

    private function seedMovimientos(
        User $admin,
        User $vendedorCentral,
        User $vendedorNorte,
        Warehouse $central,
        Warehouse $norte,
        Unit $unidad,
        Unit $caja,
    ): void {
        $registerSale = app(RegisterSale::class);
        $transfer = app(TransferStock::class);

        $id = fn (string $nombre): int => Product::where('nombre', $nombre)->value('id');
        $now = CarbonImmutable::now();

        // [usuario, almacén, fecha, items]. Las fechas cubren hoy (varias
        // horas), esta semana, la anterior, este mes y el anterior, para que
        // serie/comparativa/top/por-vendedor tengan datos en daily/weekly/monthly.
        $ventas = [
            [$vendedorCentral, $central, $now->subHours(1),  [['product_id' => $id('Agua 1L'), 'cantidad' => 12]]],
            [$vendedorCentral, $central, $now->subHours(3),  [['product_id' => $id('Refresco cola 33cl'), 'cantidad' => 6], ['product_id' => $id('Cerveza rubia 33cl'), 'cantidad' => 24]]],
            [$vendedorNorte,   $norte,   $now->subHours(5),  [['product_id' => $id('Jugo de naranja 1L'), 'cantidad' => 10]]],
            [$vendedorCentral, $central, $now->subDays(1),   [['product_id' => $id('Leche en polvo 1kg'), 'cantidad' => 20]]],
            [$vendedorNorte,   $norte,   $now->subDays(2),   [['product_id' => $id('Café molido 250g'), 'cantidad' => 5]]],
            [$admin,           $central, $now->subDays(4),   [['product_id' => $id('Ron añejo 0.7L'), 'cantidad' => 2], ['product_id' => $id('Aceite girasol 1L'), 'cantidad' => 3]]],
            [$vendedorCentral, $central, $now->subDays(6),   [['product_id' => $id('Agua 1L'), 'cantidad' => 24], ['product_id' => $id('Refresco cola 33cl'), 'cantidad' => 12]]],
            [$vendedorNorte,   $norte,   $now->subDays(8),   [['product_id' => $id('Cerveza rubia 33cl'), 'cantidad' => 30]]],
            [$vendedorCentral, $central, $now->subDays(10),  [['product_id' => $id('Leche en polvo 1kg'), 'cantidad' => 15]]],
            [$admin,           $central, $now->subDays(20),  [['product_id' => $id('Aceite girasol 1L'), 'cantidad' => 5]]],
            [$vendedorCentral, $central, $now->subDays(35),  [['product_id' => $id('Agua 1L'), 'cantidad' => 50]]],
            [$vendedorNorte,   $norte,   $now->subDays(40),  [['product_id' => $id('Jugo de naranja 1L'), 'cantidad' => 20]]],
        ];

        foreach ($ventas as [$user, $warehouse, $fecha, $items]) {
            $sale = $registerSale->handle($user, $warehouse->id, $items);

            // RegisterSale fija created_at = now(); lo retro-fechamos con el
            // query builder para no re-tocar timestamps del modelo. Las métricas
            // agregan por sales.created_at, así que solo esa columna importa.
            Sale::whereKey($sale->id)->update(['created_at' => $fecha, 'updated_at' => $fecha]);
        }

        // Transferencias Central → Norte (auditan `transferencia.realizada`).
        $transfer->handle($admin, $id('Ron añejo 0.7L'), $central->id, $norte->id, 4);
        $transfer->handle($admin, $id('Aceite girasol 1L'), $central->id, $norte->id, 1, $caja->id);
        $transfer->handle($admin, $id('Agua 1L'), $central->id, $norte->id, 100);
    }
}
