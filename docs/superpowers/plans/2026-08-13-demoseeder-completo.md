# DemoSeeder completo — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ampliar `DemoSeeder` para que deje datos en todos los módulos (usuarios, catálogo, ventas retro-fechadas, transferencias, auditoría), de modo que ningún endpoint —incluidas las métricas— salga vacío.

**Architecture:** Un único seeder idempotente. Actores y unidades con `firstOrCreate`. Catálogo y stock creados **a través de las Actions reales** (`CreateProduct`, `SetProductStock`) para que la auditoría se llene de verdad. Ventas vía `RegisterSale` con `created_at` retro-fechado ~8 semanas para encender serie/comparativa/top/por-vendedor. Transferencias vía `TransferStock`. Bloques de catálogo y de movimientos protegidos por guardas de conteo para no duplicar al re-ejecutar.

**Tech Stack:** PHP 8.4, Laravel 13, PHPUnit (SQLite en memoria), spatie/laravel-permission, Carbon.

## Global Constraints

- **Reglas de dominio (no romper):** unidad base factor 1; stock en unidad base (`cantidad × unit.factor`); venta todo-o-nada; snapshot de `precio_venta`/`precio_compra` por línea; el vendedor nunca ve `precio_compra` ni derivados; vendedor atado a un almacén; auditoría explícita desde las Actions.
- **Sin nuevas migraciones, sin super-admin, sin multi-empresa.**
- **Contraseñas de demo:** `secreto123` (conocidas; entorno de pruebas no público).
- **Zona horaria:** `APP_TIMEZONE=Europe/Madrid` determina los cortes de las métricas; usar `CarbonImmutable::now()` (respeta la config).
- **Antes de cada commit:** `vendor/bin/pint --dirty` limpio y `php artisan test` en verde.
- **Idempotencia:** re-ejecutar el seeder no debe duplicar usuarios, productos, ventas ni agotar stock dos veces.

## File Structure

- Modify: `database/seeders/DemoSeeder.php` — orquestador `run()` + métodos privados `seedAlmacenes`, `seedUsuarios`, `seedUnidades`, `seedCatalogo`, `seedMovimientos`.
- Modify: `tests/Feature/DemoSeederTest.php` — amplía las aserciones (catálogo, bajo mínimo, auditoría, ventas repartidas, idempotencia de movimientos).

---

### Task 1: Actores, unidades y catálogo vía Actions

**Files:**
- Modify: `database/seeders/DemoSeeder.php`
- Test: `tests/Feature/DemoSeederTest.php`

**Interfaces:**
- Consumes:
  - `app(\App\Modules\Catalog\Actions\CreateProduct::class)->handle(User $user, array $datos, int $baseUnitId, ?array $stockInicial = null): Product` — `$datos` = `['nombre','precio_compra','precio_venta']`. Crea producto + unidad base + auditoría `producto.creado`. **Solo engancha la unidad base.**
  - `app(\App\Modules\Warehouses\Actions\SetProductStock::class)->handle(User $user, Product $product, int $warehouseId, float $cantidad, ?float $minimo = null): Stock` — fija cantidad (unidad base) + auditoría `stock.fijado`. Es quien fija `minimo`.
  - `Warehouse::firstOrCreate`, `User::firstOrCreate`, `Unit::firstOrCreate` (patrón ya presente en el seeder).
- Produces (para Task 2):
  - `run()` deja disponibles, tras ejecutarse: usuarios `admin@almacen.test`, `vendedor@almacen.test` (Central), `vendedor.norte@almacen.test` (Norte); unidades `unidad` (factor 1) y `caja` (factor 24); 8 productos con stock en ambos almacenes, uno de ellos (`Vino tinto crianza`) bajo mínimo en Central.
  - Métodos privados `seedAlmacenes(): array{0:Warehouse,1:Warehouse}`, `seedUsuarios(Warehouse $central, Warehouse $norte): array{0:User,1:User,2:User}`, `seedUnidades(): array{0:Unit,1:Unit}`, `seedCatalogo(User $admin, Warehouse $central, Warehouse $norte, Unit $unidad, Unit $caja): void`.

- [ ] **Step 1: Escribir el test que falla (catálogo y auditoría)**

Reemplaza el método `test_el_seeder_deja_una_base_usable` y añade imports. El fichero completo de test queda así (se ampliará en Task 2, aquí solo lo necesario para catálogo/actores):

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
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
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=DemoSeederTest`
Expected: FAIL (aún hay 3 productos, no 8; no existe `vendedor.norte`; product_units/stock counts distintos).

- [ ] **Step 3: Reescribir `DemoSeeder` (actores, unidades, catálogo)**

Reemplaza `database/seeders/DemoSeeder.php` por:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Access\Enums\Role;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Warehouses\Actions\SetProductStock;
use App\Modules\Warehouses\Models\Warehouse;
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

        [$central, $norte] = $this->seedAlmacenes();
        [$admin, $vendedorCentral, $vendedorNorte] = $this->seedUsuarios($central, $norte);
        [$unidad, $caja] = $this->seedUnidades();

        // El catálogo se crea vía Actions (auditadas) y `Product::create` no es
        // idempotente: se guarda tras comprobar que no hay productos aún.
        if (Product::count() === 0) {
            $this->seedCatalogo($admin, $central, $norte, $unidad, $caja);
        }
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

        // stock: [almacén => [cantidad, minimo]]. El Vino queda bajo mínimo en
        // Central (36 < 40) a propósito, para las métricas de inventario.
        $catalogo = [
            ['nombre' => 'Agua 1L',             'compra' => 0.35, 'venta' => 0.90, 'central' => [480, 100], 'norte' => [200, 50]],
            ['nombre' => 'Refresco cola 33cl',  'compra' => 0.40, 'venta' => 1.10, 'central' => [240, 50],  'norte' => [120, 50]],
            ['nombre' => 'Vino tinto crianza',  'compra' => 5.20, 'venta' => 12.90, 'central' => [36, 40],  'norte' => [20, 40]],
            ['nombre' => 'Cerveza rubia 33cl',  'compra' => 0.30, 'venta' => 0.95, 'central' => [600, 100], 'norte' => [300, 100]],
            ['nombre' => 'Zumo naranja 1L',     'compra' => 0.80, 'venta' => 1.80, 'central' => [150, 40],  'norte' => [60, 40]],
            ['nombre' => 'Aceite oliva 1L',     'compra' => 3.10, 'venta' => 6.50, 'central' => [90, 30],   'norte' => [30, 30]],
            ['nombre' => 'Leche entera 1L',     'compra' => 0.55, 'venta' => 1.05, 'central' => [300, 80],  'norte' => [100, 80]],
            ['nombre' => 'Café molido 250g',    'compra' => 1.90, 'venta' => 3.80, 'central' => [120, 50],  'norte' => [40, 50]],
        ];

        foreach ($catalogo as $fila) {
            $product = $createProduct->handle(
                $admin,
                ['nombre' => $fila['nombre'], 'precio_compra' => $fila['compra'], 'precio_venta' => $fila['venta']],
                $unidad->id,
            );

            // CreateProduct solo engancha la unidad base; añadimos la caja.
            $product->units()->firstOrCreate(['unit_id' => $caja->id], ['is_base' => false]);

            // Stock (con mínimo) en ambos almacenes, vía la Action auditada.
            $setStock->handle($admin, $product, $central->id, (float) $fila['central'][0], (float) $fila['central'][1]);
            $setStock->handle($admin, $product, $norte->id, (float) $fila['norte'][0], (float) $fila['norte'][1]);
        }
    }
}
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `php artisan test --filter=DemoSeederTest`
Expected: PASS (los 5 métodos de test).

- [ ] **Step 5: Formatear y commit**

```bash
vendor/bin/pint --dirty
git add database/seeders/DemoSeeder.php tests/Feature/DemoSeederTest.php
git commit -m "feat(seeder): actores, unidades y catálogo vía Actions con auditoría

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Movimientos — ventas retro-fechadas y transferencias

**Files:**
- Modify: `database/seeders/DemoSeeder.php`
- Test: `tests/Feature/DemoSeederTest.php`

**Interfaces:**
- Consumes:
  - `app(\App\Modules\Sales\Actions\RegisterSale::class)->handle(User $user, int $warehouseId, array $items): Sale` — `$items` = `list<array{product_id:int, unit_id?:int, cantidad:float}>`. Sin `unit_id` usa la unidad base. Descuenta stock y guarda snapshot de precios. Crea con `created_at = now()`.
  - `app(\App\Modules\Warehouses\Actions\TransferStock::class)->handle(User $user, int $productId, int $fromWarehouseId, int $toWarehouseId, float $cantidad, ?int $unitId = null): Transfer` — mueve stock y audita `transferencia.realizada`.
  - `\App\Modules\Sales\Models\Sale`, `\App\Modules\Warehouses\Models\Transfer`.
- Produces: `run()` con un segundo bloque guardado por `Sale::count() === 0` que crea ventas repartidas ~8 semanas y unas transferencias Central→Norte. Método privado `seedMovimientos(User $admin, User $vendedorCentral, User $vendedorNorte, Warehouse $central, Warehouse $norte, Unit $unidad, Unit $caja): void`.

- [ ] **Step 1: Escribir el test que falla (movimientos)**

Añade estos métodos a `tests/Feature/DemoSeederTest.php` (y los imports `use App\Modules\Sales\Models\Sale;` y `use App\Modules\Warehouses\Models\Transfer;`):

```php
    public function test_hay_ventas_repartidas_incluyendo_la_semana_actual(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertGreaterThanOrEqual(12, Sale::count());
        // Ventas de esta semana → las métricas semanales no salen vacías.
        $this->assertTrue(
            Sale::where('created_at', '>=', now()->startOfWeek(\Carbon\CarbonInterface::MONDAY))->exists()
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
```

- [ ] **Step 2: Correr el test y verificar que falla**

Run: `php artisan test --filter=DemoSeederTest`
Expected: FAIL (no hay ventas ni transferencias todavía).

- [ ] **Step 3: Añadir el bloque de movimientos al seeder**

En `DemoSeeder.php`, añade el import `use App\Modules\Sales\Actions\RegisterSale;`, `use App\Modules\Sales\Models\Sale;`, `use App\Modules\Warehouses\Actions\TransferStock;` y `use Carbon\CarbonImmutable;`.

Modifica `run()` para llamar al nuevo bloque tras el catálogo:

```php
        if (Product::count() === 0) {
            $this->seedCatalogo($admin, $central, $norte, $unidad, $caja);
        }

        // Ventas y transferencias: `RegisterSale`/`TransferStock` no son
        // idempotentes (crean fila y mueven stock), así que se siembran solo si
        // aún no hay ventas.
        if (Sale::count() === 0) {
            $this->seedMovimientos($admin, $vendedorCentral, $vendedorNorte, $central, $norte, $unidad, $caja);
        }
```

Añade el método privado. Resuelve los productos por nombre para no depender de IDs:

```php
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
            [$vendedorNorte,   $norte,   $now->subHours(5),  [['product_id' => $id('Zumo naranja 1L'), 'cantidad' => 10]]],
            [$vendedorCentral, $central, $now->subDays(1),   [['product_id' => $id('Leche entera 1L'), 'cantidad' => 20]]],
            [$vendedorNorte,   $norte,   $now->subDays(2),   [['product_id' => $id('Café molido 250g'), 'cantidad' => 5]]],
            [$admin,           $central, $now->subDays(4),   [['product_id' => $id('Vino tinto crianza'), 'cantidad' => 2], ['product_id' => $id('Aceite oliva 1L'), 'cantidad' => 3]]],
            [$vendedorCentral, $central, $now->subDays(6),   [['product_id' => $id('Agua 1L'), 'cantidad' => 24], ['product_id' => $id('Refresco cola 33cl'), 'cantidad' => 12]]],
            [$vendedorNorte,   $norte,   $now->subDays(8),   [['product_id' => $id('Cerveza rubia 33cl'), 'cantidad' => 30]]],
            [$vendedorCentral, $central, $now->subDays(10),  [['product_id' => $id('Leche entera 1L'), 'cantidad' => 15]]],
            [$admin,           $central, $now->subDays(20),  [['product_id' => $id('Aceite oliva 1L'), 'cantidad' => 5]]],
            [$vendedorCentral, $central, $now->subDays(35),  [['product_id' => $id('Agua 1L'), 'cantidad' => 50]]],
            [$vendedorNorte,   $norte,   $now->subDays(40),  [['product_id' => $id('Zumo naranja 1L'), 'cantidad' => 20]]],
        ];

        foreach ($ventas as [$user, $warehouse, $fecha, $items]) {
            $sale = $registerSale->handle($user, $warehouse->id, $items);

            // RegisterSale fija created_at = now(); lo retro-fechamos con el
            // query builder para no re-tocar timestamps del modelo. Las métricas
            // agregan por sales.created_at, así que solo esa columna importa.
            Sale::whereKey($sale->id)->update(['created_at' => $fecha, 'updated_at' => $fecha]);
        }

        // Transferencias Central → Norte (auditan `transferencia.realizada`).
        $transfer->handle($admin, $id('Vino tinto crianza'), $central->id, $norte->id, 4);
        $transfer->handle($admin, $id('Aceite oliva 1L'), $central->id, $norte->id, 1, $caja->id);
        $transfer->handle($admin, $id('Agua 1L'), $central->id, $norte->id, 100);
    }
```

- [ ] **Step 4: Correr el test y verificar que pasa**

Run: `php artisan test --filter=DemoSeederTest`
Expected: PASS (los 8 métodos de test).

- [ ] **Step 5: Formatear y commit**

```bash
vendor/bin/pint --dirty
git add database/seeders/DemoSeeder.php tests/Feature/DemoSeederTest.php
git commit -m "feat(seeder): ventas retro-fechadas y transferencias para métricas y auditoría

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Verificación local completa

**Files:** (ninguno nuevo; validación de extremo a extremo)

**Interfaces:**
- Consumes: la suite completa y el arranque de la API en local.

- [ ] **Step 1: Suite completa en verde**

Run: `php artisan test`
Expected: toda la suite PASS (no solo `DemoSeederTest`; confirma que las Actions siguen íntegras).

- [ ] **Step 2: Formato limpio en todo lo tocado**

Run: `vendor/bin/pint --test`
Expected: sin diferencias.

- [ ] **Step 3: Sembrar en una base local y comprobar métricas/auditoría**

Con la base local migrada (`php artisan migrate:fresh`), ejecutar:

```bash
php artisan db:seed --class="Database\Seeders\DemoSeeder"
```

Login como admin (`admin@almacen.test` / `secreto123`) y comprobar que devuelven datos no vacíos:
- `GET /v1/metrics/sales?period=weekly`
- `GET /v1/metrics/sales?period=daily`
- `GET /v1/metrics/sales?period=monthly`
- `GET /v1/metrics/inventory` (incluye productos bajo mínimo)
- `GET /v1/audit-logs` (acciones `producto.creado`, `stock.fijado`, `transferencia.realizada`)
- `GET /v1/transfers`

- [ ] **Step 4: Comprobar el recorte del vendedor**

Login como `vendedor@almacen.test` / `secreto123` y verificar:
- `GET /v1/products` → **no** aparece `precio_compra`.
- `GET /v1/metrics/sales?period=weekly` → **sin** `ganancia`, `top_productos`, `comparativa`.
- `GET /v1/metrics/sales?period=daily` → `403` (el vendedor solo puede `weekly`).
- `GET /v1/metrics/inventory` → `403` (requiere `metrics.full`).

No hay commit en esta tarea: es verificación. Si algo falla, es un bug a corregir antes de dar el plan por terminado.

---

## Notas de despliegue (fuera del plan de código)

Tras mergear, sembrar en el servidor y hacer el barrido de los 37 endpoints contra producción (admin y vendedor), corrigiendo lo que aparezca. Ya detectados y a tratar aparte (no dependen del seeder):
- `/api/up` → 500 al renderizar HTML (200 con `Accept: application/json`).
- `/api/docs.openapi` → 500 (falta `storage/app/scribe/openapi.yaml`).
