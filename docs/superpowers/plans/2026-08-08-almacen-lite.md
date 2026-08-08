# almacen-lite — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir una API REST de gestión multi-almacén (productos con unidades, ventas con descuento de stock, transferencias, métricas y auditoría) como copia reducida de `almacen-backend`.

**Architecture:** Monolito modular Laravel 13 solo-API. Seis módulos en `app/Modules/<Modulo>/` (Access, Warehouses, Catalog, Sales, Metrics, Audit), cada uno con su `routes.php` agregado en `routes/api.php` bajo `/v1`. Controladores delgados, lógica en Actions, validación en Form Requests, salida siempre por API Resources, autorización con Policies y permisos de spatie. Catálogo global de productos + tabla `stocks` con la existencia por almacén.

**Tech Stack:** PHP 8.4 · Laravel ^13 · laravel/sanctum · spatie/laravel-permission · spatie/laravel-query-builder · PHPUnit 12 · Laravel Pint · Scribe. MySQL `127.0.0.1:3310` / esquema `almacen_lite` en desarrollo; SQLite en memoria en tests.

**Spec:** [`docs/superpowers/specs/2026-08-08-almacen-lite-design.md`](../specs/2026-08-08-almacen-lite-design.md)

## Global Constraints

- **Idioma del dominio en español**: nombres de tablas, columnas, campos JSON y valores de enum en español (`nombre`, `precio_compra`, `precio_venta`, `cantidad`, `minimo`, `accion`, `datos`). El código PHP (clases, métodos, variables) en inglés salvo esos nombres de dominio. Los comentarios y la documentación, en español.
- **Prefijo de rutas:** todas las rutas viven bajo `/v1` y se registran en el `routes.php` de su módulo. Nada en `routes/web.php`.
- **Sin multi-empresa.** No existe `company_id` en ninguna tabla ni `CurrentCompany`. Si aparece, es un error de copia desde `almacen-backend`.
- **Tipos decimales:** cantidades `decimal(14,3)`, precios e importes `decimal(12,2)`. En PHP se manejan como `float` y se comparan en los tests con `assertEqualsWithDelta(..., 0.001)` o comparando el string devuelto por la API.
- **Respuestas:** siempre API Resources, nunca modelos crudos. Colecciones paginadas con `spatie/laravel-query-builder`.
- **Validación:** siempre en Form Requests, nunca en el controlador.
- **Autorización:** siempre `$this->authorize(...)` en el controlador contra una Policy; los Form Requests devuelven `true` en `authorize()` salvo que se indique lo contrario.
- **Permisos** (fuente única: `RolesAndPermissionsSeeder`):
  - `admin`: `users.view|create|update|delete`, `warehouses.view|create|update|delete`, `units.view|create|update|delete`, `products.view|create|update|delete`, `stock.set`, `transfers.view`, `transfers.create`, `sales.view`, `sales.create`, `metrics.view`, `metrics.full`, `audit.view`
  - `vendedor`: `products.view`, `sales.view`, `sales.create`, `metrics.view`
- **Zona horaria:** `APP_TIMEZONE=Europe/Madrid` en `.env`, `.env.example` y `phpunit.xml`.
- **Cada tarea termina en verde:** `php artisan test` completo y `vendor/bin/pint --dirty` limpio antes del commit.
- **Convención de commits:** `tipo(ámbito): descripción` en español (`feat(sales): ...`, `test(catalog): ...`, `chore: ...`).

## Estructura de ficheros

```
app/
  Models/User.php                          usuario + rol + almacén asignado
  Modules/
    Access/                                autenticación, usuarios, roles
      Enums/Role.php
      Http/Controllers/{AuthController,UserController}.php
      Http/Middleware/ScopeToOwnWarehouse.php
      Http/Requests/{LoginRequest,StoreUserRequest,UpdateUserRequest}.php
      Http/Resources/UserResource.php
      Policies/UserPolicy.php
      routes.php
    Warehouses/                            almacenes, stock, transferencias
      Actions/{SetProductStock,TransferStock}.php
      Exceptions/InsufficientStockException.php
      Http/Controllers/{WarehouseController,ProductStockController,TransferController}.php
      Http/Requests/{StoreWarehouseRequest,UpdateWarehouseRequest,SetStockRequest,StoreTransferRequest}.php
      Http/Resources/{WarehouseResource,StockResource,TransferResource}.php
      Models/{Warehouse,Stock,Transfer}.php
      Policies/{WarehousePolicy,TransferPolicy}.php
      Providers/WarehousesServiceProvider.php
      routes.php
    Catalog/                               productos y unidades
      Actions/{CreateProduct,UpdateProduct,DeleteProduct}.php
      Http/Controllers/{UnitController,ProductController,ProductUnitController}.php
      Http/Requests/{StoreUnitRequest,UpdateUnitRequest,StoreProductRequest,UpdateProductRequest,StoreProductUnitRequest}.php
      Http/Resources/{UnitResource,ProductResource,ProductUnitResource}.php
      Models/{Unit,Product,ProductUnit}.php
      Policies/{UnitPolicy,ProductPolicy}.php
      Providers/CatalogServiceProvider.php
      routes.php
    Sales/                                 ventas
      Actions/RegisterSale.php
      Http/Controllers/SaleController.php
      Http/Requests/StoreSaleRequest.php
      Http/Resources/{SaleResource,SaleItemResource}.php
      Models/{Sale,SaleItem}.php
      Policies/SalePolicy.php
      Providers/SalesServiceProvider.php
      routes.php
    Metrics/                               métricas
      Enums/Period.php
      Http/Controllers/{SalesMetricsController,InventoryMetricsController}.php
      Http/Requests/SalesMetricsRequest.php
      Support/{SalesMetricsReporter,InventoryMetricsReporter,MetricsRoleFilter}.php
      routes.php
    Audit/                                 auditoría transversal
      Http/Controllers/AuditLogController.php
      Http/Resources/AuditLogResource.php
      Models/AuditLog.php
      Policies/AuditLogPolicy.php
      Providers/AuditServiceProvider.php
      Services/AuditLogger.php
      routes.php
database/
  factories/{UserFactory,WarehouseFactory,UnitFactory,ProductFactory,StockFactory,SaleFactory}.php
  migrations/                              una migración por tabla
  seeders/{DatabaseSeeder,RolesAndPermissionsSeeder,DemoSeeder}.php
routes/api.php                             agrega los routes.php de los módulos
tests/
  TestCase.php                             helper actingAsRole()
  Feature/{Access,Warehouses,Catalog,Sales,Metrics,Audit}/
```

Cada módulo es autónomo: su modelo, su validación, su autorización y sus rutas viven juntos. `Audit` no depende de nadie y todos pueden invocarlo; `Metrics` solo lee.

---

## Task 1: Esqueleto del proyecto

**Files:**
- Create: todo el esqueleto de Laravel en `/home/tati/PROJ/almacen-lite/`
- Modify: `bootstrap/app.php`, `phpunit.xml`, `.env`, `.env.example`, `composer.json`
- Create: `routes/api.php`, `app/Http/Controllers/Controller.php`
- Test: `tests/Feature/SmokeTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: proyecto Laravel arrancable; rutas API bajo `/v1` sin prefijo `api`; errores en JSON para `v1/*`; suite de tests sobre SQLite en memoria.

- [ ] **Step 1: Generar el esqueleto sin pisar el repo existente**

El directorio ya contiene `docs/` y `.git`, así que `create-project` no puede escribir directamente en él.

```bash
cd /home/tati/PROJ
composer create-project laravel/laravel /tmp/almacen-lite-skel "^13.0" --no-interaction
rsync -a --exclude='.git' /tmp/almacen-lite-skel/ /home/tati/PROJ/almacen-lite/
rm -rf /tmp/almacen-lite-skel
cd /home/tati/PROJ/almacen-lite
```

- [ ] **Step 2: Instalar las dependencias del proyecto**

```bash
composer require laravel/sanctum:^4.0 spatie/laravel-permission:^8.0 spatie/laravel-query-builder:^7.3 --no-interaction
composer require --dev knuckleswtf/scribe:^5.10 laravel/pint:^1.27 --no-interaction
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --no-interaction
php artisan vendor:publish --tag=scribe-config --no-interaction
php artisan install:api --no-interaction
```

- [ ] **Step 3: Configurar `bootstrap/app.php`**

Sin prefijo `api` (las rutas quedan como `v1/...`) y errores en JSON para todo lo que cuelgue de `v1/`.

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Sin prefijo `api`: las rutas quedan como `v1/...`.
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('v1/*'),
        );
    })->create();
```

- [ ] **Step 4: Crear `routes/api.php` vacío de módulos**

Cada tarea posterior añade una línea aquí.

```php
<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Los módulos se van registrando aquí:
    // require __DIR__.'/../app/Modules/Access/routes.php';
});
```

- [ ] **Step 5: Configurar `phpunit.xml`**

Reemplazar el bloque `<php>` por:

```xml
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="APP_TIMEZONE" value="Europe/Madrid"/>
        <env name="APP_MAINTENANCE_DRIVER" value="file"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_STORE" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="DB_URL" value=""/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
    </php>
```

- [ ] **Step 6: Configurar `.env` y `.env.example`**

Las dos claves que cambian respecto al esqueleto (en ambos ficheros; en `.env.example` deja `DB_PASSWORD=` vacío):

```dotenv
APP_NAME=almacen-lite
APP_TIMEZONE=Europe/Madrid

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3310
DB_DATABASE=almacen_lite
DB_USERNAME=root
DB_PASSWORD=
```

Crear el esquema y generar la clave:

```bash
php artisan key:generate
mysql -h 127.0.0.1 -P 3310 -u root -p -e "CREATE DATABASE IF NOT EXISTS almacen_lite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

- [ ] **Step 7: Escribir el test de humo**

`tests/Feature/SmokeTest.php`:

```php
<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_ruta_v1_inexistente_responde_json_y_no_html(): void
    {
        $this->getJson('/v1/no-existe')
            ->assertNotFound()
            ->assertHeader('content-type', 'application/json');
    }

    public function test_el_healthcheck_responde(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_la_zona_horaria_de_la_aplicacion_es_madrid(): void
    {
        $this->assertSame('Europe/Madrid', config('app.timezone'));
    }
}
```

- [ ] **Step 8: Ejecutar los tests**

Run: `php artisan test`
Expected: PASS (3 tests). Si `test_la_zona_horaria...` falla, falta `APP_TIMEZONE` en `phpunit.xml`.

- [ ] **Step 9: Añadir `.gitignore` de docs generada y commit**

Añadir al final de `.gitignore`:

```gitignore
/.scribe
/public/vendor/scribe
/resources/views/scribe
```

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "chore: esqueleto Laravel 13 solo-API con sanctum, permission y query-builder"
```

---

## Task 2: Access — roles, permisos y autenticación

**Files:**
- Create: `app/Modules/Access/Enums/Role.php`, `app/Modules/Access/Http/Controllers/AuthController.php`, `app/Modules/Access/Http/Requests/LoginRequest.php`, `app/Modules/Access/Http/Resources/UserResource.php`, `app/Modules/Access/routes.php`
- Create: `database/seeders/RolesAndPermissionsSeeder.php`
- Modify: `app/Models/User.php`, `routes/api.php`, `database/seeders/DatabaseSeeder.php`, `tests/TestCase.php`
- Test: `tests/Feature/Access/AuthTest.php`

**Interfaces:**
- Consumes: esqueleto de la Task 1.
- Produces:
  - `App\Modules\Access\Enums\Role` con `Role::Admin` (`'admin'`) y `Role::Vendedor` (`'vendedor'`).
  - `App\Models\User::isAdmin(): bool` y `User::isVendedor(): bool`.
  - `Database\Seeders\RolesAndPermissionsSeeder` — fuente única de roles y permisos.
  - `Tests\TestCase::actingAsRole(string $role, ?Warehouse $warehouse = null): User` — siembra roles, crea el usuario, lo autentica por Sanctum y lo devuelve.
  - `POST /v1/login`, `POST /v1/logout`, `GET /v1/me`.

- [ ] **Step 1: Escribir los tests de autenticación**

`tests/Feature/Access/AuthTest.php`:

```php
<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_con_credenciales_correctas_devuelve_token(): void
    {
        User::factory()->create([
            'email' => 'admin@almacen.test',
            'password' => Hash::make('secreto123'),
        ]);

        $response = $this->postJson('/v1/login', [
            'email' => 'admin@almacen.test',
            'password' => 'secreto123',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'rol']]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_con_password_incorrecta_devuelve_422(): void
    {
        User::factory()->create([
            'email' => 'admin@almacen.test',
            'password' => Hash::make('secreto123'),
        ]);

        $this->postJson('/v1/login', [
            'email' => 'admin@almacen.test',
            'password' => 'incorrecta',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_me_devuelve_el_usuario_autenticado_con_su_rol(): void
    {
        $admin = $this->actingAsRole('admin');

        $this->getJson('/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.rol', 'admin');
    }

    public function test_me_sin_token_devuelve_401(): void
    {
        $this->getJson('/v1/me')->assertUnauthorized();
    }

    public function test_logout_revoca_el_token_actual(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secreto123')]);
        $token = $this->postJson('/v1/login', [
            'email' => $user->email,
            'password' => 'secreto123',
        ])->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/v1/logout')->assertNoContent();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/v1/me')->assertUnauthorized();
    }

    public function test_el_seeder_crea_los_dos_roles_con_sus_permisos(): void
    {
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);

        $vendedor = \Spatie\Permission\Models\Role::findByName('vendedor');

        $this->assertTrue($vendedor->hasPermissionTo('sales.create'));
        $this->assertFalse($vendedor->hasPermissionTo('products.create'));
        $this->assertTrue(\Spatie\Permission\Models\Role::findByName('admin')->hasPermissionTo('metrics.full'));
    }
}
```

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

Run: `php artisan test --filter=AuthTest`
Expected: FAIL — `actingAsRole` no existe y las rutas devuelven 404.

- [ ] **Step 3: Crear el enum de roles**

`app/Modules/Access/Enums/Role.php`:

```php
<?php

namespace App\Modules\Access\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Vendedor = 'vendedor';
}
```

- [ ] **Step 4: Preparar el modelo `User`**

`app/Models/User.php` — añadir el trait de spatie y los dos helpers de rol:

```php
<?php

namespace App\Models;

use App\Modules\Access\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin->value);
    }

    public function isVendedor(): bool
    {
        return $this->hasRole(Role::Vendedor->value);
    }
}
```

- [ ] **Step 5: Escribir el seeder de roles y permisos**

`database/seeders/RolesAndPermissionsSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Modules\Access\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permisos por rol. Fuente única de RBAC del proyecto.
     *
     * @var array<string, list<string>>
     */
    private const ROLE_PERMISSIONS = [
        RoleEnum::Admin->value => [
            'users.view', 'users.create', 'users.update', 'users.delete',
            'warehouses.view', 'warehouses.create', 'warehouses.update', 'warehouses.delete',
            'units.view', 'units.create', 'units.update', 'units.delete',
            'products.view', 'products.create', 'products.update', 'products.delete',
            'stock.set',
            'transfers.view', 'transfers.create',
            'sales.view', 'sales.create',
            'metrics.view', 'metrics.full',
            'audit.view',
        ],
        RoleEnum::Vendedor->value => [
            'products.view',
            'sales.view', 'sales.create',
            'metrics.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            foreach ($permissions as $permission) {
                Permission::findOrCreate($permission, 'web');
            }

            Role::findOrCreate($roleName, 'web')->syncPermissions($permissions);
        }
    }
}
```

Registrarlo en `database/seeders/DatabaseSeeder.php`:

```php
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
    }
```

- [ ] **Step 6: Escribir el `LoginRequest`, el `UserResource` y el `AuthController`**

`app/Modules/Access/Http/Requests/LoginRequest.php`:

```php
<?php

namespace App\Modules\Access\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
```

`app/Modules/Access/Http/Resources/UserResource.php`:

```php
<?php

namespace App\Modules\Access\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'rol' => $this->getRoleNames()->first(),
            'created_at' => $this->created_at,
        ];
    }
}
```

`app/Modules/Access/Http/Controllers/AuthController.php`:

```php
<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Access\Http\Requests\LoginRequest;
use App\Modules\Access\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * @group Acceso · Autenticación
 */
class AuthController extends Controller
{
    /**
     * Iniciar sesión.
     *
     * Devuelve un token de Sanctum para las siguientes peticiones.
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        return new JsonResponse([
            'token' => $user->createToken('api')->plainTextToken,
            'user' => (new UserResource($user))->resolve($request),
        ]);
    }

    /**
     * Cerrar sesión (revoca el token en uso).
     *
     * @authenticated
     */
    public function logout(): Response
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    /**
     * Usuario autenticado.
     *
     * @authenticated
     */
    public function me(): UserResource
    {
        return new UserResource(auth()->user());
    }
}
```

- [ ] **Step 7: Registrar las rutas del módulo**

`app/Modules/Access/routes.php`:

```php
<?php

use App\Modules\Access\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
});
```

En `routes/api.php`, dentro del grupo `v1`:

```php
    require __DIR__.'/../app/Modules/Access/routes.php';
```

- [ ] **Step 8: Añadir el helper `actingAsRole` al `TestCase`**

`tests/TestCase.php`:

```php
<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Siembra roles y permisos, crea un usuario con el rol indicado
     * (asignándole almacén si se pasa) y lo autentica vía Sanctum.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function actingAsRole(string $role, array $attributes = []): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }
}
```

- [ ] **Step 9: Ejecutar los tests**

Run: `php artisan test --filter=AuthTest`
Expected: PASS (6 tests).

Run: `php artisan test`
Expected: PASS (toda la suite).

- [ ] **Step 10: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat(access): roles admin/vendedor, permisos y autenticación por token"
```

---

## Task 3: Almacenes

**Files:**
- Create: `app/Modules/Warehouses/Models/Warehouse.php`, `app/Modules/Warehouses/Policies/WarehousePolicy.php`, `app/Modules/Warehouses/Providers/WarehousesServiceProvider.php`, `app/Modules/Warehouses/Http/Controllers/WarehouseController.php`, `app/Modules/Warehouses/Http/Requests/{StoreWarehouseRequest,UpdateWarehouseRequest}.php`, `app/Modules/Warehouses/Http/Resources/WarehouseResource.php`, `app/Modules/Warehouses/routes.php`
- Create: `database/migrations/xxxx_create_warehouses_table.php`, `database/factories/WarehouseFactory.php`
- Modify: `routes/api.php`, `bootstrap/providers.php`
- Test: `tests/Feature/Warehouses/WarehouseManagementTest.php`

**Interfaces:**
- Consumes: `Tests\TestCase::actingAsRole()`, permisos `warehouses.*` de la Task 2.
- Produces: `App\Modules\Warehouses\Models\Warehouse` con `nombre` (string) y `activo` (bool); `WarehouseFactory`; endpoints `GET|POST /v1/warehouses`, `GET|PUT|DELETE /v1/warehouses/{warehouse}`.

- [ ] **Step 1: Escribir los tests**

`tests/Feature/Warehouses/WarehouseManagementTest.php`:

```php
<?php

namespace Tests\Feature\Warehouses;

use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_admin_puede_crear_un_almacen(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/v1/warehouses', ['nombre' => 'Almacén Central'])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Almacén Central')
            ->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('warehouses', ['nombre' => 'Almacén Central', 'activo' => true]);
    }

    public function test_el_nombre_del_almacen_es_obligatorio_y_unico(): void
    {
        $this->actingAsRole('admin');
        Warehouse::factory()->create(['nombre' => 'Central']);

        $this->postJson('/v1/warehouses', [])->assertStatus(422)->assertJsonValidationErrors('nombre');
        $this->postJson('/v1/warehouses', ['nombre' => 'Central'])->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    public function test_el_admin_puede_listar_ver_actualizar_y_borrar_almacenes(): void
    {
        $this->actingAsRole('admin');
        $warehouse = Warehouse::factory()->create(['nombre' => 'Norte']);

        $this->getJson('/v1/warehouses')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/v1/warehouses/{$warehouse->id}")->assertOk()->assertJsonPath('data.nombre', 'Norte');

        $this->putJson("/v1/warehouses/{$warehouse->id}", ['nombre' => 'Sur', 'activo' => false])
            ->assertOk()->assertJsonPath('data.nombre', 'Sur')->assertJsonPath('data.activo', false);

        $this->deleteJson("/v1/warehouses/{$warehouse->id}")->assertNoContent();
        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
    }

    public function test_el_vendedor_no_puede_gestionar_almacenes(): void
    {
        $this->actingAsRole('vendedor');
        $warehouse = Warehouse::factory()->create();

        $this->postJson('/v1/warehouses', ['nombre' => 'X'])->assertForbidden();
        $this->putJson("/v1/warehouses/{$warehouse->id}", ['nombre' => 'X'])->assertForbidden();
        $this->deleteJson("/v1/warehouses/{$warehouse->id}")->assertForbidden();
        $this->getJson('/v1/warehouses')->assertForbidden();
    }

    public function test_sin_token_no_se_accede(): void
    {
        $this->getJson('/v1/warehouses')->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

Run: `php artisan test --filter=WarehouseManagementTest`
Expected: FAIL — la clase `Warehouse` no existe.

- [ ] **Step 3: Crear la migración, el modelo y la factory**

```bash
php artisan make:migration create_warehouses_table --no-interaction
```

Contenido del `up()`:

```php
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
```

`app/Modules/Warehouses/Models/Warehouse.php`:

```php
<?php

namespace App\Modules\Warehouses\Models;

use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    protected $fillable = ['nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    protected static function newFactory(): WarehouseFactory
    {
        return WarehouseFactory::new();
    }
}
```

`database/factories/WarehouseFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Warehouse> */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Almacén '.fake()->unique()->city(),
            'activo' => true,
        ];
    }
}
```

- [ ] **Step 4: Crear la Policy y el ServiceProvider del módulo**

`app/Modules/Warehouses/Policies/WarehousePolicy.php`:

```php
<?php

namespace App\Modules\Warehouses\Policies;

use App\Models\User;
use App\Modules\Warehouses\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('warehouses.view');
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouses.view');
    }

    public function create(User $user): bool
    {
        return $user->can('warehouses.create');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouses.update');
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->can('warehouses.delete');
    }
}
```

`app/Modules/Warehouses/Providers/WarehousesServiceProvider.php`:

```php
<?php

namespace App\Modules\Warehouses\Providers;

use App\Modules\Warehouses\Models\Warehouse;
use App\Modules\Warehouses\Policies\WarehousePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class WarehousesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Warehouse::class, WarehousePolicy::class);
    }
}
```

Registrarlo en `bootstrap/providers.php`:

```php
    App\Modules\Warehouses\Providers\WarehousesServiceProvider::class,
```

- [ ] **Step 5: Crear Form Requests, Resource y Controller**

`app/Modules/Warehouses/Http/Requests/StoreWarehouseRequest.php`:

```php
<?php

namespace App\Modules\Warehouses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255', 'unique:warehouses,nombre'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
```

`app/Modules/Warehouses/Http/Requests/UpdateWarehouseRequest.php` — idéntico salvo la regla `unique`, que ignora el registro actual:

```php
<?php

namespace App\Modules\Warehouses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:255', Rule::unique('warehouses', 'nombre')->ignore($this->route('warehouse'))],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
```

`app/Modules/Warehouses/Http/Resources/WarehouseResource.php`:

```php
<?php

namespace App\Modules\Warehouses\Http\Resources;

use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Warehouse */
class WarehouseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'activo' => $this->activo,
            'created_at' => $this->created_at,
        ];
    }
}
```

`app/Modules/Warehouses/Http/Controllers/WarehouseController.php`:

```php
<?php

namespace App\Modules\Warehouses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Warehouses\Http\Requests\StoreWarehouseRequest;
use App\Modules\Warehouses\Http\Requests\UpdateWarehouseRequest;
use App\Modules\Warehouses\Http\Resources\WarehouseResource;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Almacenes
 *
 * @authenticated
 */
class WarehouseController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Warehouse::class);

        $warehouses = QueryBuilder::for(Warehouse::class)
            ->allowedFilters(AllowedFilter::partial('nombre'), 'activo')
            ->allowedSorts('nombre', 'created_at')
            ->paginate()
            ->appends(request()->query());

        return WarehouseResource::collection($warehouses);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $this->authorize('create', Warehouse::class);

        $warehouse = Warehouse::create($request->validated());

        return (new WarehouseResource($warehouse))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Warehouse $warehouse): WarehouseResource
    {
        $this->authorize('view', $warehouse);

        return new WarehouseResource($warehouse);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        $this->authorize('update', $warehouse);

        $warehouse->update($request->validated());

        return new WarehouseResource($warehouse);
    }

    public function destroy(Warehouse $warehouse): Response
    {
        $this->authorize('delete', $warehouse);

        $warehouse->delete();

        return response()->noContent();
    }
}
```

- [ ] **Step 6: Registrar las rutas**

`app/Modules/Warehouses/routes.php`:

```php
<?php

use App\Modules\Warehouses\Http\Controllers\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('warehouses', WarehouseController::class);
});
```

Y en `routes/api.php`, tras la línea de Access:

```php
    require __DIR__.'/../app/Modules/Warehouses/routes.php';
```

- [ ] **Step 7: Ejecutar los tests**

Run: `php artisan test --filter=WarehouseManagementTest`
Expected: PASS (5 tests).

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat(warehouses): CRUD de almacenes restringido al admin"
```

---

## Task 4: Usuarios y alcance del vendedor

**Files:**
- Create: `app/Modules/Access/Http/Controllers/UserController.php`, `app/Modules/Access/Http/Requests/{StoreUserRequest,UpdateUserRequest}.php`, `app/Modules/Access/Policies/UserPolicy.php`, `app/Modules/Access/Providers/AccessServiceProvider.php`, `app/Modules/Access/Http/Middleware/ScopeToOwnWarehouse.php`
- Create: `database/migrations/xxxx_add_warehouse_id_to_users_table.php`
- Modify: `app/Models/User.php`, `app/Modules/Access/Http/Resources/UserResource.php`, `app/Modules/Access/routes.php`, `bootstrap/app.php`, `bootstrap/providers.php`
- Test: `tests/Feature/Access/UserManagementTest.php`

**Interfaces:**
- Consumes: `Warehouse` (Task 3), `Role`, permisos `users.*` (Task 2).
- Produces:
  - `User::$warehouse_id`, `User::warehouse(): BelongsTo`.
  - Middleware con alias `scope.warehouse` → `App\Modules\Access\Http\Middleware\ScopeToOwnWarehouse`: si el usuario es vendedor, **sobrescribe** `warehouse_id` en la petición con el suyo; si es admin, la deja intacta.
  - Endpoints `GET|POST /v1/users`, `GET|PUT|DELETE /v1/users/{user}`.

- [ ] **Step 1: Escribir los tests**

`tests/Feature/Access/UserManagementTest.php`:

```php
<?php

namespace Tests\Feature\Access;

use App\Models\User;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_admin_puede_crear_un_vendedor_asignado_a_un_almacen(): void
    {
        $this->actingAsRole('admin');
        $warehouse = Warehouse::factory()->create();

        $this->postJson('/v1/users', [
            'name' => 'Ana',
            'email' => 'ana@almacen.test',
            'password' => 'secreto123',
            'rol' => 'vendedor',
            'warehouse_id' => $warehouse->id,
        ])->assertCreated()
            ->assertJsonPath('data.rol', 'vendedor')
            ->assertJsonPath('data.warehouse_id', $warehouse->id);

        $this->assertTrue(User::where('email', 'ana@almacen.test')->first()->isVendedor());
    }

    public function test_un_vendedor_sin_almacen_es_rechazado(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/v1/users', [
            'name' => 'Ana',
            'email' => 'ana@almacen.test',
            'password' => 'secreto123',
            'rol' => 'vendedor',
        ])->assertStatus(422)->assertJsonValidationErrors('warehouse_id');
    }

    public function test_no_se_puede_quitar_el_almacen_a_un_vendedor_existente(): void
    {
        $this->actingAsRole('admin');
        $vendedor = User::factory()->create(['warehouse_id' => Warehouse::factory()->create()->id]);
        $vendedor->assignRole('vendedor');

        $this->putJson("/v1/users/{$vendedor->id}", ['warehouse_id' => null])
            ->assertStatus(422)->assertJsonValidationErrors('warehouse_id');
    }

    public function test_el_admin_no_necesita_almacen(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/v1/users', [
            'name' => 'Jefe',
            'email' => 'jefe@almacen.test',
            'password' => 'secreto123',
            'rol' => 'admin',
        ])->assertCreated()->assertJsonPath('data.warehouse_id', null);
    }

    public function test_el_vendedor_no_puede_crear_ni_listar_usuarios(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => Warehouse::factory()->create()->id]);

        $this->getJson('/v1/users')->assertForbidden();
        $this->postJson('/v1/users', [
            'name' => 'X', 'email' => 'x@x.test', 'password' => 'secreto123', 'rol' => 'vendedor',
        ])->assertForbidden();
    }

    public function test_no_existe_registro_publico(): void
    {
        $this->postJson('/v1/register', [
            'name' => 'X', 'email' => 'x@x.test', 'password' => 'secreto123',
        ])->assertNotFound();
    }

    public function test_el_admin_puede_actualizar_y_borrar_usuarios(): void
    {
        $admin = $this->actingAsRole('admin');
        $otro = User::factory()->create();
        $otro->assignRole('admin');

        $this->putJson("/v1/users/{$otro->id}", ['name' => 'Renombrado'])
            ->assertOk()->assertJsonPath('data.name', 'Renombrado');

        $this->deleteJson("/v1/users/{$otro->id}")->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $otro->id]);
    }

    public function test_un_admin_no_puede_borrarse_a_si_mismo(): void
    {
        $admin = $this->actingAsRole('admin');

        $this->deleteJson("/v1/users/{$admin->id}")->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
```

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

Run: `php artisan test --filter=UserManagementTest`
Expected: FAIL — la columna `warehouse_id` no existe y `/v1/users` devuelve 404.

- [ ] **Step 3: Migración y relación en el modelo**

```bash
php artisan make:migration add_warehouse_id_to_users_table --no-interaction
```

```php
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')->nullable()->after('email')
                ->constrained('warehouses')->nullOnDelete();
        });
```

En `app/Models/User.php` añadir `'warehouse_id'` a `$fillable` y la relación:

```php
    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
```

(con `use App\Modules\Warehouses\Models\Warehouse;` y `use Illuminate\Database\Eloquent\Relations\BelongsTo;`).

Añadir `'warehouse_id' => $this->warehouse_id,` al `UserResource`.

- [ ] **Step 4: Form Requests con la regla «vendedor ⇒ almacén»**

`app/Modules/Access/Http/Requests/StoreUserRequest.php`:

```php
<?php

namespace App\Modules\Access\Http\Requests;

use App\Modules\Access\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'rol' => ['required', Rule::enum(Role::class)],
            // Un vendedor sin almacén es un estado inválido (spec §5, regla 2).
            'warehouse_id' => [
                Rule::requiredIf(fn (): bool => $this->input('rol') === Role::Vendedor->value),
                'nullable', 'integer', 'exists:warehouses,id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'Un vendedor debe estar asignado a un almacén.',
        ];
    }
}
```

`app/Modules/Access/Http/Requests/UpdateUserRequest.php`:

```php
<?php

namespace App\Modules\Access\Http\Requests;

use App\Models\User;
use App\Modules\Access\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');
        $rolFinal = $this->input('rol', $target->getRoleNames()->first());

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target)],
            'password' => ['sometimes', 'string', 'min:8'],
            'rol' => ['sometimes', Rule::enum(Role::class)],
            'warehouse_id' => [
                Rule::requiredIf(fn (): bool => $rolFinal === Role::Vendedor->value),
                'nullable', 'integer', 'exists:warehouses,id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'Un vendedor debe estar asignado a un almacén.',
        ];
    }
}
```

- [ ] **Step 5: Policy, Controller y ServiceProvider**

`app/Modules/Access/Policies/UserPolicy.php`:

```php
<?php

namespace App\Modules\Access\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('users.update');
    }

    /** Nadie puede borrarse a sí mismo: dejaría el sistema sin su propio operador. */
    public function delete(User $user, User $target): bool
    {
        return $user->can('users.delete') && $user->id !== $target->id;
    }
}
```

`app/Modules/Access/Http/Controllers/UserController.php`:

```php
<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Access\Http\Requests\StoreUserRequest;
use App\Modules\Access\Http\Requests\UpdateUserRequest;
use App\Modules\Access\Http\Resources\UserResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Acceso · Usuarios
 *
 * @authenticated
 */
class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = QueryBuilder::for(User::class)
            ->allowedFilters(AllowedFilter::partial('name'), AllowedFilter::partial('email'), 'warehouse_id')
            ->allowedSorts('name', 'created_at')
            ->paginate()
            ->appends(request()->query());

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $user = User::create($request->safe()->except('rol'));
        $user->assignRole($request->validated('rol'));

        return (new UserResource($user))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->authorize('update', $user);

        $user->update($request->safe()->except('rol'));

        if ($request->has('rol')) {
            $user->syncRoles([$request->validated('rol')]);
        }

        return new UserResource($user->refresh());
    }

    public function destroy(User $user): Response
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->noContent();
    }
}
```

`app/Modules/Access/Providers/AccessServiceProvider.php`:

```php
<?php

namespace App\Modules\Access\Providers;

use App\Models\User;
use App\Modules\Access\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AccessServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
    }
}
```

Registrarlo en `bootstrap/providers.php`.

- [ ] **Step 6: Middleware `ScopeToOwnWarehouse`**

`app/Modules/Access/Http/Middleware/ScopeToOwnWarehouse.php`:

```php
<?php

namespace App\Modules\Access\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fuerza `warehouse_id` al almacén del vendedor.
 *
 * No rechaza la petición cuando llega otro almacén: lo sobrescribe. Así el
 * vendedor nunca puede operar fuera de su almacén, venga lo que venga en el
 * cuerpo o en la query string. El admin pasa sin cambios.
 */
class ScopeToOwnWarehouse
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isVendedor()) {
            $request->merge(['warehouse_id' => $user->warehouse_id]);
            $request->query->set('warehouse_id', (string) $user->warehouse_id);
        }

        return $next($request);
    }
}
```

Alias en `bootstrap/app.php`, dentro de `withMiddleware`:

```php
        $middleware->alias([
            'scope.warehouse' => \App\Modules\Access\Http\Middleware\ScopeToOwnWarehouse::class,
        ]);
```

- [ ] **Step 7: Añadir las rutas de usuarios**

En `app/Modules/Access/routes.php`, dentro del grupo `auth:sanctum`:

```php
    Route::apiResource('users', UserController::class);
```

(con `use App\Modules\Access\Http\Controllers\UserController;`).

- [ ] **Step 8: Ejecutar los tests**

Run: `php artisan test --filter=UserManagementTest`
Expected: PASS (8 tests).

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat(access): CRUD de usuarios y alcance del vendedor a su almacén"
```

---

## Task 5: Auditoría

**Files:**
- Create: `app/Modules/Audit/Models/AuditLog.php`, `app/Modules/Audit/Services/AuditLogger.php`, `app/Modules/Audit/Http/Controllers/AuditLogController.php`, `app/Modules/Audit/Http/Resources/AuditLogResource.php`, `app/Modules/Audit/Policies/AuditLogPolicy.php`, `app/Modules/Audit/Providers/AuditServiceProvider.php`, `app/Modules/Audit/routes.php`
- Create: `database/migrations/xxxx_create_audit_logs_table.php`
- Modify: `routes/api.php`, `bootstrap/providers.php`
- Test: `tests/Feature/Audit/AuditLogTest.php`

**Interfaces:**
- Consumes: `Warehouse` (Task 3), permiso `audit.view` (Task 2).
- Produces:
  - `App\Modules\Audit\Services\AuditLogger::log(User $user, string $accion, Model $auditable, ?int $warehouseId = null, array $datos = []): AuditLog`
  - Constantes de acción en `AuditLogger`: `ACCION_PRODUCTO_CREADO = 'producto.creado'`, `ACCION_PRODUCTO_ACTUALIZADO = 'producto.actualizado'`, `ACCION_PRODUCTO_ELIMINADO = 'producto.eliminado'`, `ACCION_STOCK_FIJADO = 'stock.fijado'`, `ACCION_TRANSFERENCIA = 'transferencia.realizada'`.
  - `GET /v1/audit-logs` con filtros `filter[user_id]`, `filter[accion]`, `filter[auditable_id]`, `filter[desde]`, `filter[hasta]`.

- [ ] **Step 1: Escribir los tests**

`tests/Feature/Audit/AuditLogTest.php`:

```php
<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_logger_guarda_usuario_accion_objeto_almacen_y_datos(): void
    {
        $admin = $this->actingAsRole('admin');
        $warehouse = Warehouse::factory()->create();

        $log = app(AuditLogger::class)->log(
            $admin,
            AuditLogger::ACCION_STOCK_FIJADO,
            $warehouse,
            $warehouse->id,
            ['anterior' => 10, 'nuevo' => 25],
        );

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'user_id' => $admin->id,
            'accion' => 'stock.fijado',
            'auditable_type' => Warehouse::class,
            'auditable_id' => $warehouse->id,
            'warehouse_id' => $warehouse->id,
        ]);
        $this->assertSame(['anterior' => 10, 'nuevo' => 25], $log->fresh()->datos);
    }

    public function test_el_admin_puede_listar_la_auditoria_mas_reciente_primero(): void
    {
        $admin = $this->actingAsRole('admin');
        $warehouse = Warehouse::factory()->create();

        app(AuditLogger::class)->log($admin, AuditLogger::ACCION_PRODUCTO_CREADO, $warehouse);
        app(AuditLogger::class)->log($admin, AuditLogger::ACCION_PRODUCTO_ELIMINADO, $warehouse);

        $this->getJson('/v1/audit-logs')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.accion', 'producto.eliminado');
    }

    public function test_la_auditoria_se_puede_filtrar_por_accion_y_por_usuario(): void
    {
        $admin = $this->actingAsRole('admin');
        $otro = User::factory()->create();
        $warehouse = Warehouse::factory()->create();

        app(AuditLogger::class)->log($admin, AuditLogger::ACCION_PRODUCTO_CREADO, $warehouse);
        app(AuditLogger::class)->log($otro, AuditLogger::ACCION_TRANSFERENCIA, $warehouse);

        $this->getJson('/v1/audit-logs?filter[accion]=transferencia.realizada')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $otro->id);

        $this->getJson("/v1/audit-logs?filter[user_id]={$admin->id}")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_la_auditoria_se_puede_filtrar_por_rango_de_fechas(): void
    {
        $admin = $this->actingAsRole('admin');
        $warehouse = Warehouse::factory()->create();

        $viejo = app(AuditLogger::class)->log($admin, AuditLogger::ACCION_PRODUCTO_CREADO, $warehouse);
        AuditLog::where('id', $viejo->id)->update(['created_at' => '2020-01-01 10:00:00']);
        app(AuditLogger::class)->log($admin, AuditLogger::ACCION_PRODUCTO_ACTUALIZADO, $warehouse);

        $this->getJson('/v1/audit-logs?filter[desde]=2024-01-01')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.accion', 'producto.actualizado');
    }

    public function test_el_vendedor_no_puede_ver_la_auditoria(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => Warehouse::factory()->create()->id]);

        $this->getJson('/v1/audit-logs')->assertForbidden();
    }
}
```

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

Run: `php artisan test --filter=AuditLogTest`
Expected: FAIL — `AuditLogger` no existe.

- [ ] **Step 3: Migración y modelo**

```bash
php artisan make:migration create_audit_logs_table --no-interaction
```

```php
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('accion');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->json('datos')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('accion');
            $table->index('created_at');
        });
```

`app/Modules/Audit/Models/AuditLog.php`:

```php
<?php

namespace App\Modules\Audit\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'accion', 'auditable_type', 'auditable_id', 'warehouse_id', 'datos',
    ];

    protected function casts(): array
    {
        return ['datos' => 'array', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

- [ ] **Step 4: Escribir el `AuditLogger`**

`app/Modules/Audit/Services/AuditLogger.php`:

```php
<?php

namespace App\Modules\Audit\Services;

use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Registro de auditoría de movimientos sobre productos y almacenes.
 *
 * Se invoca explícitamente desde las Actions (no como observer) para que el
 * rastro sea legible siguiendo el código.
 */
class AuditLogger
{
    public const ACCION_PRODUCTO_CREADO = 'producto.creado';

    public const ACCION_PRODUCTO_ACTUALIZADO = 'producto.actualizado';

    public const ACCION_PRODUCTO_ELIMINADO = 'producto.eliminado';

    public const ACCION_STOCK_FIJADO = 'stock.fijado';

    public const ACCION_TRANSFERENCIA = 'transferencia.realizada';

    /**
     * @param  array<string, mixed>  $datos
     */
    public function log(
        User $user,
        string $accion,
        Model $auditable,
        ?int $warehouseId = null,
        array $datos = [],
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $user->id,
            'accion' => $accion,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->getKey(),
            'warehouse_id' => $warehouseId,
            'datos' => $datos === [] ? null : $datos,
        ]);
    }
}
```

- [ ] **Step 5: Resource, Policy, Controller, Provider y rutas**

`app/Modules/Audit/Http/Resources/AuditLogResource.php`:

```php
<?php

namespace App\Modules\Audit\Http\Resources;

use App\Modules\Audit\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuditLog */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'usuario' => $this->whenLoaded('user', fn (): ?string => $this->user?->name),
            'accion' => $this->accion,
            'auditable_type' => class_basename($this->auditable_type),
            'auditable_id' => $this->auditable_id,
            'warehouse_id' => $this->warehouse_id,
            'datos' => $this->datos,
            'created_at' => $this->created_at,
        ];
    }
}
```

`app/Modules/Audit/Policies/AuditLogPolicy.php`:

```php
<?php

namespace App\Modules\Audit\Policies;

use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit.view');
    }
}
```

`app/Modules/Audit/Http/Controllers/AuditLogController.php`:

```php
<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Http\Resources\AuditLogResource;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Auditoría
 *
 * @authenticated
 */
class AuditLogController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = QueryBuilder::for(AuditLog::class)
            ->with('user')
            ->allowedFilters(
                'user_id',
                'accion',
                'auditable_id',
                AllowedFilter::callback('desde', fn (Builder $q, $value) => $q->where('created_at', '>=', $value)),
                AllowedFilter::callback('hasta', fn (Builder $q, $value) => $q->where('created_at', '<=', $value)),
            )
            ->defaultSort('-created_at')
            ->allowedSorts('created_at')
            ->paginate()
            ->appends(request()->query());

        return AuditLogResource::collection($logs);
    }
}
```

`app/Modules/Audit/Providers/AuditServiceProvider.php`:

```php
<?php

namespace App\Modules\Audit\Providers;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Audit\Policies\AuditLogPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
    }
}
```

Registrarlo en `bootstrap/providers.php`.

`app/Modules/Audit/routes.php`:

```php
<?php

use App\Modules\Audit\Http\Controllers\AuditLogController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('audit-logs', [AuditLogController::class, 'index']);
});
```

Y la línea correspondiente en `routes/api.php`.

- [ ] **Step 6: Ejecutar los tests**

Run: `php artisan test --filter=AuditLogTest`
Expected: PASS (5 tests).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat(audit): registro de auditoría con logger explícito y listado para el admin"
```

---

## Task 6: Unidades

**Files:**
- Create: `app/Modules/Catalog/Models/Unit.php`, `app/Modules/Catalog/Policies/UnitPolicy.php`, `app/Modules/Catalog/Providers/CatalogServiceProvider.php`, `app/Modules/Catalog/Http/Controllers/UnitController.php`, `app/Modules/Catalog/Http/Requests/{StoreUnitRequest,UpdateUnitRequest}.php`, `app/Modules/Catalog/Http/Resources/UnitResource.php`, `app/Modules/Catalog/routes.php`
- Create: `database/migrations/xxxx_create_units_table.php`, `database/factories/UnitFactory.php`
- Modify: `routes/api.php`, `bootstrap/providers.php`
- Test: `tests/Feature/Catalog/UnitManagementTest.php`

**Interfaces:**
- Consumes: permisos `units.*` (Task 2).
- Produces: `App\Modules\Catalog\Models\Unit` con `nombre` (string, único) y `factor` (float, > 0); `UnitFactory` con estado `base()` que fija `nombre = 'unidad'` y `factor = 1`; endpoints `GET|POST /v1/units`, `GET|PUT|DELETE /v1/units/{unit}`.

- [ ] **Step 1: Escribir los tests**

`tests/Feature/Catalog/UnitManagementTest.php`:

```php
<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Unit;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_admin_puede_crear_una_unidad_con_nombre_y_factor(): void
    {
        $this->actingAsRole('admin');

        $this->postJson('/v1/units', ['nombre' => 'caja', 'factor' => 24])
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'caja')
            ->assertJsonPath('data.factor', '24.000');

        $this->assertDatabaseHas('units', ['nombre' => 'caja']);
    }

    public function test_el_nombre_es_unico_y_el_factor_debe_ser_positivo(): void
    {
        $this->actingAsRole('admin');
        Unit::factory()->create(['nombre' => 'caja']);

        $this->postJson('/v1/units', ['nombre' => 'caja', 'factor' => 12])
            ->assertStatus(422)->assertJsonValidationErrors('nombre');

        $this->postJson('/v1/units', ['nombre' => 'palé', 'factor' => 0])
            ->assertStatus(422)->assertJsonValidationErrors('factor');

        $this->postJson('/v1/units', ['nombre' => 'palé', 'factor' => -3])
            ->assertStatus(422)->assertJsonValidationErrors('factor');
    }

    public function test_el_admin_puede_listar_actualizar_y_borrar_unidades(): void
    {
        $this->actingAsRole('admin');
        $unit = Unit::factory()->create(['nombre' => 'caja', 'factor' => 24]);

        $this->getJson('/v1/units')->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/v1/units/{$unit->id}", ['factor' => 12])
            ->assertOk()->assertJsonPath('data.factor', '12.000');

        $this->deleteJson("/v1/units/{$unit->id}")->assertNoContent();
        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
    }

    public function test_el_vendedor_no_puede_gestionar_unidades(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => Warehouse::factory()->create()->id]);
        $unit = Unit::factory()->create();

        $this->getJson('/v1/units')->assertForbidden();
        $this->postJson('/v1/units', ['nombre' => 'x', 'factor' => 2])->assertForbidden();
        $this->deleteJson("/v1/units/{$unit->id}")->assertForbidden();
    }
}
```

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

Run: `php artisan test --filter=UnitManagementTest`
Expected: FAIL — la clase `Unit` no existe.

- [ ] **Step 3: Migración, modelo y factory**

```bash
php artisan make:migration create_units_table --no-interaction
```

```php
        Schema::create('units', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->unique();
            $table->decimal('factor', 14, 3);
            $table->timestamps();
        });
```

`app/Modules/Catalog/Models/Unit.php`:

```php
<?php

namespace App\Modules\Catalog\Models;

use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    protected $fillable = ['nombre', 'factor'];

    protected function casts(): array
    {
        return ['factor' => 'float'];
    }

    public function esBase(): bool
    {
        return abs($this->factor - 1.0) < 0.0001;
    }

    protected static function newFactory(): UnitFactory
    {
        return UnitFactory::new();
    }
}
```

`database/factories/UnitFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Unit> */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->word(),
            'factor' => fake()->randomElement([6, 12, 24]),
        ];
    }

    /** Unidad base: factor 1. */
    public function base(): static
    {
        return $this->state(fn (): array => ['nombre' => 'unidad', 'factor' => 1]);
    }
}
```

- [ ] **Step 4: Policy, Provider, Requests, Resource y Controller**

`app/Modules/Catalog/Policies/UnitPolicy.php` — mismo patrón que `WarehousePolicy`, con los permisos `units.view|create|update|delete`:

```php
<?php

namespace App\Modules\Catalog\Policies;

use App\Models\User;
use App\Modules\Catalog\Models\Unit;

class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('units.view');
    }

    public function view(User $user, Unit $unit): bool
    {
        return $user->can('units.view');
    }

    public function create(User $user): bool
    {
        return $user->can('units.create');
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->can('units.update');
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->can('units.delete');
    }
}
```

`app/Modules/Catalog/Providers/CatalogServiceProvider.php`:

```php
<?php

namespace App\Modules\Catalog\Providers;

use App\Modules\Catalog\Models\Unit;
use App\Modules\Catalog\Policies\UnitPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class CatalogServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Unit::class, UnitPolicy::class);
    }
}
```

Registrarlo en `bootstrap/providers.php`.

`app/Modules/Catalog/Http/Requests/StoreUnitRequest.php`:

```php
<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', 'unique:units,nombre'],
            'factor' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
```

`app/Modules/Catalog/Http/Requests/UpdateUnitRequest.php`:

```php
<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:100', Rule::unique('units', 'nombre')->ignore($this->route('unit'))],
            'factor' => ['sometimes', 'numeric', 'gt:0'],
        ];
    }
}
```

`app/Modules/Catalog/Http/Resources/UnitResource.php`:

```php
<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Unit */
class UnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'factor' => number_format($this->factor, 3, '.', ''),
        ];
    }
}
```

`app/Modules/Catalog/Http/Controllers/UnitController.php` — mismo esqueleto que `WarehouseController` (index con QueryBuilder y filtro parcial por `nombre`, store 201, show, update, destroy 204), autorizando contra `Unit::class` y devolviendo `UnitResource`:

```php
<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\StoreUnitRequest;
use App\Modules\Catalog\Http\Requests\UpdateUnitRequest;
use App\Modules\Catalog\Http\Resources\UnitResource;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Catálogo · Unidades
 *
 * @authenticated
 */
class UnitController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Unit::class);

        $units = QueryBuilder::for(Unit::class)
            ->allowedFilters(AllowedFilter::partial('nombre'))
            ->allowedSorts('nombre', 'factor')
            ->paginate()
            ->appends(request()->query());

        return UnitResource::collection($units);
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        $this->authorize('create', Unit::class);

        $unit = Unit::create($request->validated());

        return (new UnitResource($unit))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Unit $unit): UnitResource
    {
        $this->authorize('view', $unit);

        return new UnitResource($unit);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): UnitResource
    {
        $this->authorize('update', $unit);

        $unit->update($request->validated());

        return new UnitResource($unit);
    }

    public function destroy(Unit $unit): Response
    {
        $this->authorize('delete', $unit);

        $unit->delete();

        return response()->noContent();
    }
}
```

- [ ] **Step 5: Rutas del módulo**

`app/Modules/Catalog/routes.php`:

```php
<?php

use App\Modules\Catalog\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('units', UnitController::class);
});
```

Y la línea correspondiente en `routes/api.php`.

- [ ] **Step 6: Ejecutar los tests**

Run: `php artisan test --filter=UnitManagementTest`
Expected: PASS (4 tests).

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat(catalog): CRUD de unidades con factor de conversión"
```

---

## Task 7: Productos, unidad base y auditoría del catálogo

**Files:**
- Create: `app/Modules/Catalog/Models/{Product,ProductUnit}.php`, `app/Modules/Catalog/Actions/{CreateProduct,UpdateProduct,DeleteProduct}.php`, `app/Modules/Catalog/Policies/ProductPolicy.php`, `app/Modules/Catalog/Http/Controllers/{ProductController,ProductUnitController}.php`, `app/Modules/Catalog/Http/Requests/{StoreProductRequest,UpdateProductRequest,StoreProductUnitRequest}.php`, `app/Modules/Catalog/Http/Resources/{ProductResource,ProductUnitResource}.php`
- Create: `database/migrations/xxxx_create_products_table.php`, `xxxx_create_product_units_table.php`, `database/factories/ProductFactory.php`
- Modify: `app/Modules/Catalog/routes.php`, `app/Modules/Catalog/Providers/CatalogServiceProvider.php`, `app/Modules/Catalog/Http/Controllers/UnitController.php` (guarda de borrado)
- Test: `tests/Feature/Catalog/ProductManagementTest.php`, `tests/Feature/Catalog/ProductUnitManagementTest.php`

**Interfaces:**
- Consumes: `Unit` (Task 6), `AuditLogger` (Task 5), permisos `products.*` (Task 2).
- Produces:
  - `App\Modules\Catalog\Models\Product`: `nombre`, `precio_compra`, `precio_venta`, soft delete; `units(): HasMany<ProductUnit>`, `baseProductUnit(): ?ProductUnit`, `toBase(float $cantidad, Unit $unit): float`.
  - `App\Modules\Catalog\Models\ProductUnit`: `product_id`, `unit_id`, `is_base`; `unit(): BelongsTo<Unit>`.
  - `CreateProduct::handle(User $user, array $datos, int $baseUnitId): Product`
  - `UpdateProduct::handle(User $user, Product $product, array $datos): Product`
  - `DeleteProduct::handle(User $user, Product $product): void`
  - Endpoints `GET|POST /v1/products`, `GET|PUT|DELETE /v1/products/{product}`, `POST /v1/products/{product}/units`, `DELETE /v1/products/{product}/units/{unit}`.
- **Nota para la Task 8:** `ProductResource` se escribe aquí con la rama de admin completa y una rama de vendedor **sin** `cantidad`; la Task 8 añade `cantidad` cuando exista la tabla `stocks`.

- [ ] **Step 1: Escribir los tests de productos**

`tests/Feature/Catalog/ProductManagementTest.php`:

```php
<?php

namespace Tests\Feature\Catalog;

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

        $log = \App\Modules\Audit\Models\AuditLog::where('accion', 'producto.actualizado')->firstOrFail();
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
        $product = Product::factory()->create();
        $base = Unit::factory()->base()->create();
        $this->actingAsRole('vendedor', ['warehouse_id' => Warehouse::factory()->create()->id]);

        $this->postJson('/v1/products', [
            'nombre' => 'X', 'precio_compra' => 1, 'precio_venta' => 2, 'base_unit_id' => $base->id,
        ])->assertForbidden();
        $this->putJson("/v1/products/{$product->id}", ['precio_venta' => 5])->assertForbidden();
        $this->deleteJson("/v1/products/{$product->id}")->assertForbidden();
    }
}
```

- [ ] **Step 2: Escribir los tests de unidades del producto**

`tests/Feature/Catalog/ProductUnitManagementTest.php`:

```php
<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductUnitManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_admin_asigna_una_unidad_adicional_al_producto(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();
        $caja = Unit::factory()->create(['nombre' => 'caja', 'factor' => 24]);

        $this->postJson("/v1/products/{$product->id}/units", ['unit_id' => $caja->id])
            ->assertCreated()
            ->assertJsonPath('data.unit_id', $caja->id)
            ->assertJsonPath('data.is_base', false);

        $this->assertDatabaseHas('product_units', [
            'product_id' => $product->id, 'unit_id' => $caja->id, 'is_base' => false,
        ]);
    }

    public function test_no_se_puede_asignar_dos_veces_la_misma_unidad(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();
        $caja = Unit::factory()->create(['factor' => 24]);

        $this->postJson("/v1/products/{$product->id}/units", ['unit_id' => $caja->id])->assertCreated();
        $this->postJson("/v1/products/{$product->id}/units", ['unit_id' => $caja->id])
            ->assertStatus(422)->assertJsonValidationErrors('unit_id');
    }

    public function test_no_se_puede_desasignar_la_unidad_base(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();
        $baseUnitId = $product->baseProductUnit()->unit_id;

        $this->deleteJson("/v1/products/{$product->id}/units/{$baseUnitId}")
            ->assertStatus(422);

        $this->assertDatabaseHas('product_units', ['product_id' => $product->id, 'unit_id' => $baseUnitId]);
    }

    public function test_el_admin_puede_desasignar_una_unidad_no_base(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();
        $caja = Unit::factory()->create(['factor' => 24]);
        $product->units()->create(['unit_id' => $caja->id, 'is_base' => false]);

        $this->deleteJson("/v1/products/{$product->id}/units/{$caja->id}")->assertNoContent();

        $this->assertDatabaseMissing('product_units', ['product_id' => $product->id, 'unit_id' => $caja->id]);
    }

    public function test_no_se_puede_borrar_una_unidad_asignada_a_un_producto(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();
        $caja = Unit::factory()->create(['factor' => 24]);
        $product->units()->create(['unit_id' => $caja->id, 'is_base' => false]);

        $this->deleteJson("/v1/units/{$caja->id}")->assertStatus(422);
        $this->assertDatabaseHas('units', ['id' => $caja->id]);
    }

    public function test_el_producto_convierte_a_unidad_base(): void
    {
        $product = Product::factory()->create();
        $caja = Unit::factory()->create(['factor' => 24]);
        $product->units()->create(['unit_id' => $caja->id, 'is_base' => false]);

        $this->assertEqualsWithDelta(48.0, $product->fresh()->load('units')->toBase(2, $caja), 0.001);
    }

    public function test_convertir_con_una_unidad_no_asignada_lanza_excepcion(): void
    {
        $product = Product::factory()->create();
        $ajena = Unit::factory()->create(['factor' => 24]);

        $this->expectException(\RuntimeException::class);
        $product->load('units')->toBase(2, $ajena);
    }

    public function test_el_vendedor_no_puede_asignar_unidades(): void
    {
        $product = Product::factory()->create();
        $caja = Unit::factory()->create(['factor' => 24]);
        $this->actingAsRole('vendedor', ['warehouse_id' => Warehouse::factory()->create()->id]);

        $this->postJson("/v1/products/{$product->id}/units", ['unit_id' => $caja->id])->assertForbidden();
    }
}
```

- [ ] **Step 3: Ejecutar los tests para verificar que fallan**

Run: `php artisan test --filter="ProductManagementTest|ProductUnitManagementTest"`
Expected: FAIL — la clase `Product` no existe.

- [ ] **Step 4: Migraciones, modelos y factory**

```bash
php artisan make:migration create_products_table --no-interaction
php artisan make:migration create_product_units_table --no-interaction
```

```php
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->decimal('precio_compra', 12, 2);
            $table->decimal('precio_venta', 12, 2);
            $table->softDeletes();
            $table->timestamps();

            $table->index('nombre');
        });
```

```php
        Schema::create('product_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->cascadeOnDelete();
            $table->boolean('is_base')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'unit_id']);
        });
```

`app/Modules/Catalog/Models/Product.php`:

```php
<?php

namespace App\Modules\Catalog\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['nombre', 'precio_compra', 'precio_venta'];

    protected function casts(): array
    {
        return ['precio_compra' => 'float', 'precio_venta' => 'float'];
    }

    /** @return HasMany<ProductUnit, $this> */
    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function baseProductUnit(): ?ProductUnit
    {
        return $this->units()->where('is_base', true)->first();
    }

    /**
     * Convierte una cantidad expresada en `$unit` a la unidad base del producto.
     *
     * @throws RuntimeException si la unidad no está asignada al producto
     */
    public function toBase(float $cantidad, Unit $unit): float
    {
        $asignada = $this->units->firstWhere('unit_id', $unit->id);

        if ($asignada === null) {
            throw new RuntimeException("La unidad {$unit->nombre} no está asignada al producto {$this->nombre}.");
        }

        return $cantidad * $unit->factor;
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
```

`app/Modules/Catalog/Models/ProductUnit.php`:

```php
<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    protected $fillable = ['product_id', 'unit_id', 'is_base'];

    protected function casts(): array
    {
        return ['is_base' => 'boolean'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
```

`database/factories/ProductFactory.php` — crea siempre el producto **con su unidad base**, para que las factories de los tests reflejen el invariante:

```php
<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(2, true),
            'precio_compra' => fake()->randomFloat(2, 1, 50),
            'precio_venta' => fake()->randomFloat(2, 51, 100),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            $base = Unit::firstWhere('factor', 1) ?? Unit::factory()->base()->create();

            $product->units()->create(['unit_id' => $base->id, 'is_base' => true]);
        });
    }
}
```

- [ ] **Step 5: Actions con auditoría**

`app/Modules/Catalog/Actions/CreateProduct.php`:

```php
<?php

namespace App\Modules\Catalog\Actions;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

class CreateProduct
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $datos  nombre, precio_compra, precio_venta
     */
    public function handle(User $user, array $datos, int $baseUnitId): Product
    {
        return DB::transaction(function () use ($user, $datos, $baseUnitId): Product {
            $product = Product::create($datos);
            $product->units()->create(['unit_id' => $baseUnitId, 'is_base' => true]);

            $this->audit->log($user, AuditLogger::ACCION_PRODUCTO_CREADO, $product, null, [
                'nombre' => $product->nombre,
                'precio_compra' => number_format($product->precio_compra, 2, '.', ''),
                'precio_venta' => number_format($product->precio_venta, 2, '.', ''),
            ]);

            return $product->load('units.unit');
        });
    }
}
```

`app/Modules/Catalog/Actions/UpdateProduct.php`:

```php
<?php

namespace App\Modules\Catalog\Actions;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

class UpdateProduct
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function handle(User $user, Product $product, array $datos): Product
    {
        return DB::transaction(function () use ($user, $product, $datos): Product {
            $cambios = [];

            foreach ($datos as $campo => $nuevo) {
                $antes = $product->getAttribute($campo);
                $formatea = fn (mixed $v): string => is_float($v) || is_numeric($v)
                    ? number_format((float) $v, 2, '.', '')
                    : (string) $v;

                if ($formatea($antes) !== $formatea($nuevo)) {
                    $cambios[$campo] = ['antes' => $formatea($antes), 'despues' => $formatea($nuevo)];
                }
            }

            $product->update($datos);

            if ($cambios !== []) {
                $this->audit->log($user, AuditLogger::ACCION_PRODUCTO_ACTUALIZADO, $product, null, $cambios);
            }

            return $product->refresh();
        });
    }
}
```

`app/Modules/Catalog/Actions/DeleteProduct.php`:

```php
<?php

namespace App\Modules\Catalog\Actions;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

class DeleteProduct
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Borrado lógico: el producto se marca como eliminado y queda constancia de quién lo hizo. */
    public function handle(User $user, Product $product): void
    {
        DB::transaction(function () use ($user, $product): void {
            $this->audit->log($user, AuditLogger::ACCION_PRODUCTO_ELIMINADO, $product, null, [
                'nombre' => $product->nombre,
            ]);

            $product->delete();
        });
    }
}
```

- [ ] **Step 6: Form Requests**

`app/Modules/Catalog/Http/Requests/StoreProductRequest.php` — la regla clave es que la unidad base tenga factor 1:

```php
<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'base_unit_id' => [
                'required', 'integer',
                // La unidad base es, por definición, la de factor 1 (spec §4.1).
                Rule::exists('units', 'id')->where('factor', 1),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'base_unit_id.exists' => 'La unidad base debe existir y tener factor 1.',
        ];
    }
}
```

`app/Modules/Catalog/Http/Requests/UpdateProductRequest.php`:

```php
<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:255'],
            'precio_compra' => ['sometimes', 'numeric', 'min:0'],
            'precio_venta' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
```

`app/Modules/Catalog/Http/Requests/StoreProductUnitRequest.php`:

```php
<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unit_id' => [
                'required', 'integer', 'exists:units,id',
                Rule::unique('product_units', 'unit_id')
                    ->where('product_id', $this->route('product')->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_id.unique' => 'Esa unidad ya está asignada al producto.',
        ];
    }
}
```

- [ ] **Step 7: Resources con ramas explícitas por rol**

`app/Modules/Catalog/Http/Resources/ProductUnitResource.php`:

```php
<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\ProductUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductUnit */
class ProductUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'nombre' => $this->whenLoaded('unit', fn (): string => $this->unit->nombre),
            'factor' => $this->whenLoaded('unit', fn (): string => number_format($this->unit->factor, 3, '.', '')),
            'is_base' => $this->is_base,
        ];
    }
}
```

`app/Modules/Catalog/Http/Resources/ProductResource.php` — **dos ramas completas**, no campos condicionales: así un campo nuevo nunca se filtra al vendedor por descuido.

```php
<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($request->user()?->isAdmin() === true) {
            return $this->paraAdmin();
        }

        return $this->paraVendedor();
    }

    /**
     * @return array<string, mixed>
     */
    private function paraAdmin(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'precio_compra' => number_format($this->precio_compra, 2, '.', ''),
            'precio_venta' => number_format($this->precio_venta, 2, '.', ''),
            'unidades' => ProductUnitResource::collection($this->whenLoaded('units')),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * El vendedor solo ve nombre, precio de venta y las unidades con las que
     * puede vender. Nunca el precio de compra ni nada derivado de él.
     *
     * @return array<string, mixed>
     */
    private function paraVendedor(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'precio_venta' => number_format($this->precio_venta, 2, '.', ''),
            'unidades' => ProductUnitResource::collection($this->whenLoaded('units')),
        ];
    }
}
```

- [ ] **Step 8: Policy, controladores y guarda de borrado de unidad**

`app/Modules/Catalog/Policies/ProductPolicy.php` — mismo patrón que `UnitPolicy` con los permisos `products.view|create|update|delete`:

```php
<?php

namespace App\Modules\Catalog\Policies;

use App\Models\User;
use App\Modules\Catalog\Models\Product;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(User $user, Product $product): bool
    {
        return $user->can('products.update');
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->can('products.delete');
    }
}
```

Registrar la policy en `CatalogServiceProvider::boot()`:

```php
        Gate::policy(Product::class, ProductPolicy::class);
```

`app/Modules/Catalog/Http/Controllers/ProductController.php`:

```php
<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Actions\DeleteProduct;
use App\Modules\Catalog\Actions\UpdateProduct;
use App\Modules\Catalog\Http\Requests\StoreProductRequest;
use App\Modules\Catalog\Http\Requests\UpdateProductRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Catálogo · Productos
 *
 * @authenticated
 */
class ProductController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        $products = QueryBuilder::for(Product::class)
            ->with('units.unit')
            ->allowedFilters(AllowedFilter::partial('nombre'))
            ->allowedSorts('nombre', 'precio_venta', 'created_at')
            ->paginate()
            ->appends(request()->query());

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request, CreateProduct $action): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = $action->handle(
            $request->user(),
            $request->safe()->only(['nombre', 'precio_compra', 'precio_venta']),
            (int) $request->validated('base_unit_id'),
        );

        return (new ProductResource($product))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $product): ProductResource
    {
        $this->authorize('view', $product);

        return new ProductResource($product->load('units.unit'));
    }

    public function update(UpdateProductRequest $request, Product $product, UpdateProduct $action): ProductResource
    {
        $this->authorize('update', $product);

        return new ProductResource($action->handle($request->user(), $product, $request->validated())->load('units.unit'));
    }

    public function destroy(Product $product, DeleteProduct $action): Response
    {
        $this->authorize('delete', $product);

        $action->handle(request()->user(), $product);

        return response()->noContent();
    }
}
```

`app/Modules/Catalog/Http/Controllers/ProductUnitController.php`:

```php
<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\StoreProductUnitRequest;
use App\Modules\Catalog\Http\Resources\ProductUnitResource;
use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * @group Catálogo · Unidades del producto
 *
 * @authenticated
 */
class ProductUnitController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreProductUnitRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $productUnit = $product->units()->create([
            'unit_id' => (int) $request->validated('unit_id'),
            'is_base' => false,
        ]);

        return (new ProductUnitResource($productUnit->load('unit')))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Product $product, int $unit): Response
    {
        $this->authorize('update', $product);

        $productUnit = $product->units()->where('unit_id', $unit)->firstOrFail();

        if ($productUnit->is_base) {
            throw ValidationException::withMessages([
                'unit' => ['No se puede desasignar la unidad base del producto.'],
            ]);
        }

        $productUnit->delete();

        return response()->noContent();
    }
}
```

Guarda de borrado en `UnitController::destroy()` — una unidad en uso no se borra:

```php
    public function destroy(Unit $unit): Response
    {
        $this->authorize('delete', $unit);

        if ($unit->productUnits()->exists()) {
            throw ValidationException::withMessages([
                'unit' => ['La unidad está asignada a algún producto y no se puede borrar.'],
            ]);
        }

        $unit->delete();

        return response()->noContent();
    }
```

Con `use Illuminate\Validation\ValidationException;` en el controlador y esta relación en `Unit`:

```php
    /** @return HasMany<ProductUnit, $this> */
    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }
```

- [ ] **Step 9: Rutas**

En `app/Modules/Catalog/routes.php`, dentro del grupo `auth:sanctum`:

```php
    Route::apiResource('products', ProductController::class);
    Route::post('products/{product}/units', [ProductUnitController::class, 'store']);
    Route::delete('products/{product}/units/{unit}', [ProductUnitController::class, 'destroy']);
```

(con los `use` de ambos controladores).

- [ ] **Step 10: Ejecutar los tests**

Run: `php artisan test --filter="ProductManagementTest|ProductUnitManagementTest"`
Expected: PASS (15 tests).

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 11: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat(catalog): productos con unidad base, conversión, borrado lógico y auditoría"
```

---

## Task 8: Stock por almacén

**Files:**
- Create: `app/Modules/Warehouses/Models/Stock.php`, `app/Modules/Warehouses/Actions/SetProductStock.php`, `app/Modules/Warehouses/Http/Controllers/ProductStockController.php`, `app/Modules/Warehouses/Http/Requests/SetStockRequest.php`, `app/Modules/Warehouses/Http/Resources/StockResource.php`
- Create: `database/migrations/xxxx_create_stocks_table.php`, `database/factories/StockFactory.php`
- Modify: `app/Modules/Catalog/Models/Product.php` (relación `stocks`), `app/Modules/Catalog/Http/Resources/ProductResource.php` (rama del vendedor con `cantidad`), `app/Modules/Catalog/Http/Requests/StoreProductRequest.php` y `app/Modules/Catalog/Actions/CreateProduct.php` (stock inicial opcional), `app/Modules/Warehouses/Http/Controllers/WarehouseController.php` (guarda de borrado), `app/Modules/Warehouses/routes.php`
- Test: `tests/Feature/Warehouses/StockManagementTest.php`

**Interfaces:**
- Consumes: `Product`, `Unit` (Task 7), `Warehouse` (Task 3), `AuditLogger` (Task 5).
- Produces:
  - `App\Modules\Warehouses\Models\Stock`: `product_id`, `warehouse_id`, `cantidad` (float, unidad base), `minimo` (float).
  - `Product::stocks(): HasMany<Stock>` y `Product::cantidadEn(int $warehouseId): float`.
  - `SetProductStock::handle(User $user, Product $product, int $warehouseId, float $cantidad, ?float $minimo = null): Stock` — **fija** la cantidad, no la incrementa; audita valor anterior y nuevo.
  - `POST /v1/products/{product}/stock`.
  - `StoreProductRequest` acepta además `warehouse_id` y `cantidad` opcionales (stock inicial); si va uno, va el otro.

- [ ] **Step 1: Escribir los tests**

`tests/Feature/Warehouses/StockManagementTest.php`:

```php
<?php

namespace Tests\Feature\Warehouses;

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

        $log = \App\Modules\Audit\Models\AuditLog::where('accion', 'stock.fijado')->firstOrFail();
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
        \App\Models\User::factory()->create(['warehouse_id' => $warehouse->id]);

        $this->deleteJson("/v1/warehouses/{$warehouse->id}")->assertStatus(422);
    }

    public function test_se_puede_borrar_un_almacen_vacio(): void
    {
        $this->actingAsRole('admin');
        $warehouse = Warehouse::factory()->create();

        $this->deleteJson("/v1/warehouses/{$warehouse->id}")->assertNoContent();
    }
}
```

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

Run: `php artisan test --filter=StockManagementTest`
Expected: FAIL — la clase `Stock` no existe.

- [ ] **Step 3: Migración, modelo y factory de stock**

```bash
php artisan make:migration create_stocks_table --no-interaction
```

```php
        Schema::create('stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            // Siempre en unidad base.
            $table->decimal('cantidad', 14, 3)->default(0);
            $table->decimal('minimo', 14, 3)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
        });
```

`app/Modules/Warehouses/Models/Stock.php`:

```php
<?php

namespace App\Modules\Warehouses\Models;

use App\Modules\Catalog\Models\Product;
use Database\Factories\StockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    /** @use HasFactory<StockFactory> */
    use HasFactory;

    protected $fillable = ['product_id', 'warehouse_id', 'cantidad', 'minimo'];

    protected function casts(): array
    {
        return ['cantidad' => 'float', 'minimo' => 'float'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    protected static function newFactory(): StockFactory
    {
        return StockFactory::new();
    }
}
```

`database/factories/StockFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Stock> */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'cantidad' => fake()->numberBetween(0, 500),
            'minimo' => 0,
        ];
    }
}
```

Relaciones en `Product`:

```php
    /** @return HasMany<Stock, $this> */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function cantidadEn(int $warehouseId): float
    {
        return (float) ($this->stocks()->where('warehouse_id', $warehouseId)->value('cantidad') ?? 0);
    }
```

(con `use App\Modules\Warehouses\Models\Stock;`).

- [ ] **Step 4: Action `SetProductStock`**

`app/Modules/Warehouses/Actions/SetProductStock.php`:

```php
<?php

namespace App\Modules\Warehouses\Actions;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouses\Models\Stock;
use Illuminate\Support\Facades\DB;

class SetProductStock
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Fija (no incrementa) la cantidad del producto en el almacén, en unidad base.
     */
    public function handle(User $user, Product $product, int $warehouseId, float $cantidad, ?float $minimo = null): Stock
    {
        return DB::transaction(function () use ($user, $product, $warehouseId, $cantidad, $minimo): Stock {
            $stock = Stock::lockForUpdate()->firstOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouseId],
                ['cantidad' => 0, 'minimo' => 0],
            );

            $anterior = $stock->cantidad;

            $stock->cantidad = $cantidad;
            if ($minimo !== null) {
                $stock->minimo = $minimo;
            }
            $stock->save();

            $this->audit->log($user, AuditLogger::ACCION_STOCK_FIJADO, $product, $warehouseId, [
                'anterior' => number_format($anterior, 3, '.', ''),
                'nuevo' => number_format($cantidad, 3, '.', ''),
            ]);

            return $stock;
        });
    }
}
```

- [ ] **Step 5: Request, Resource, Controller y ruta**

`app/Modules/Warehouses/Http/Requests/SetStockRequest.php`:

```php
<?php

namespace App\Modules\Warehouses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'cantidad' => ['required', 'numeric', 'min:0'],
            'minimo' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
```

`app/Modules/Warehouses/Http/Resources/StockResource.php`:

```php
<?php

namespace App\Modules\Warehouses\Http\Resources;

use App\Modules\Warehouses\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Stock */
class StockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'cantidad' => number_format($this->cantidad, 3, '.', ''),
            'minimo' => number_format($this->minimo, 3, '.', ''),
        ];
    }
}
```

`app/Modules/Warehouses/Http/Controllers/ProductStockController.php`:

```php
<?php

namespace App\Modules\Warehouses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouses\Actions\SetProductStock;
use App\Modules\Warehouses\Http\Requests\SetStockRequest;
use App\Modules\Warehouses\Http\Resources\StockResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @group Almacenes · Stock
 *
 * @authenticated
 */
class ProductStockController extends Controller
{
    use AuthorizesRequests;

    /** Fija la cantidad disponible del producto en un almacén. */
    public function store(SetStockRequest $request, Product $product, SetProductStock $action): StockResource
    {
        $this->authorize('setStock', $product);

        $stock = $action->handle(
            $request->user(),
            $product,
            (int) $request->validated('warehouse_id'),
            (float) $request->validated('cantidad'),
            $request->has('minimo') ? (float) $request->validated('minimo') : null,
        );

        return new StockResource($stock);
    }
}
```

Añadir a `ProductPolicy`:

```php
    public function setStock(User $user, Product $product): bool
    {
        return $user->can('stock.set');
    }
```

En `app/Modules/Warehouses/routes.php`, dentro del grupo:

```php
    Route::post('products/{product}/stock', [ProductStockController::class, 'store']);
```

- [ ] **Step 6: Stock inicial en el alta de producto**

En `StoreProductRequest::rules()` añadir:

```php
            'warehouse_id' => ['sometimes', 'required_with:cantidad', 'integer', 'exists:warehouses,id'],
            'cantidad' => ['sometimes', 'required_with:warehouse_id', 'numeric', 'min:0'],
```

En `CreateProduct::handle()`, cambiar la firma y crear el stock inicial dentro de la misma transacción:

```php
    /**
     * @param  array<string, mixed>  $datos  nombre, precio_compra, precio_venta
     * @param  array{warehouse_id: int, cantidad: float}|null  $stockInicial
     */
    public function handle(User $user, array $datos, int $baseUnitId, ?array $stockInicial = null): Product
    {
        return DB::transaction(function () use ($user, $datos, $baseUnitId, $stockInicial): Product {
            $product = Product::create($datos);
            $product->units()->create(['unit_id' => $baseUnitId, 'is_base' => true]);

            $this->audit->log($user, AuditLogger::ACCION_PRODUCTO_CREADO, $product, null, [
                'nombre' => $product->nombre,
                'precio_compra' => number_format($product->precio_compra, 2, '.', ''),
                'precio_venta' => number_format($product->precio_venta, 2, '.', ''),
            ]);

            if ($stockInicial !== null) {
                $this->setStock->handle(
                    $user,
                    $product,
                    $stockInicial['warehouse_id'],
                    $stockInicial['cantidad'],
                );
            }

            return $product->load('units.unit');
        });
    }
```

Con el constructor ampliado:

```php
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SetProductStock $setStock,
    ) {}
```

Y en `ProductController::store()`:

```php
        $product = $action->handle(
            $request->user(),
            $request->safe()->only(['nombre', 'precio_compra', 'precio_venta']),
            (int) $request->validated('base_unit_id'),
            $request->has('warehouse_id') ? [
                'warehouse_id' => (int) $request->validated('warehouse_id'),
                'cantidad' => (float) $request->validated('cantidad'),
            ] : null,
        );
```

- [ ] **Step 7: Añadir `cantidad` a la rama del vendedor en `ProductResource`**

El vendedor ve la cantidad **de su almacén**, no la suma global:

```php
    /**
     * @return array<string, mixed>
     */
    private function paraVendedor(): array
    {
        $warehouseId = (int) request()->user()->warehouse_id;

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'precio_venta' => number_format($this->precio_venta, 2, '.', ''),
            'cantidad' => number_format($this->cantidadEn($warehouseId), 3, '.', ''),
            'unidades' => ProductUnitResource::collection($this->whenLoaded('units')),
        ];
    }
```

Y en la rama de admin, el desglose por almacén:

```php
            'stocks' => StockResource::collection($this->whenLoaded('stocks')),
```

(con `use App\Modules\Warehouses\Http\Resources\StockResource;`), cargando la relación en `ProductController::index()` y `show()`: `->with(['units.unit', 'stocks'])`.

- [ ] **Step 8: Guarda de borrado de almacén**

En `WarehouseController::destroy()`:

```php
    public function destroy(Warehouse $warehouse): Response
    {
        $this->authorize('delete', $warehouse);

        $tieneStock = Stock::where('warehouse_id', $warehouse->id)->where('cantidad', '>', 0)->exists();
        $tieneUsuarios = User::where('warehouse_id', $warehouse->id)->exists();

        if ($tieneStock || $tieneUsuarios) {
            throw ValidationException::withMessages([
                'warehouse' => ['El almacén tiene stock o usuarios asignados. Desactívalo en lugar de borrarlo.'],
            ]);
        }

        $warehouse->delete();

        return response()->noContent();
    }
```

(con `use App\Models\User;`, `use App\Modules\Warehouses\Models\Stock;` y `use Illuminate\Validation\ValidationException;`). La condición se amplía en la Task 9 para incluir las ventas.

- [ ] **Step 9: Ejecutar los tests**

Run: `php artisan test --filter=StockManagementTest`
Expected: PASS (10 tests).

Run: `php artisan test`
Expected: PASS (los tests de Catalog de la Task 7 siguen verdes; si `test_el_vendedor_no_ve_el_precio_de_compra` falla, revisa que `paraVendedor()` no incluya `precio_compra` ni `stocks`).

- [ ] **Step 10: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat(warehouses): stock por almacén, stock inicial en el alta y visibilidad por rol"
```

---

## Task 9: Ventas

**Files:**
- Create: `app/Modules/Sales/Models/{Sale,SaleItem}.php`, `app/Modules/Sales/Actions/RegisterSale.php`, `app/Modules/Warehouses/Exceptions/InsufficientStockException.php`, `app/Modules/Sales/Http/Controllers/SaleController.php`, `app/Modules/Sales/Http/Requests/StoreSaleRequest.php`, `app/Modules/Sales/Http/Resources/{SaleResource,SaleItemResource}.php`, `app/Modules/Sales/Policies/SalePolicy.php`, `app/Modules/Sales/Providers/SalesServiceProvider.php`, `app/Modules/Sales/routes.php`
- Create: `database/migrations/xxxx_create_sales_table.php`, `xxxx_create_sale_items_table.php`, `database/factories/SaleFactory.php`
- Modify: `routes/api.php`, `bootstrap/providers.php`, `bootstrap/app.php` (render de la excepción), `app/Modules/Warehouses/Http/Controllers/WarehouseController.php` (guarda de borrado)
- Test: `tests/Feature/Sales/RegisterSaleTest.php`, `tests/Feature/Sales/SaleVisibilityTest.php`

**Interfaces:**
- Consumes: `Product`, `Unit`, `ProductUnit` (Task 7), `Stock` (Task 8), middleware `scope.warehouse` (Task 4).
- Produces:
  - `App\Modules\Sales\Models\Sale`: `warehouse_id`, `user_id`, `total`; `items(): HasMany<SaleItem>`.
  - `App\Modules\Sales\Models\SaleItem`: `sale_id`, `product_id`, `unit_id`, `cantidad`, `cantidad_base`, `precio_venta_unit`, `precio_compra_unit`, `subtotal`.
  - `RegisterSale::handle(User $user, int $warehouseId, array $items): Sale` donde `$items` es `list<array{product_id: int, unit_id: ?int, cantidad: float}>`.
  - `App\Modules\Warehouses\Exceptions\InsufficientStockException` con `getFaltantes(): list<array{product_id: int, nombre: string, solicitado: string, disponible: string}>`, renderizada como `422`. **Vive en `Warehouses`, no en `Sales`**: el stock es del almacén, y así la Task 10 puede reutilizarla sin que `Warehouses` dependa de `Sales`.
  - `POST /v1/sales`, `GET /v1/sales`, `GET /v1/sales/{sale}`.

- [ ] **Step 1: Escribir los tests del registro de venta**

`tests/Feature/Sales/RegisterSaleTest.php`:

```php
<?php

namespace Tests\Feature\Sales;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterSaleTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->warehouse = Warehouse::factory()->create();
    }

    private function producto(float $compra, float $venta, float $stock): Product
    {
        $product = Product::factory()->create(['precio_compra' => $compra, 'precio_venta' => $venta]);
        Stock::factory()->for($product)->for($this->warehouse)->create(['cantidad' => $stock]);

        return $product;
    }

    public function test_una_venta_descuenta_stock_y_devuelve_el_total(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 100);
        $pan = $this->producto(0.50, 1.20, 50);

        $this->postJson('/v1/sales', [
            'items' => [
                ['product_id' => $agua->id, 'cantidad' => 10],
                ['product_id' => $pan->id, 'cantidad' => 5],
            ],
        ])->assertCreated()
            // 10×0.90 + 5×1.20 = 9.00 + 6.00 = 15.00
            ->assertJsonPath('data.total', '15.00')
            ->assertJsonCount(2, 'data.items');

        $this->assertEqualsWithDelta(90.0, $agua->cantidadEn($this->warehouse->id), 0.001);
        $this->assertEqualsWithDelta(45.0, $pan->cantidadEn($this->warehouse->id), 0.001);
    }

    public function test_vender_en_una_unidad_no_base_descuenta_el_equivalente_en_base(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 100);
        $caja = Unit::factory()->create(['nombre' => 'caja', 'factor' => 24]);
        $agua->units()->create(['unit_id' => $caja->id, 'is_base' => false]);

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $agua->id, 'unit_id' => $caja->id, 'cantidad' => 2]],
        ])->assertCreated()
            // 2 cajas × 24 = 48 unidades base × 0.90 = 43.20
            ->assertJsonPath('data.total', '43.20')
            ->assertJsonPath('data.items.0.cantidad_base', '48.000');

        $this->assertEqualsWithDelta(52.0, $agua->cantidadEn($this->warehouse->id), 0.001);
    }

    public function test_una_unidad_no_asignada_al_producto_se_rechaza(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 100);
        $ajena = Unit::factory()->create(['factor' => 24]);

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $agua->id, 'unit_id' => $ajena->id, 'cantidad' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.unit_id');
    }

    public function test_stock_insuficiente_rechaza_la_venta_entera_y_no_toca_el_inventario(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 100);
        $pan = $this->producto(0.50, 1.20, 3);

        $this->postJson('/v1/sales', [
            'items' => [
                ['product_id' => $agua->id, 'cantidad' => 10],
                ['product_id' => $pan->id, 'cantidad' => 5],
            ],
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Stock insuficiente')
            ->assertJsonPath('productos_afectados.0.product_id', $pan->id)
            ->assertJsonPath('productos_afectados.0.solicitado', '5.000')
            ->assertJsonPath('productos_afectados.0.disponible', '3.000');

        // Ni el ítem que sí tenía stock se descuenta.
        $this->assertEqualsWithDelta(100.0, $agua->cantidadEn($this->warehouse->id), 0.001);
        $this->assertEqualsWithDelta(3.0, $pan->cantidadEn($this->warehouse->id), 0.001);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
    }

    public function test_el_error_de_stock_lista_todos_los_productos_afectados(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $a = $this->producto(1, 2, 1);
        $b = $this->producto(1, 2, 1);

        $this->postJson('/v1/sales', [
            'items' => [
                ['product_id' => $a->id, 'cantidad' => 5],
                ['product_id' => $b->id, 'cantidad' => 5],
            ],
        ])->assertStatus(422)->assertJsonCount(2, 'productos_afectados');
    }

    public function test_el_mismo_producto_repetido_suma_para_comprobar_el_stock(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 10);

        $this->postJson('/v1/sales', [
            'items' => [
                ['product_id' => $agua->id, 'cantidad' => 6],
                ['product_id' => $agua->id, 'cantidad' => 6],
            ],
        ])->assertStatus(422)->assertJsonPath('productos_afectados.0.solicitado', '12.000');

        $this->assertEqualsWithDelta(10.0, $agua->cantidadEn($this->warehouse->id), 0.001);
    }

    public function test_vender_un_producto_sin_stock_en_ese_almacen_se_rechaza(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $product = Product::factory()->create();

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $product->id, 'cantidad' => 1]],
        ])->assertStatus(422)->assertJsonPath('productos_afectados.0.disponible', '0.000');
    }

    public function test_la_venta_guarda_el_snapshot_de_precios(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 100);

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $agua->id, 'cantidad' => 10]],
        ])->assertCreated();

        $agua->update(['precio_venta' => 5.00, 'precio_compra' => 3.00]);

        $this->assertDatabaseHas('sale_items', [
            'product_id' => $agua->id,
            'precio_venta_unit' => '0.90',
            'precio_compra_unit' => '0.40',
        ]);
    }

    public function test_no_se_puede_vender_un_producto_eliminado(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 100);
        $agua->delete();

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $agua->id, 'cantidad' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_la_venta_requiere_al_menos_un_item_con_cantidad_positiva(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->warehouse->id]);
        $agua = $this->producto(0.40, 0.90, 100);

        $this->postJson('/v1/sales', ['items' => []])
            ->assertStatus(422)->assertJsonValidationErrors('items');

        $this->postJson('/v1/sales', ['items' => [['product_id' => $agua->id, 'cantidad' => 0]]])
            ->assertStatus(422)->assertJsonValidationErrors('items.0.cantidad');
    }
}
```

- [ ] **Step 2: Escribir los tests de alcance y visibilidad**

`tests/Feature/Sales/SaleVisibilityTest.php`:

```php
<?php

namespace Tests\Feature\Sales;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_vendedor_vende_siempre_en_su_almacen_aunque_pida_otro(): void
    {
        $suyo = Warehouse::factory()->create();
        $ajeno = Warehouse::factory()->create();
        $product = Product::factory()->create(['precio_venta' => 2.00]);
        Stock::factory()->for($product)->for($suyo)->create(['cantidad' => 10]);
        Stock::factory()->for($product)->for($ajeno)->create(['cantidad' => 10]);

        $this->actingAsRole('vendedor', ['warehouse_id' => $suyo->id]);

        $this->postJson('/v1/sales', [
            'warehouse_id' => $ajeno->id,
            'items' => [['product_id' => $product->id, 'cantidad' => 4]],
        ])->assertCreated()->assertJsonPath('data.warehouse_id', $suyo->id);

        $this->assertEqualsWithDelta(6.0, $product->cantidadEn($suyo->id), 0.001);
        $this->assertEqualsWithDelta(10.0, $product->cantidadEn($ajeno->id), 0.001);
    }

    public function test_el_admin_debe_indicar_el_almacen(): void
    {
        $this->actingAsRole('admin');
        $product = Product::factory()->create();

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $product->id, 'cantidad' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('warehouse_id');
    }

    public function test_el_vendedor_solo_ve_las_ventas_de_su_almacen(): void
    {
        $suyo = Warehouse::factory()->create();
        $ajeno = Warehouse::factory()->create();
        Sale::factory()->for($suyo)->create();
        Sale::factory()->for($ajeno)->create();

        $this->actingAsRole('vendedor', ['warehouse_id' => $suyo->id]);

        $this->getJson('/v1/sales')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.warehouse_id', $suyo->id);
    }

    public function test_el_vendedor_no_puede_ver_una_venta_de_otro_almacen(): void
    {
        $suyo = Warehouse::factory()->create();
        $ajena = Sale::factory()->for(Warehouse::factory()->create())->create();

        $this->actingAsRole('vendedor', ['warehouse_id' => $suyo->id]);

        $this->getJson("/v1/sales/{$ajena->id}")->assertForbidden();
    }

    public function test_el_admin_ve_las_ventas_de_todos_los_almacenes(): void
    {
        Sale::factory()->count(3)->create();
        $this->actingAsRole('admin');

        $this->getJson('/v1/sales')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_la_venta_registra_el_usuario_que_la_genero(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['precio_venta' => 3.00]);
        Stock::factory()->for($product)->for($warehouse)->create(['cantidad' => 10]);

        $vendedor = $this->actingAsRole('vendedor', ['warehouse_id' => $warehouse->id]);

        $this->postJson('/v1/sales', [
            'items' => [['product_id' => $product->id, 'cantidad' => 2]],
        ])->assertCreated()->assertJsonPath('data.user_id', $vendedor->id);
    }

    public function test_no_se_puede_borrar_un_almacen_con_ventas(): void
    {
        $warehouse = Warehouse::factory()->create();
        Sale::factory()->for($warehouse)->create();
        $this->actingAsRole('admin');

        $this->deleteJson("/v1/warehouses/{$warehouse->id}")->assertStatus(422);
    }
}
```

- [ ] **Step 3: Ejecutar los tests para verificar que fallan**

Run: `php artisan test --filter="RegisterSaleTest|SaleVisibilityTest"`
Expected: FAIL — la clase `Sale` no existe.

- [ ] **Step 4: Migraciones, modelos y factory**

```bash
php artisan make:migration create_sales_table --no-interaction
php artisan make:migration create_sale_items_table --no-interaction
```

```php
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('total', 12, 2);
            $table->timestamps();

            $table->index(['warehouse_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
```

```php
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            // Cantidad tal como se vendió y su equivalente en unidad base.
            $table->decimal('cantidad', 14, 3);
            $table->decimal('cantidad_base', 14, 3);
            // Snapshots: la ganancia histórica no depende de los precios actuales.
            $table->decimal('precio_venta_unit', 12, 2);
            $table->decimal('precio_compra_unit', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();

            $table->index('product_id');
        });
```

`app/Modules/Sales/Models/Sale.php`:

```php
<?php

namespace App\Modules\Sales\Models;

use App\Models\User;
use App\Modules\Warehouses\Models\Warehouse;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    protected $fillable = ['warehouse_id', 'user_id', 'total'];

    protected function casts(): array
    {
        return ['total' => 'float'];
    }

    /** @return HasMany<SaleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function newFactory(): SaleFactory
    {
        return SaleFactory::new();
    }
}
```

`app/Modules/Sales/Models/SaleItem.php`:

```php
<?php

namespace App\Modules\Sales\Models;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id', 'product_id', 'unit_id', 'cantidad', 'cantidad_base',
        'precio_venta_unit', 'precio_compra_unit', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'float',
            'cantidad_base' => 'float',
            'precio_venta_unit' => 'float',
            'precio_compra_unit' => 'float',
            'subtotal' => 'float',
        ];
    }

    /** @return BelongsTo<Sale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
```

`database/factories/SaleFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Sale> */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'user_id' => User::factory(),
            'total' => fake()->randomFloat(2, 5, 500),
        ];
    }
}
```

- [ ] **Step 5: Excepción de stock insuficiente**

`app/Modules/Warehouses/Exceptions/InsufficientStockException.php`:

```php
<?php

namespace App\Modules\Warehouses\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class InsufficientStockException extends RuntimeException
{
    /**
     * @param  list<array{product_id: int, nombre: string, solicitado: string, disponible: string}>  $faltantes
     */
    public function __construct(private readonly array $faltantes)
    {
        parent::__construct('Stock insuficiente');
    }

    /**
     * @return list<array{product_id: int, nombre: string, solicitado: string, disponible: string}>
     */
    public function getFaltantes(): array
    {
        return $this->faltantes;
    }

    public function render(Request $request): JsonResponse
    {
        $cuantos = count($this->faltantes);

        return new JsonResponse([
            'message' => 'Stock insuficiente',
            'errors' => [
                'items' => ["Stock insuficiente para {$cuantos} producto".($cuantos === 1 ? '' : 's').'.'],
            ],
            'productos_afectados' => $this->faltantes,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
```

- [ ] **Step 6: Action `RegisterSale`**

`app/Modules/Sales/Actions/RegisterSale.php`:

```php
<?php

namespace App\Modules\Sales\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Warehouses\Exceptions\InsufficientStockException;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Models\Stock;
use Illuminate\Support\Facades\DB;

class RegisterSale
{
    /**
     * Registra una venta y descuenta el stock del almacén.
     *
     * Valida la disponibilidad de **todos** los ítems antes de escribir nada:
     * si alguno no cabe, no se registra la venta ni se altera el inventario.
     *
     * @param  list<array{product_id: int, unit_id?: int|null, cantidad: float}>  $items
     *
     * @throws InsufficientStockException
     */
    public function handle(User $user, int $warehouseId, array $items): Sale
    {
        return DB::transaction(function () use ($user, $warehouseId, $items): Sale {
            $products = Product::with('units')
                ->whereIn('id', array_column($items, 'product_id'))
                ->get()
                ->keyBy('id');

            $units = Unit::whereIn('id', array_filter(array_column($items, 'unit_id')))->get()->keyBy('id');

            // 1. Resolver cada línea a unidad base.
            $lineas = [];
            $demandaPorProducto = [];

            foreach ($items as $item) {
                $product = $products[$item['product_id']];
                $unit = isset($item['unit_id'])
                    ? $units[$item['unit_id']]
                    : $product->units->firstWhere('is_base', true)->unit()->first();

                $cantidadBase = $product->toBase((float) $item['cantidad'], $unit);

                $lineas[] = [
                    'product' => $product,
                    'unit' => $unit,
                    'cantidad' => (float) $item['cantidad'],
                    'cantidad_base' => $cantidadBase,
                ];

                $demandaPorProducto[$product->id] = ($demandaPorProducto[$product->id] ?? 0) + $cantidadBase;
            }

            // 2. Bloquear las filas de stock implicadas.
            $stocks = Stock::where('warehouse_id', $warehouseId)
                ->whereIn('product_id', array_keys($demandaPorProducto))
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            // 3. Validar TODO antes de tocar nada.
            $faltantes = [];

            foreach ($demandaPorProducto as $productId => $solicitado) {
                $disponible = (float) ($stocks[$productId]->cantidad ?? 0);

                if ($solicitado > $disponible + 0.0001) {
                    $faltantes[] = [
                        'product_id' => (int) $productId,
                        'nombre' => $products[$productId]->nombre,
                        'solicitado' => number_format($solicitado, 3, '.', ''),
                        'disponible' => number_format($disponible, 3, '.', ''),
                    ];
                }
            }

            if ($faltantes !== []) {
                throw new InsufficientStockException($faltantes);
            }

            // 4. Descontar.
            foreach ($demandaPorProducto as $productId => $solicitado) {
                $stocks[$productId]->decrement('cantidad', $solicitado);
            }

            // 5. Registrar la venta con los snapshots de precio.
            $sale = Sale::create(['warehouse_id' => $warehouseId, 'user_id' => $user->id, 'total' => 0]);
            $total = 0.0;

            foreach ($lineas as $linea) {
                $product = $linea['product'];
                $subtotal = round($product->precio_venta * $linea['cantidad_base'], 2);
                $total += $subtotal;

                $sale->items()->create([
                    'product_id' => $product->id,
                    'unit_id' => $linea['unit']->id,
                    'cantidad' => $linea['cantidad'],
                    'cantidad_base' => $linea['cantidad_base'],
                    'precio_venta_unit' => $product->precio_venta,
                    'precio_compra_unit' => $product->precio_compra,
                    'subtotal' => $subtotal,
                ]);
            }

            $sale->update(['total' => round($total, 2)]);

            return $sale->load('items.unit', 'items.product');
        });
    }
}
```

- [ ] **Step 7: Form Request con validación de unidad y almacén**

`app/Modules/Sales/Http/Requests/StoreSaleRequest.php`:

```php
<?php

namespace App\Modules\Sales\Http\Requests;

use App\Modules\Catalog\Models\ProductUnit;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // El middleware `scope.warehouse` ya lo ha fijado para el vendedor;
            // el admin debe indicarlo explícitamente.
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * La unidad de cada línea debe estar asignada a su producto.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('items', []) as $i => $item) {
                if (! isset($item['unit_id'], $item['product_id'])) {
                    continue;
                }

                $asignada = ProductUnit::where('product_id', $item['product_id'])
                    ->where('unit_id', $item['unit_id'])
                    ->exists();

                if (! $asignada) {
                    $validator->errors()->add("items.{$i}.unit_id", 'Esa unidad no está asignada al producto.');
                }
            }
        });
    }
}
```

`exists:products,id` no encuentra los productos con soft delete, así que un producto eliminado se rechaza automáticamente en `items.*.product_id`.

- [ ] **Step 8: Resources, Policy, Controller, Provider y rutas**

`app/Modules/Sales/Http/Resources/SaleItemResource.php`:

```php
<?php

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SaleItem */
class SaleItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'producto' => $this->whenLoaded('product', fn (): string => $this->product->nombre),
            'unit_id' => $this->unit_id,
            'unidad' => $this->whenLoaded('unit', fn (): string => $this->unit->nombre),
            'cantidad' => number_format($this->cantidad, 3, '.', ''),
            'cantidad_base' => number_format($this->cantidad_base, 3, '.', ''),
            'precio_venta_unit' => number_format($this->precio_venta_unit, 2, '.', ''),
            'subtotal' => number_format($this->subtotal, 2, '.', ''),
        ];
    }
}
```

El ítem **no** expone `precio_compra_unit`: es dato de coste y solo alimenta las métricas del admin.

`app/Modules/Sales/Http/Resources/SaleResource.php`:

```php
<?php

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sale */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'user_id' => $this->user_id,
            'total' => number_format($this->total, 2, '.', ''),
            'fecha' => $this->created_at,
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
```

`app/Modules/Sales/Policies/SalePolicy.php`:

```php
<?php

namespace App\Modules\Sales\Policies;

use App\Models\User;
use App\Modules\Sales\Models\Sale;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.view');
    }

    /** El vendedor solo ve las ventas de su propio almacén. */
    public function view(User $user, Sale $sale): bool
    {
        if (! $user->can('sales.view')) {
            return false;
        }

        return $user->isAdmin() || $user->warehouse_id === $sale->warehouse_id;
    }

    public function create(User $user): bool
    {
        return $user->can('sales.create');
    }
}
```

`app/Modules/Sales/Http/Controllers/SaleController.php`:

```php
<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Actions\RegisterSale;
use App\Modules\Sales\Http\Requests\StoreSaleRequest;
use App\Modules\Sales\Http\Resources\SaleResource;
use App\Modules\Sales\Models\Sale;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Ventas
 *
 * @authenticated
 */
class SaleController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sale::class);

        $user = request()->user();

        $sales = QueryBuilder::for(Sale::class)
            ->with('items.product', 'items.unit')
            ->when($user->isVendedor(), fn ($q) => $q->where('warehouse_id', $user->warehouse_id))
            ->allowedFilters('warehouse_id', 'user_id')
            ->defaultSort('-created_at')
            ->allowedSorts('created_at', 'total')
            ->paginate()
            ->appends(request()->query());

        return SaleResource::collection($sales);
    }

    /**
     * Registrar una venta.
     *
     * Descuenta el stock y devuelve el total de los productos vendidos.
     */
    public function store(StoreSaleRequest $request, RegisterSale $action): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $sale = $action->handle(
            $request->user(),
            (int) $request->validated('warehouse_id'),
            $request->validated('items'),
        );

        return (new SaleResource($sale))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Sale $sale): SaleResource
    {
        $this->authorize('view', $sale);

        return new SaleResource($sale->load('items.product', 'items.unit'));
    }
}
```

`app/Modules/Sales/Providers/SalesServiceProvider.php` — registra `Gate::policy(Sale::class, SalePolicy::class)`, siguiendo el patrón de `AuditServiceProvider`. Añadirlo a `bootstrap/providers.php`.

`app/Modules/Sales/routes.php` — el middleware `scope.warehouse` es lo que fuerza el almacén del vendedor:

```php
<?php

use App\Modules\Sales\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'scope.warehouse'])->group(function (): void {
    Route::get('sales', [SaleController::class, 'index']);
    Route::post('sales', [SaleController::class, 'store']);
    Route::get('sales/{sale}', [SaleController::class, 'show']);
});
```

Y la línea correspondiente en `routes/api.php`.

- [ ] **Step 9: Ampliar la guarda de borrado de almacén**

En `WarehouseController::destroy()`, añadir las ventas a la condición:

```php
        $tieneVentas = Sale::where('warehouse_id', $warehouse->id)->exists();

        if ($tieneStock || $tieneUsuarios || $tieneVentas) {
```

(con `use App\Modules\Sales\Models\Sale;`).

- [ ] **Step 10: Ejecutar los tests**

Run: `php artisan test --filter="RegisterSaleTest|SaleVisibilityTest"`
Expected: PASS (17 tests).

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 11: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat(sales): venta multi-producto con conversión de unidades y rechazo atómico por stock"
```

---

## Task 10: Transferencias entre almacenes

**Files:**
- Create: `app/Modules/Warehouses/Models/Transfer.php`, `app/Modules/Warehouses/Actions/TransferStock.php`, `app/Modules/Warehouses/Http/Controllers/TransferController.php`, `app/Modules/Warehouses/Http/Requests/StoreTransferRequest.php`, `app/Modules/Warehouses/Http/Resources/TransferResource.php`, `app/Modules/Warehouses/Policies/TransferPolicy.php`
- Create: `database/migrations/xxxx_create_transfers_table.php`
- Modify: `app/Modules/Warehouses/routes.php`, `app/Modules/Warehouses/Providers/WarehousesServiceProvider.php`
- Test: `tests/Feature/Warehouses/TransferTest.php`

**Interfaces:**
- Consumes: `Stock` (Task 8), `Product`, `Unit` (Task 7), `AuditLogger` (Task 5), `App\Modules\Warehouses\Exceptions\InsufficientStockException` (creada en la Task 9, dentro de este mismo módulo).
- Produces:
  - `App\Modules\Warehouses\Models\Transfer`: `product_id`, `from_warehouse_id`, `to_warehouse_id`, `cantidad_base`, `user_id`.
  - `TransferStock::handle(User $user, int $productId, int $fromWarehouseId, int $toWarehouseId, float $cantidad, ?int $unitId = null): Transfer`
  - `POST /v1/transfers`, `GET /v1/transfers`.

- [ ] **Step 1: Escribir los tests**

`tests/Feature/Warehouses/TransferTest.php`:

```php
<?php

namespace Tests\Feature\Warehouses;

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
}
```

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

Run: `php artisan test --filter=TransferTest`
Expected: FAIL — `/v1/transfers` devuelve 404.

- [ ] **Step 3: Migración y modelo**

```bash
php artisan make:migration create_transfers_table --no-interaction
```

```php
        Schema::create('transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('from_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('cantidad_base', 14, 3);
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
        });
```

`app/Modules/Warehouses/Models/Transfer.php`:

```php
<?php

namespace App\Modules\Warehouses\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id', 'from_warehouse_id', 'to_warehouse_id', 'cantidad_base', 'user_id',
    ];

    protected function casts(): array
    {
        return ['cantidad_base' => 'float', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 4: Action `TransferStock`**

`app/Modules/Warehouses/Actions/TransferStock.php`:

```php
<?php

namespace App\Modules\Warehouses\Actions;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Warehouses\Exceptions\InsufficientStockException;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Transfer;
use Illuminate\Support\Facades\DB;

class TransferStock
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Mueve stock de un almacén a otro. Solo la ejecuta el admin, así que es
     * inmediata: no hay estado "en tránsito" ni aprobación.
     *
     * @throws InsufficientStockException si el origen no tiene cantidad suficiente
     */
    public function handle(
        User $user,
        int $productId,
        int $fromWarehouseId,
        int $toWarehouseId,
        float $cantidad,
        ?int $unitId = null,
    ): Transfer {
        return DB::transaction(function () use ($user, $productId, $fromWarehouseId, $toWarehouseId, $cantidad, $unitId): Transfer {
            $product = Product::with('units')->findOrFail($productId);

            $unit = $unitId !== null
                ? Unit::findOrFail($unitId)
                : $product->units->firstWhere('is_base', true)->unit()->first();

            $cantidadBase = $product->toBase($cantidad, $unit);

            $origen = Stock::where('product_id', $productId)
                ->where('warehouse_id', $fromWarehouseId)
                ->lockForUpdate()
                ->first();

            $disponible = (float) ($origen->cantidad ?? 0);

            if ($cantidadBase > $disponible + 0.0001) {
                throw new InsufficientStockException([[
                    'product_id' => $productId,
                    'nombre' => $product->nombre,
                    'solicitado' => number_format($cantidadBase, 3, '.', ''),
                    'disponible' => number_format($disponible, 3, '.', ''),
                ]]);
            }

            $origen->decrement('cantidad', $cantidadBase);

            $destino = Stock::lockForUpdate()->firstOrCreate(
                ['product_id' => $productId, 'warehouse_id' => $toWarehouseId],
                ['cantidad' => 0, 'minimo' => 0],
            );
            $destino->increment('cantidad', $cantidadBase);

            $transfer = Transfer::create([
                'product_id' => $productId,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'cantidad_base' => $cantidadBase,
                'user_id' => $user->id,
            ]);

            $this->audit->log($user, AuditLogger::ACCION_TRANSFERENCIA, $product, $fromWarehouseId, [
                'desde' => $fromWarehouseId,
                'hacia' => $toWarehouseId,
                'cantidad_base' => number_format($cantidadBase, 3, '.', ''),
                'unidad' => $unit->nombre,
            ]);

            return $transfer;
        });
    }
}
```

- [ ] **Step 5: Request, Resource, Policy, Controller y rutas**

`app/Modules/Warehouses/Http/Requests/StoreTransferRequest.php`:

```php
<?php

namespace App\Modules\Warehouses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_warehouse_id.different' => 'El almacén de destino debe ser distinto del de origen.',
        ];
    }
}
```

`app/Modules/Warehouses/Http/Resources/TransferResource.php`:

```php
<?php

namespace App\Modules\Warehouses\Http\Resources;

use App\Modules\Warehouses\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transfer */
class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'producto' => $this->whenLoaded('product', fn (): string => $this->product->nombre),
            'from_warehouse_id' => $this->from_warehouse_id,
            'to_warehouse_id' => $this->to_warehouse_id,
            'cantidad_base' => number_format($this->cantidad_base, 3, '.', ''),
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
        ];
    }
}
```

`app/Modules/Warehouses/Policies/TransferPolicy.php`:

```php
<?php

namespace App\Modules\Warehouses\Policies;

use App\Models\User;

class TransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('transfers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('transfers.create');
    }
}
```

Registrarla en `WarehousesServiceProvider::boot()`:

```php
        Gate::policy(Transfer::class, TransferPolicy::class);
```

`app/Modules/Warehouses/Http/Controllers/TransferController.php`:

```php
<?php

namespace App\Modules\Warehouses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Warehouses\Actions\TransferStock;
use App\Modules\Warehouses\Http\Requests\StoreTransferRequest;
use App\Modules\Warehouses\Http\Resources\TransferResource;
use App\Modules\Warehouses\Models\Transfer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Almacenes · Transferencias
 *
 * @authenticated
 */
class TransferController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Transfer::class);

        $transfers = QueryBuilder::for(Transfer::class)
            ->with('product')
            ->allowedFilters('product_id', 'from_warehouse_id', 'to_warehouse_id', 'user_id')
            ->defaultSort('-created_at')
            ->allowedSorts('created_at')
            ->paginate()
            ->appends(request()->query());

        return TransferResource::collection($transfers);
    }

    /** Transferencia inmediata entre almacenes (solo admin). */
    public function store(StoreTransferRequest $request, TransferStock $action): JsonResponse
    {
        $this->authorize('create', Transfer::class);

        $transfer = $action->handle(
            $request->user(),
            (int) $request->validated('product_id'),
            (int) $request->validated('from_warehouse_id'),
            (int) $request->validated('to_warehouse_id'),
            (float) $request->validated('cantidad'),
            $request->filled('unit_id') ? (int) $request->validated('unit_id') : null,
        );

        return (new TransferResource($transfer))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
```

En `app/Modules/Warehouses/routes.php`:

```php
    Route::get('transfers', [TransferController::class, 'index']);
    Route::post('transfers', [TransferController::class, 'store']);
```

- [ ] **Step 6: Ejecutar los tests**

Run: `php artisan test --filter=TransferTest`
Expected: PASS (7 tests).

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat(warehouses): transferencias inmediatas entre almacenes con auditoría"
```

---

## Task 11: Métricas de ventas — periodos y agregados base

**Files:**
- Create: `app/Modules/Metrics/Enums/Period.php`, `app/Modules/Metrics/Support/SalesMetricsReporter.php`, `app/Modules/Metrics/Http/Controllers/SalesMetricsController.php`, `app/Modules/Metrics/Http/Requests/SalesMetricsRequest.php`, `app/Modules/Metrics/routes.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Metrics/SalesMetricsTest.php`, `tests/Unit/Metrics/PeriodTest.php`

**Interfaces:**
- Consumes: `Sale`, `SaleItem` (Task 9), middleware `scope.warehouse` (Task 4), permisos `metrics.view` / `metrics.full` (Task 2).
- Produces:
  - `App\Modules\Metrics\Enums\Period` — `Daily = 'daily'`, `Weekly = 'weekly'`, `Monthly = 'monthly'`; métodos `rango(CarbonInterface $fecha): array{0: CarbonImmutable, 1: CarbonImmutable}` (inicio inclusivo, fin exclusivo) y `rangoAnterior(CarbonInterface $fecha): array{0: CarbonImmutable, 1: CarbonImmutable}`.
  - `SalesMetricsReporter::report(Period $period, CarbonInterface $fecha, ?int $warehouseId): array` con las claves `periodo`, `desde`, `hasta`, `warehouse_id`, `ingresos`, `numero_ventas`, `unidades_vendidas`, `ganancia`, `ticket_promedio`. La Task 12 añade `serie`, `top_productos`, `ventas_por_vendedor` y `comparativa`.
  - `GET /v1/metrics/sales?period=&date=&warehouse_id=`.

- [ ] **Step 1: Escribir el test unitario de periodos**

`tests/Unit/Metrics/PeriodTest.php`:

```php
<?php

namespace Tests\Unit\Metrics;

use App\Modules\Metrics\Enums\Period;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PeriodTest extends TestCase
{
    public function test_el_periodo_diario_va_de_medianoche_a_medianoche(): void
    {
        [$desde, $hasta] = Period::Daily->rango(CarbonImmutable::parse('2026-03-11 15:42:00'));

        $this->assertSame('2026-03-11 00:00:00', $desde->toDateTimeString());
        $this->assertSame('2026-03-12 00:00:00', $hasta->toDateTimeString());
    }

    public function test_el_periodo_semanal_va_de_lunes_a_lunes(): void
    {
        // 2026-03-11 es miércoles.
        [$desde, $hasta] = Period::Weekly->rango(CarbonImmutable::parse('2026-03-11 15:42:00'));

        $this->assertSame('2026-03-09 00:00:00', $desde->toDateTimeString());
        $this->assertSame('2026-03-16 00:00:00', $hasta->toDateTimeString());
    }

    public function test_el_periodo_mensual_cubre_el_mes_natural(): void
    {
        [$desde, $hasta] = Period::Monthly->rango(CarbonImmutable::parse('2026-03-11 15:42:00'));

        $this->assertSame('2026-03-01 00:00:00', $desde->toDateTimeString());
        $this->assertSame('2026-04-01 00:00:00', $hasta->toDateTimeString());
    }

    public function test_el_periodo_anterior_es_el_inmediatamente_previo(): void
    {
        $fecha = CarbonImmutable::parse('2026-03-11 15:42:00');

        $this->assertSame('2026-03-10 00:00:00', Period::Daily->rangoAnterior($fecha)[0]->toDateTimeString());
        $this->assertSame('2026-03-02 00:00:00', Period::Weekly->rangoAnterior($fecha)[0]->toDateTimeString());
        $this->assertSame('2026-02-01 00:00:00', Period::Monthly->rangoAnterior($fecha)[0]->toDateTimeString());
        $this->assertSame('2026-03-01 00:00:00', Period::Monthly->rangoAnterior($fecha)[1]->toDateTimeString());
    }
}
```

- [ ] **Step 2: Escribir los tests de las métricas base**

`tests/Feature/Metrics/SalesMetricsTest.php`:

```php
<?php

namespace Tests\Feature\Metrics;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalesMetricsTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $almacen;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-11 12:00:00'); // miércoles
        $this->almacen = Warehouse::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Crea una venta ya cerrada con una línea, sin pasar por la API.
     */
    private function venta(
        Warehouse $almacen,
        User $usuario,
        Product $product,
        float $cantidadBase,
        string $cuando,
    ): Sale {
        $subtotal = round($product->precio_venta * $cantidadBase, 2);

        $sale = Sale::factory()->for($almacen)->for($usuario)->create([
            'total' => $subtotal,
            'created_at' => $cuando,
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'unit_id' => $product->baseProductUnit()->unit_id,
            'cantidad' => $cantidadBase,
            'cantidad_base' => $cantidadBase,
            'precio_venta_unit' => $product->precio_venta,
            'precio_compra_unit' => $product->precio_compra,
            'subtotal' => $subtotal,
        ]);

        return $sale;
    }

    public function test_metrica_diaria_agrega_ingresos_ventas_unidades_ganancia_y_ticket(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 3.00]);

        $this->venta($this->almacen, $admin, $product, 10, '2026-03-11 09:00:00'); // 30.00
        $this->venta($this->almacen, $admin, $product, 5, '2026-03-11 18:00:00');  // 15.00
        $this->venta($this->almacen, $admin, $product, 100, '2026-03-10 09:00:00'); // otro día

        $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')
            ->assertOk()
            ->assertJsonPath('data.periodo', 'daily')
            ->assertJsonPath('data.ingresos', '45.00')
            ->assertJsonPath('data.numero_ventas', 2)
            ->assertJsonPath('data.unidades_vendidas', '15.000')
            // (3.00 − 1.00) × 15 = 30.00
            ->assertJsonPath('data.ganancia', '30.00')
            ->assertJsonPath('data.ticket_promedio', '22.50');
    }

    public function test_metrica_semanal_cubre_de_lunes_a_domingo(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);

        $this->venta($this->almacen, $admin, $product, 10, '2026-03-09 09:00:00'); // lunes
        $this->venta($this->almacen, $admin, $product, 10, '2026-03-15 23:00:00'); // domingo
        $this->venta($this->almacen, $admin, $product, 10, '2026-03-16 00:30:00'); // lunes siguiente

        $this->getJson('/v1/metrics/sales?period=weekly&date=2026-03-11')
            ->assertOk()
            ->assertJsonPath('data.numero_ventas', 2)
            ->assertJsonPath('data.ingresos', '40.00');
    }

    public function test_metrica_mensual_cubre_el_mes_natural(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);

        $this->venta($this->almacen, $admin, $product, 5, '2026-03-01 00:00:00');
        $this->venta($this->almacen, $admin, $product, 5, '2026-03-31 23:59:00');
        $this->venta($this->almacen, $admin, $product, 5, '2026-04-01 00:00:00');

        $this->getJson('/v1/metrics/sales?period=monthly&date=2026-03-11')
            ->assertOk()->assertJsonPath('data.numero_ventas', 2);
    }

    public function test_sin_ventas_los_agregados_son_cero_y_el_ticket_no_divide_por_cero(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')
            ->assertOk()
            ->assertJsonPath('data.ingresos', '0.00')
            ->assertJsonPath('data.numero_ventas', 0)
            ->assertJsonPath('data.ticket_promedio', '0.00');
    }

    public function test_filtrar_por_almacen_aisla_los_datos_y_sin_filtro_son_globales(): void
    {
        $admin = $this->actingAsRole('admin');
        $otro = Warehouse::factory()->create();
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);

        $this->venta($this->almacen, $admin, $product, 10, '2026-03-11 09:00:00'); // 20.00
        $this->venta($otro, $admin, $product, 5, '2026-03-11 09:00:00');            // 10.00

        $this->getJson("/v1/metrics/sales?period=daily&date=2026-03-11&warehouse_id={$this->almacen->id}")
            ->assertOk()->assertJsonPath('data.ingresos', '20.00');

        $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')
            ->assertOk()->assertJsonPath('data.ingresos', '30.00');
    }

    public function test_sin_date_se_usa_el_momento_actual(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);
        $this->venta($this->almacen, $admin, $product, 4, '2026-03-11 10:00:00');

        $this->getJson('/v1/metrics/sales?period=daily')
            ->assertOk()->assertJsonPath('data.ingresos', '8.00');
    }

    public function test_un_periodo_invalido_se_rechaza(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/v1/metrics/sales?period=anual')
            ->assertStatus(422)->assertJsonValidationErrors('period');
    }

    public function test_el_vendedor_solo_puede_pedir_la_semana(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => $this->almacen->id]);

        $this->getJson('/v1/metrics/sales?period=weekly')->assertOk();
        $this->getJson('/v1/metrics/sales?period=daily')->assertForbidden();
        $this->getJson('/v1/metrics/sales?period=monthly')->assertForbidden();
    }

    public function test_el_vendedor_solo_ve_su_almacen_aunque_pida_otro(): void
    {
        $ajeno = Warehouse::factory()->create();
        $otroUsuario = User::factory()->create();
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);

        $this->venta($this->almacen, $otroUsuario, $product, 3, '2026-03-11 10:00:00'); // 6.00
        $this->venta($ajeno, $otroUsuario, $product, 50, '2026-03-11 10:00:00');         // 100.00

        $this->actingAsRole('vendedor', ['warehouse_id' => $this->almacen->id]);

        $this->getJson("/v1/metrics/sales?period=weekly&warehouse_id={$ajeno->id}")
            ->assertOk()
            ->assertJsonPath('data.warehouse_id', $this->almacen->id)
            ->assertJsonPath('data.ingresos', '6.00');
    }
}
```

- [ ] **Step 3: Ejecutar los tests para verificar que fallan**

Run: `php artisan test --filter="PeriodTest|SalesMetricsTest"`
Expected: FAIL — el enum `Period` no existe.

- [ ] **Step 4: Escribir el enum `Period`**

`app/Modules/Metrics/Enums/Period.php`:

```php
<?php

namespace App\Modules\Metrics\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

enum Period: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * Ventana del periodo que contiene `$fecha`: inicio inclusivo, fin exclusivo.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function rango(CarbonInterface $fecha): array
    {
        $fecha = CarbonImmutable::instance($fecha);

        return match ($this) {
            self::Daily => [$fecha->startOfDay(), $fecha->startOfDay()->addDay()],
            self::Weekly => [$fecha->startOfWeek(CarbonInterface::MONDAY), $fecha->startOfWeek(CarbonInterface::MONDAY)->addWeek()],
            self::Monthly => [$fecha->startOfMonth(), $fecha->startOfMonth()->addMonth()],
        };
    }

    /**
     * Ventana del periodo inmediatamente anterior, para la comparativa.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function rangoAnterior(CarbonInterface $fecha): array
    {
        [$desde] = $this->rango($fecha);

        return $this->rango(match ($this) {
            self::Daily => $desde->subDay(),
            self::Weekly => $desde->subWeek(),
            self::Monthly => $desde->subMonth(),
        });
    }
}
```

- [ ] **Step 5: Escribir el `SalesMetricsReporter`**

`app/Modules/Metrics/Support/SalesMetricsReporter.php`:

```php
<?php

namespace App\Modules\Metrics\Support;

use App\Modules\Metrics\Enums\Period;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Calcula las métricas de ventas al vuelo sobre `sales` y `sale_items`.
 *
 * No hay tablas de agregados: con el volumen esperado la agregación SQL basta
 * y no hay riesgo de que los agregados queden desincronizados.
 */
class SalesMetricsReporter
{
    /**
     * @return array<string, mixed>
     */
    public function report(Period $period, CarbonInterface $fecha, ?int $warehouseId): array
    {
        [$desde, $hasta] = $period->rango($fecha);

        return [
            'periodo' => $period->value,
            'desde' => $desde->toDateTimeString(),
            'hasta' => $hasta->toDateTimeString(),
            'warehouse_id' => $warehouseId,
            ...$this->agregados($desde, $hasta, $warehouseId),
        ];
    }

    /**
     * Ingresos, nº de ventas, unidades, ganancia y ticket promedio de una ventana.
     *
     * @return array<string, mixed>
     */
    public function agregados(CarbonImmutable $desde, CarbonImmutable $hasta, ?int $warehouseId): array
    {
        $ventas = $this->ventasEn($desde, $hasta, $warehouseId);

        $ingresos = (float) $ventas->clone()->sum('total');
        $numeroVentas = (int) $ventas->clone()->count();

        $lineas = SaleItem::whereIn('sale_id', $ventas->clone()->select('sales.id'))
            ->selectRaw('COALESCE(SUM(cantidad_base), 0) as unidades')
            ->selectRaw('COALESCE(SUM((precio_venta_unit - precio_compra_unit) * cantidad_base), 0) as ganancia')
            ->first();

        return [
            'ingresos' => number_format($ingresos, 2, '.', ''),
            'numero_ventas' => $numeroVentas,
            'unidades_vendidas' => number_format((float) $lineas->unidades, 3, '.', ''),
            'ganancia' => number_format((float) $lineas->ganancia, 2, '.', ''),
            'ticket_promedio' => number_format($numeroVentas > 0 ? $ingresos / $numeroVentas : 0, 2, '.', ''),
        ];
    }

    /**
     * Columnas cualificadas con `sales.`: `ventasPorVendedor()` hace `join` con
     * `users`, que también tiene `created_at`, y sin el prefijo la consulta es
     * ambigua.
     *
     * @return EloquentBuilder<Sale>
     */
    public function ventasEn(CarbonImmutable $desde, CarbonImmutable $hasta, ?int $warehouseId): EloquentBuilder
    {
        return Sale::query()
            ->where('sales.created_at', '>=', $desde)
            ->where('sales.created_at', '<', $hasta)
            ->when($warehouseId !== null, fn (Builder $q) => $q->where('sales.warehouse_id', $warehouseId));
    }
}
```

- [ ] **Step 6: Request y Controller con las restricciones por rol**

`app/Modules/Metrics/Http/Requests/SalesMetricsRequest.php`:

```php
<?php

namespace App\Modules\Metrics\Http\Requests;

use App\Modules\Metrics\Enums\Period;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['required', Rule::enum(Period::class)],
            'date' => ['sometimes', 'date'],
            'warehouse_id' => ['sometimes', 'nullable', 'integer', 'exists:warehouses,id'],
        ];
    }
}
```

`app/Modules/Metrics/Http/Controllers/SalesMetricsController.php`:

```php
<?php

namespace App\Modules\Metrics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Metrics\Enums\Period;
use App\Modules\Metrics\Http\Requests\SalesMetricsRequest;
use App\Modules\Metrics\Support\SalesMetricsReporter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @group Métricas · Ventas
 *
 * @authenticated
 */
class SalesMetricsController extends Controller
{
    /**
     * Métricas de ventas del periodo.
     *
     * El vendedor solo puede pedir `weekly` y siempre de su almacén.
     */
    public function __invoke(SalesMetricsRequest $request, SalesMetricsReporter $reporter): JsonResponse
    {
        $user = $request->user();
        $period = Period::from($request->validated('period'));

        if ($user->isVendedor() && $period !== Period::Weekly) {
            throw new AccessDeniedHttpException('El vendedor solo puede consultar métricas semanales.');
        }

        $fecha = $request->filled('date')
            ? CarbonImmutable::parse($request->validated('date'))
            : CarbonImmutable::now();

        // El middleware `scope.warehouse` ya ha forzado el almacén del vendedor.
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->validated('warehouse_id') : null;

        return new JsonResponse(['data' => $reporter->report($period, $fecha, $warehouseId)]);
    }
}
```

`app/Modules/Metrics/routes.php`:

```php
<?php

use App\Modules\Metrics\Http\Controllers\SalesMetricsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'scope.warehouse'])->group(function (): void {
    Route::get('metrics/sales', SalesMetricsController::class)->middleware('can:metrics.view');
});
```

Y la línea correspondiente en `routes/api.php`.

- [ ] **Step 7: Ejecutar los tests**

Run: `php artisan test --filter="PeriodTest|SalesMetricsTest"`
Expected: PASS (13 tests).

Si `test_el_vendedor_solo_ve_su_almacen_aunque_pida_otro` falla con el almacén ajeno, revisa que la ruta lleve el middleware `scope.warehouse` y que este sobrescriba también la query string.

- [ ] **Step 8: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat(metrics): agregados de ventas por periodo diario, semanal y mensual"
```

---

## Task 12: Métricas de ventas — serie, top, vendedores, comparativa y recorte por rol

**Files:**
- Create: `app/Modules/Metrics/Support/MetricsRoleFilter.php`
- Modify: `app/Modules/Metrics/Support/SalesMetricsReporter.php`, `app/Modules/Metrics/Http/Controllers/SalesMetricsController.php`
- Test: `tests/Feature/Metrics/SalesMetricsDetailTest.php`, `tests/Feature/Metrics/MetricsRoleFilterTest.php`

**Interfaces:**
- Consumes: `SalesMetricsReporter::agregados()` y `::ventasEn()` (Task 11).
- Produces:
  - `SalesMetricsReporter::report()` devuelve además:
    - `serie`: `list<array{etiqueta: string, ingresos: string, numero_ventas: int}>` — 24 puntos por hora en `daily`, 7 por día en `weekly`, uno por día del mes en `monthly`. Incluye los puntos con cero.
    - `top_productos`: `array{por_unidades: list<array{product_id: int, nombre: string, unidades: string}>, por_ingresos: list<array{product_id: int, nombre: string, ingresos: string}>}` — 10 como máximo cada una.
    - `ventas_por_vendedor`: `list<array{user_id: int, nombre: string, ingresos: string, numero_ventas: int}>`.
    - `comparativa`: `array{ingresos_anterior: string, numero_ventas_anterior: int, variacion_ingresos: float|null, variacion_numero_ventas: float|null}` — variación en % con dos decimales; `null` cuando el periodo anterior fue cero.
  - `MetricsRoleFilter::filter(array $report, User $user): array` — devuelve el informe recortado según el rol.

- [ ] **Step 1: Escribir los tests del detalle de las métricas**

`tests/Feature/Metrics/SalesMetricsDetailTest.php`:

```php
<?php

namespace Tests\Feature\Metrics;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalesMetricsDetailTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $almacen;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-11 12:00:00');
        $this->almacen = Warehouse::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function venta(User $usuario, Product $product, float $cantidadBase, string $cuando): Sale
    {
        $subtotal = round($product->precio_venta * $cantidadBase, 2);

        $sale = Sale::factory()->for($this->almacen)->for($usuario)->create([
            'total' => $subtotal,
            'created_at' => $cuando,
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'unit_id' => $product->baseProductUnit()->unit_id,
            'cantidad' => $cantidadBase,
            'cantidad_base' => $cantidadBase,
            'precio_venta_unit' => $product->precio_venta,
            'precio_compra_unit' => $product->precio_compra,
            'subtotal' => $subtotal,
        ]);

        return $sale;
    }

    public function test_la_serie_diaria_tiene_24_puntos_por_hora(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);
        $this->venta($admin, $product, 5, '2026-03-11 09:30:00');  // 10.00
        $this->venta($admin, $product, 10, '2026-03-11 09:45:00'); // 20.00

        $response = $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')->assertOk();

        $this->assertCount(24, $response->json('data.serie'));
        $this->assertSame('09', $response->json('data.serie.9.etiqueta'));
        $this->assertSame('30.00', $response->json('data.serie.9.ingresos'));
        $this->assertSame(2, $response->json('data.serie.9.numero_ventas'));
        $this->assertSame('0.00', $response->json('data.serie.0.ingresos'));
    }

    public function test_la_serie_semanal_tiene_7_puntos_por_dia(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);
        $this->venta($admin, $product, 5, '2026-03-09 09:00:00'); // lunes

        $response = $this->getJson('/v1/metrics/sales?period=weekly&date=2026-03-11')->assertOk();

        $this->assertCount(7, $response->json('data.serie'));
        $this->assertSame('2026-03-09', $response->json('data.serie.0.etiqueta'));
        $this->assertSame('10.00', $response->json('data.serie.0.ingresos'));
    }

    public function test_la_serie_mensual_tiene_un_punto_por_dia_del_mes(): void
    {
        $this->actingAsRole('admin');

        $response = $this->getJson('/v1/metrics/sales?period=monthly&date=2026-03-11')->assertOk();

        $this->assertCount(31, $response->json('data.serie'));
        $this->assertSame('2026-03-01', $response->json('data.serie.0.etiqueta'));
    }

    public function test_el_top_de_productos_ordena_por_unidades_y_por_ingresos(): void
    {
        $admin = $this->actingAsRole('admin');
        $barato = Product::factory()->create(['nombre' => 'Agua', 'precio_compra' => 0.10, 'precio_venta' => 1.00]);
        $caro = Product::factory()->create(['nombre' => 'Vino', 'precio_compra' => 5.00, 'precio_venta' => 20.00]);

        $this->venta($admin, $barato, 100, '2026-03-11 09:00:00'); // 100 unidades, 100.00
        $this->venta($admin, $caro, 10, '2026-03-11 10:00:00');    // 10 unidades, 200.00

        $response = $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')->assertOk();

        $this->assertSame('Agua', $response->json('data.top_productos.por_unidades.0.nombre'));
        $this->assertSame('100.000', $response->json('data.top_productos.por_unidades.0.unidades'));
        $this->assertSame('Vino', $response->json('data.top_productos.por_ingresos.0.nombre'));
        $this->assertSame('200.00', $response->json('data.top_productos.por_ingresos.0.ingresos'));
    }

    public function test_las_ventas_por_vendedor_se_desglosan_por_usuario(): void
    {
        $admin = $this->actingAsRole('admin');
        $ana = User::factory()->create(['name' => 'Ana', 'warehouse_id' => $this->almacen->id]);
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);

        $this->venta($ana, $product, 10, '2026-03-11 09:00:00'); // 20.00
        $this->venta($ana, $product, 5, '2026-03-11 10:00:00');  // 10.00
        $this->venta($admin, $product, 1, '2026-03-11 11:00:00'); // 2.00

        $response = $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')->assertOk();

        $porVendedor = collect($response->json('data.ventas_por_vendedor'))->keyBy('user_id');
        $this->assertSame('30.00', $porVendedor[$ana->id]['ingresos']);
        $this->assertSame(2, $porVendedor[$ana->id]['numero_ventas']);
        $this->assertSame('2.00', $porVendedor[$admin->id]['ingresos']);
    }

    public function test_la_comparativa_calcula_la_variacion_frente_al_periodo_anterior(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);

        $this->venta($admin, $product, 50, '2026-03-10 09:00:00'); // ayer: 100.00
        $this->venta($admin, $product, 75, '2026-03-11 09:00:00'); // hoy: 150.00

        $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')
            ->assertOk()
            ->assertJsonPath('data.comparativa.ingresos_anterior', '100.00')
            ->assertJsonPath('data.comparativa.variacion_ingresos', 50.0)
            ->assertJsonPath('data.comparativa.variacion_numero_ventas', 0.0);
    }

    public function test_si_el_periodo_anterior_no_tuvo_ventas_la_variacion_es_null(): void
    {
        $admin = $this->actingAsRole('admin');
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);
        $this->venta($admin, $product, 10, '2026-03-11 09:00:00');

        $this->getJson('/v1/metrics/sales?period=daily&date=2026-03-11')
            ->assertOk()
            ->assertJsonPath('data.comparativa.ingresos_anterior', '0.00')
            ->assertJsonPath('data.comparativa.variacion_ingresos', null);
    }
}
```

- [ ] **Step 2: Escribir los tests del recorte por rol**

`tests/Feature/Metrics/MetricsRoleFilterTest.php`:

```php
<?php

namespace Tests\Feature\Metrics;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MetricsRoleFilterTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $almacen;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-03-11 12:00:00');
        $this->almacen = Warehouse::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function venta(User $usuario, Product $product, float $cantidadBase, string $cuando): void
    {
        $subtotal = round($product->precio_venta * $cantidadBase, 2);

        $sale = Sale::factory()->for($this->almacen)->for($usuario)->create([
            'total' => $subtotal, 'created_at' => $cuando,
        ]);

        $sale->items()->create([
            'product_id' => $product->id,
            'unit_id' => $product->baseProductUnit()->unit_id,
            'cantidad' => $cantidadBase,
            'cantidad_base' => $cantidadBase,
            'precio_venta_unit' => $product->precio_venta,
            'precio_compra_unit' => $product->precio_compra,
            'subtotal' => $subtotal,
        ]);
    }

    public function test_el_admin_recibe_el_informe_completo(): void
    {
        $this->actingAsRole('admin');

        $this->getJson('/v1/metrics/sales?period=weekly')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'ingresos', 'numero_ventas', 'unidades_vendidas', 'ticket_promedio',
                'ganancia', 'top_productos', 'ventas_por_vendedor', 'comparativa', 'serie',
            ]]);
    }

    public function test_el_vendedor_no_recibe_ganancia_top_ni_comparativa(): void
    {
        $vendedor = $this->actingAsRole('vendedor', ['warehouse_id' => $this->almacen->id]);

        $data = $this->getJson('/v1/metrics/sales?period=weekly')->assertOk()->json('data');

        $this->assertArrayHasKey('ingresos', $data);
        $this->assertArrayHasKey('ticket_promedio', $data);
        $this->assertArrayHasKey('serie', $data);
        $this->assertArrayNotHasKey('ganancia', $data);
        $this->assertArrayNotHasKey('top_productos', $data);
        $this->assertArrayNotHasKey('comparativa', $data);
    }

    public function test_el_vendedor_solo_ve_sus_propias_ventas_en_el_desglose_por_vendedor(): void
    {
        $product = Product::factory()->create(['precio_compra' => 1.00, 'precio_venta' => 2.00]);
        $otro = User::factory()->create(['name' => 'Otro', 'warehouse_id' => $this->almacen->id]);
        $this->venta($otro, $product, 50, '2026-03-11 09:00:00'); // 100.00

        $vendedor = $this->actingAsRole('vendedor', ['warehouse_id' => $this->almacen->id]);
        $this->venta($vendedor, $product, 5, '2026-03-11 10:00:00'); // 10.00

        $data = $this->getJson('/v1/metrics/sales?period=weekly')->assertOk()->json('data');

        // Los totales del almacén sí incluyen las ventas de sus compañeros.
        $this->assertSame('110.00', $data['ingresos']);
        // El desglose por vendedor, no.
        $this->assertCount(1, $data['ventas_por_vendedor']);
        $this->assertSame($vendedor->id, $data['ventas_por_vendedor'][0]['user_id']);
        $this->assertSame('10.00', $data['ventas_por_vendedor'][0]['ingresos']);
    }

    public function test_ninguna_respuesta_al_vendedor_contiene_precio_de_compra(): void
    {
        $product = Product::factory()->create(['precio_compra' => 7.77, 'precio_venta' => 20.00]);
        $vendedor = $this->actingAsRole('vendedor', ['warehouse_id' => $this->almacen->id]);
        $this->venta($vendedor, $product, 3, '2026-03-11 10:00:00');

        $response = $this->getJson('/v1/metrics/sales?period=weekly')->assertOk();

        $this->assertStringNotContainsString('7.77', $response->getContent());
        $this->assertStringNotContainsString('precio_compra', $response->getContent());
    }
}
```

- [ ] **Step 3: Ejecutar los tests para verificar que fallan**

Run: `php artisan test --filter="SalesMetricsDetailTest|MetricsRoleFilterTest"`
Expected: FAIL — `data.serie` no existe en la respuesta.

- [ ] **Step 4: Ampliar el `SalesMetricsReporter`**

Sustituir el método `report()` y añadir los cuatro métodos nuevos:

```php
    /**
     * @return array<string, mixed>
     */
    public function report(Period $period, CarbonInterface $fecha, ?int $warehouseId): array
    {
        [$desde, $hasta] = $period->rango($fecha);
        [$desdeAnterior, $hastaAnterior] = $period->rangoAnterior($fecha);

        $actual = $this->agregados($desde, $hasta, $warehouseId);
        $anterior = $this->agregados($desdeAnterior, $hastaAnterior, $warehouseId);

        return [
            'periodo' => $period->value,
            'desde' => $desde->toDateTimeString(),
            'hasta' => $hasta->toDateTimeString(),
            'warehouse_id' => $warehouseId,
            ...$actual,
            'serie' => $this->serie($period, $desde, $hasta, $warehouseId),
            'top_productos' => $this->topProductos($desde, $hasta, $warehouseId),
            'ventas_por_vendedor' => $this->ventasPorVendedor($desde, $hasta, $warehouseId),
            'comparativa' => [
                'ingresos_anterior' => $anterior['ingresos'],
                'numero_ventas_anterior' => $anterior['numero_ventas'],
                'variacion_ingresos' => $this->variacion((float) $anterior['ingresos'], (float) $actual['ingresos']),
                'variacion_numero_ventas' => $this->variacion((float) $anterior['numero_ventas'], (float) $actual['numero_ventas']),
            ],
        ];
    }

    /**
     * Serie de tiempo del periodo, con los puntos vacíos incluidos para que el
     * panel pueda graficar sin rellenar huecos.
     *
     * @return list<array{etiqueta: string, ingresos: string, numero_ventas: int}>
     */
    private function serie(Period $period, CarbonImmutable $desde, CarbonImmutable $hasta, ?int $warehouseId): array
    {
        $ventas = $this->ventasEn($desde, $hasta, $warehouseId)->get(['created_at', 'total']);

        $puntos = [];
        $cursor = $desde;
        $paso = $period === Period::Daily ? 'addHour' : 'addDay';
        $formato = $period === Period::Daily ? 'H' : 'Y-m-d';

        while ($cursor < $hasta) {
            $puntos[$cursor->format($formato)] = ['ingresos' => 0.0, 'numero_ventas' => 0];
            $cursor = $cursor->{$paso}();
        }

        foreach ($ventas as $venta) {
            $clave = CarbonImmutable::instance($venta->created_at)->format($formato);

            if (! isset($puntos[$clave])) {
                continue;
            }

            $puntos[$clave]['ingresos'] += (float) $venta->total;
            $puntos[$clave]['numero_ventas']++;
        }

        $serie = [];

        foreach ($puntos as $etiqueta => $valores) {
            $serie[] = [
                'etiqueta' => (string) $etiqueta,
                'ingresos' => number_format($valores['ingresos'], 2, '.', ''),
                'numero_ventas' => $valores['numero_ventas'],
            ];
        }

        return $serie;
    }

    /**
     * @return array{por_unidades: list<array<string, mixed>>, por_ingresos: list<array<string, mixed>>}
     */
    private function topProductos(CarbonImmutable $desde, CarbonImmutable $hasta, ?int $warehouseId): array
    {
        $base = fn (): \Illuminate\Database\Eloquent\Builder => SaleItem::query()
            ->whereIn('sale_id', $this->ventasEn($desde, $hasta, $warehouseId)->select('sales.id'))
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->groupBy('sale_items.product_id', 'products.nombre')
            ->limit(10);

        $porUnidades = $base()
            ->selectRaw('sale_items.product_id, products.nombre, SUM(sale_items.cantidad_base) as unidades')
            ->orderByDesc('unidades')
            ->get()
            ->map(fn ($fila): array => [
                'product_id' => (int) $fila->product_id,
                'nombre' => $fila->nombre,
                'unidades' => number_format((float) $fila->unidades, 3, '.', ''),
            ])->all();

        $porIngresos = $base()
            ->selectRaw('sale_items.product_id, products.nombre, SUM(sale_items.subtotal) as ingresos')
            ->orderByDesc('ingresos')
            ->get()
            ->map(fn ($fila): array => [
                'product_id' => (int) $fila->product_id,
                'nombre' => $fila->nombre,
                'ingresos' => number_format((float) $fila->ingresos, 2, '.', ''),
            ])->all();

        return ['por_unidades' => $porUnidades, 'por_ingresos' => $porIngresos];
    }

    /**
     * @return list<array{user_id: int, nombre: string, ingresos: string, numero_ventas: int}>
     */
    private function ventasPorVendedor(CarbonImmutable $desde, CarbonImmutable $hasta, ?int $warehouseId): array
    {
        return $this->ventasEn($desde, $hasta, $warehouseId)
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->groupBy('sales.user_id', 'users.name')
            ->selectRaw('sales.user_id, users.name, SUM(sales.total) as ingresos, COUNT(*) as numero_ventas')
            ->orderByDesc('ingresos')
            ->get()
            ->map(fn ($fila): array => [
                'user_id' => (int) $fila->user_id,
                'nombre' => $fila->name,
                'ingresos' => number_format((float) $fila->ingresos, 2, '.', ''),
                'numero_ventas' => (int) $fila->numero_ventas,
            ])->all();
    }

    /** Variación porcentual; `null` si el periodo anterior fue cero (no hay base de comparación). */
    private function variacion(float $anterior, float $actual): ?float
    {
        if (abs($anterior) < 0.0001) {
            return null;
        }

        return round((($actual - $anterior) / $anterior) * 100, 2);
    }
```

- [ ] **Step 5: Escribir el `MetricsRoleFilter`**

`app/Modules/Metrics/Support/MetricsRoleFilter.php`:

```php
<?php

namespace App\Modules\Metrics\Support;

use App\Models\User;

/**
 * Recorta el informe según el rol.
 *
 * Filtrar después de calcular mantiene el cálculo en un solo sitio; lo que el
 * vendedor no puede ver se elimina aquí, en un único punto auditable.
 */
class MetricsRoleFilter
{
    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public function filter(array $report, User $user): array
    {
        if ($user->isAdmin()) {
            return $report;
        }

        // La ganancia y el top de productos exponen el precio de compra o
        // información de negocio que el vendedor no debe ver (spec §8.3).
        unset($report['ganancia'], $report['top_productos'], $report['comparativa']);

        $report['ventas_por_vendedor'] = array_values(array_filter(
            $report['ventas_por_vendedor'],
            fn (array $fila): bool => $fila['user_id'] === $user->id,
        ));

        return $report;
    }
}
```

Aplicarlo en `SalesMetricsController::__invoke()`:

```php
    public function __invoke(
        SalesMetricsRequest $request,
        SalesMetricsReporter $reporter,
        MetricsRoleFilter $filter,
    ): JsonResponse {
        // ...igual hasta calcular $warehouseId...

        $report = $reporter->report($period, $fecha, $warehouseId);

        return new JsonResponse(['data' => $filter->filter($report, $user)]);
    }
```

(con `use App\Modules\Metrics\Support\MetricsRoleFilter;`).

- [ ] **Step 6: Ejecutar los tests**

Run: `php artisan test --filter="SalesMetricsDetailTest|MetricsRoleFilterTest"`
Expected: PASS (11 tests).

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat(metrics): serie de tiempo, top productos, ventas por vendedor, comparativa y recorte por rol"
```

---

## Task 13: Métricas de inventario

**Files:**
- Create: `app/Modules/Metrics/Support/InventoryMetricsReporter.php`, `app/Modules/Metrics/Http/Controllers/InventoryMetricsController.php`, `app/Modules/Metrics/Http/Requests/InventoryMetricsRequest.php`
- Modify: `app/Modules/Metrics/routes.php`
- Test: `tests/Feature/Metrics/InventoryMetricsTest.php`

**Interfaces:**
- Consumes: `Stock` (Task 8), `Product` (Task 7), permiso `metrics.full` (Task 2).
- Produces:
  - `InventoryMetricsReporter::report(?int $warehouseId, ?float $umbral): array` con `valor_inventario` (`list<array{warehouse_id: int, nombre: string, a_coste: string, a_venta: string}>`), `total_a_coste`, `total_a_venta` y `stock_bajo` (`list<array{warehouse_id: int, product_id: int, nombre: string, cantidad: string, minimo: string}>`).
  - `GET /v1/metrics/inventory?warehouse_id=&umbral=`.

- [ ] **Step 1: Escribir los tests**

`tests/Feature/Metrics/InventoryMetricsTest.php`:

```php
<?php

namespace Tests\Feature\Metrics;

use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_valor_del_inventario_se_calcula_a_coste_y_a_venta_por_almacen(): void
    {
        $this->actingAsRole('admin');
        $norte = Warehouse::factory()->create(['nombre' => 'Norte']);
        $sur = Warehouse::factory()->create(['nombre' => 'Sur']);
        $product = Product::factory()->create(['precio_compra' => 2.00, 'precio_venta' => 5.00]);

        Stock::factory()->for($product)->for($norte)->create(['cantidad' => 10]);
        Stock::factory()->for($product)->for($sur)->create(['cantidad' => 4]);

        $data = $this->getJson('/v1/metrics/inventory')->assertOk()->json('data');

        $porAlmacen = collect($data['valor_inventario'])->keyBy('warehouse_id');
        $this->assertSame('20.00', $porAlmacen[$norte->id]['a_coste']);
        $this->assertSame('50.00', $porAlmacen[$norte->id]['a_venta']);
        $this->assertSame('8.00', $porAlmacen[$sur->id]['a_coste']);
        $this->assertSame('28.00', $data['total_a_coste']);
        $this->assertSame('70.00', $data['total_a_venta']);
    }

    public function test_se_puede_filtrar_por_almacen(): void
    {
        $this->actingAsRole('admin');
        $norte = Warehouse::factory()->create();
        $sur = Warehouse::factory()->create();
        $product = Product::factory()->create(['precio_compra' => 2.00, 'precio_venta' => 5.00]);
        Stock::factory()->for($product)->for($norte)->create(['cantidad' => 10]);
        Stock::factory()->for($product)->for($sur)->create(['cantidad' => 4]);

        $data = $this->getJson("/v1/metrics/inventory?warehouse_id={$norte->id}")->assertOk()->json('data');

        $this->assertCount(1, $data['valor_inventario']);
        $this->assertSame('20.00', $data['total_a_coste']);
    }

    public function test_el_stock_bajo_usa_el_minimo_de_cada_fila(): void
    {
        $this->actingAsRole('admin');
        $almacen = Warehouse::factory()->create();
        $bajo = Product::factory()->create(['nombre' => 'Agua']);
        $sobrado = Product::factory()->create(['nombre' => 'Vino']);

        Stock::factory()->for($bajo)->for($almacen)->create(['cantidad' => 3, 'minimo' => 10]);
        Stock::factory()->for($sobrado)->for($almacen)->create(['cantidad' => 80, 'minimo' => 10]);

        $data = $this->getJson('/v1/metrics/inventory')->assertOk()->json('data');

        $this->assertCount(1, $data['stock_bajo']);
        $this->assertSame('Agua', $data['stock_bajo'][0]['nombre']);
        $this->assertSame('3.000', $data['stock_bajo'][0]['cantidad']);
    }

    public function test_el_stock_bajo_incluye_la_igualdad_con_el_minimo(): void
    {
        $this->actingAsRole('admin');
        $almacen = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Stock::factory()->for($product)->for($almacen)->create(['cantidad' => 10, 'minimo' => 10]);

        $this->getJson('/v1/metrics/inventory')->assertOk()->assertJsonCount(1, 'data.stock_bajo');
    }

    public function test_el_umbral_del_parametro_sustituye_al_minimo_de_cada_fila(): void
    {
        $this->actingAsRole('admin');
        $almacen = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Stock::factory()->for($product)->for($almacen)->create(['cantidad' => 30, 'minimo' => 0]);

        $this->getJson('/v1/metrics/inventory')->assertOk()->assertJsonCount(0, 'data.stock_bajo');
        $this->getJson('/v1/metrics/inventory?umbral=50')->assertOk()->assertJsonCount(1, 'data.stock_bajo');
    }

    public function test_el_stock_de_productos_eliminados_no_cuenta(): void
    {
        $this->actingAsRole('admin');
        $almacen = Warehouse::factory()->create();
        $product = Product::factory()->create(['precio_compra' => 2.00, 'precio_venta' => 5.00]);
        Stock::factory()->for($product)->for($almacen)->create(['cantidad' => 10]);
        $product->delete();

        $data = $this->getJson('/v1/metrics/inventory')->assertOk()->json('data');

        $this->assertSame('0.00', $data['total_a_coste']);
        $this->assertCount(0, $data['stock_bajo']);
    }

    public function test_el_vendedor_no_puede_ver_las_metricas_de_inventario(): void
    {
        $almacen = Warehouse::factory()->create();
        $this->actingAsRole('vendedor', ['warehouse_id' => $almacen->id]);

        $this->getJson('/v1/metrics/inventory')->assertForbidden();
    }
}
```

- [ ] **Step 2: Ejecutar los tests para verificar que fallan**

Run: `php artisan test --filter=InventoryMetricsTest`
Expected: FAIL — `/v1/metrics/inventory` devuelve 404.

- [ ] **Step 3: Escribir el `InventoryMetricsReporter`**

`app/Modules/Metrics/Support/InventoryMetricsReporter.php`:

```php
<?php

namespace App\Modules\Metrics\Support;

use App\Modules\Warehouses\Models\Stock;
use Illuminate\Database\Eloquent\Builder;

/**
 * Valor del inventario y productos bajo mínimo. Solo para el panel del admin:
 * expone el precio de compra.
 */
class InventoryMetricsReporter
{
    /**
     * @param  float|null  $umbral  si se indica, sustituye al mínimo de cada fila
     * @return array<string, mixed>
     */
    public function report(?int $warehouseId, ?float $umbral): array
    {
        $valor = $this->stocksVivos($warehouseId)
            ->join('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->groupBy('stocks.warehouse_id', 'warehouses.nombre')
            ->selectRaw('stocks.warehouse_id, warehouses.nombre')
            ->selectRaw('SUM(stocks.cantidad * products.precio_compra) as a_coste')
            ->selectRaw('SUM(stocks.cantidad * products.precio_venta) as a_venta')
            ->get();

        $stockBajo = $this->stocksVivos($warehouseId)
            ->when(
                $umbral !== null,
                fn (Builder $q) => $q->where('stocks.cantidad', '<=', $umbral),
                fn (Builder $q) => $q->whereColumn('stocks.cantidad', '<=', 'stocks.minimo'),
            )
            ->orderBy('stocks.cantidad')
            ->get(['stocks.warehouse_id', 'stocks.product_id', 'products.nombre', 'stocks.cantidad', 'stocks.minimo']);

        return [
            'valor_inventario' => $valor->map(fn ($fila): array => [
                'warehouse_id' => (int) $fila->warehouse_id,
                'nombre' => $fila->nombre,
                'a_coste' => number_format((float) $fila->a_coste, 2, '.', ''),
                'a_venta' => number_format((float) $fila->a_venta, 2, '.', ''),
            ])->all(),
            'total_a_coste' => number_format((float) $valor->sum('a_coste'), 2, '.', ''),
            'total_a_venta' => number_format((float) $valor->sum('a_venta'), 2, '.', ''),
            'stock_bajo' => $stockBajo->map(fn ($fila): array => [
                'warehouse_id' => (int) $fila->warehouse_id,
                'product_id' => (int) $fila->product_id,
                'nombre' => $fila->nombre,
                'cantidad' => number_format((float) $fila->cantidad, 3, '.', ''),
                'minimo' => number_format((float) $fila->minimo, 3, '.', ''),
            ])->all(),
        ];
    }

    /**
     * Stock de productos no eliminados. El `join` con `products` filtra por
     * `deleted_at` para que el borrado lógico no infle el valor del inventario.
     *
     * @return Builder<Stock>
     */
    private function stocksVivos(?int $warehouseId): Builder
    {
        return Stock::query()
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereNull('products.deleted_at')
            ->when($warehouseId !== null, fn (Builder $q) => $q->where('stocks.warehouse_id', $warehouseId));
    }
}
```

- [ ] **Step 4: Request, Controller y ruta**

`app/Modules/Metrics/Http/Requests/InventoryMetricsRequest.php`:

```php
<?php

namespace App\Modules\Metrics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['sometimes', 'nullable', 'integer', 'exists:warehouses,id'],
            'umbral' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
```

`app/Modules/Metrics/Http/Controllers/InventoryMetricsController.php`:

```php
<?php

namespace App\Modules\Metrics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Metrics\Http\Requests\InventoryMetricsRequest;
use App\Modules\Metrics\Support\InventoryMetricsReporter;
use Illuminate\Http\JsonResponse;

/**
 * @group Métricas · Inventario
 *
 * @authenticated
 */
class InventoryMetricsController extends Controller
{
    /** Valor del inventario y productos bajo mínimo (solo admin). */
    public function __invoke(InventoryMetricsRequest $request, InventoryMetricsReporter $reporter): JsonResponse
    {
        return new JsonResponse(['data' => $reporter->report(
            $request->filled('warehouse_id') ? (int) $request->validated('warehouse_id') : null,
            $request->filled('umbral') ? (float) $request->validated('umbral') : null,
        )]);
    }
}
```

En `app/Modules/Metrics/routes.php`, **fuera** del grupo con `scope.warehouse` (esta ruta es solo de admin, no necesita el forzado):

```php
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('metrics/inventory', InventoryMetricsController::class)->middleware('can:metrics.full');
});
```

- [ ] **Step 5: Ejecutar los tests**

Run: `php artisan test --filter=InventoryMetricsTest`
Expected: PASS (7 tests).

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/pint --dirty
git add -A
git commit -m "feat(metrics): valor de inventario y productos bajo mínimo"
```

---

## Task 14: Seeder de demostración, documentación y cierre

**Files:**
- Create: `database/seeders/DemoSeeder.php`, `README.md`, `docs/funcional/README.md`, `docs/funcional/{access,catalog,warehouses,sales,metrics,audit}.md`, `CLAUDE.md`
- Modify: `config/scribe.php`
- Test: `tests/Feature/DemoSeederTest.php`

**Interfaces:**
- Consumes: todo lo anterior.
- Produces: `php artisan migrate:fresh --seed` deja una base usable; `php artisan scribe:generate` produce la doc en `/docs`.

- [ ] **Step 1: Escribir el test del seeder**

`tests/Feature/DemoSeederTest.php`:

```php
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
```

- [ ] **Step 2: Ejecutar el test para verificar que falla**

Run: `php artisan test --filter=DemoSeederTest`
Expected: FAIL — `DemoSeeder` no existe.

- [ ] **Step 3: Escribir el `DemoSeeder`**

`database/seeders/DemoSeeder.php`:

```php
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
```

El "Vino tinto crianza" nace con 36 unidades y mínimo 40 para que `GET /v1/metrics/inventory` devuelva un caso de stock bajo desde el primer arranque.

- [ ] **Step 4: Ejecutar el test del seeder**

Run: `php artisan test --filter=DemoSeederTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Configurar Scribe y generar la documentación**

En `config/scribe.php`:

```php
    'title' => 'API almacén — documentación',
    'type' => 'laravel',
    'theme' => 'elements',
    'routes' => [
        [
            'match' => ['prefixes' => ['v1/*'], 'domains' => ['*']],
        ],
    ],
    'auth' => [
        'enabled' => true,
        'in' => 'bearer',
        'name' => 'Authorization',
    ],
```

```bash
php artisan serve &
php artisan scribe:generate
```

Verificar que `http://localhost:8000/docs` abre y lista los grupos: Acceso, Almacenes, Catálogo, Ventas, Métricas y Auditoría. Parar el servidor.

- [ ] **Step 6: Escribir el `README.md`**

```markdown
# almacen-lite

API REST de gestión de almacenes: inventario por almacén, ventas con descuento
automático de stock, transferencias, métricas y auditoría.

Copia reducida de `almacen-backend` (WMS+ERP completo): 6 módulos y 11 tablas
frente a 18 módulos y 94 migraciones. Sin multi-empresa, sin ubicaciones, sin
lotes ni ERP.

## Puesta en marcha

```bash
composer install
cp .env.example .env && php artisan key:generate
# crear el esquema `almacen_lite` en MySQL (127.0.0.1:3310)
php artisan migrate --seed
php artisan serve
```

Usuarios de demostración (`DemoSeeder`), ambos con contraseña `secreto123`:

| Email | Rol |
|---|---|
| `admin@almacen.test` | admin |
| `vendedor@almacen.test` | vendedor (Almacén Central) |

## Uso

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/v1/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@almacen.test","password":"secreto123"}' | jq -r .token)

curl -s http://localhost:8000/v1/products -H "Authorization: Bearer $TOKEN"
```

## Comandos

```bash
php artisan test              # toda la suite (SQLite en memoria)
php artisan test --compact    # salida breve
vendor/bin/pint               # formateo
php artisan scribe:generate   # regenerar la doc de la API en /docs
```

## Documentación

- **Diseño:** [`docs/superpowers/specs/2026-08-08-almacen-lite-design.md`](docs/superpowers/specs/2026-08-08-almacen-lite-design.md)
- **Plan de implementación:** [`docs/superpowers/plans/2026-08-08-almacen-lite.md`](docs/superpowers/plans/2026-08-08-almacen-lite.md)
- **Guía funcional por módulo:** [`docs/funcional/README.md`](docs/funcional/README.md)
- **Referencia de endpoints:** `http://localhost:8000/docs` (Scribe)

## Roles

| | admin | vendedor |
|---|---|---|
| Usuarios, almacenes, unidades, productos, transferencias | ✔ | ✘ |
| Vender | ✔ | Solo en su almacén |
| Métricas | Los tres periodos, global y por almacén | Solo `weekly` y solo su almacén |
| Ganancia, top productos, comparativa, inventario | ✔ | ✘ |
| Auditoría | ✔ | ✘ |
```

- [ ] **Step 7: Escribir la documentación funcional**

Un fichero por módulo en `docs/funcional/`, todos con la misma plantilla de cinco secciones (**¿Para qué sirve? · Conceptos clave · Flujos de uso · Cómo lo usa el frontend · Qué no hace todavía**), como en `almacen-backend`.

`docs/funcional/README.md` es el índice:

```markdown
# Documentación funcional

Guía por módulo para cliente y equipo de frontend. Complementa —no reemplaza—
la referencia técnica de endpoints en `/docs` (Scribe).

| Módulo | Qué cubre |
|---|---|
| [Acceso](access.md) | Login por token, usuarios, roles admin y vendedor |
| [Almacenes](warehouses.md) | Almacenes, stock por almacén, transferencias |
| [Catálogo](catalog.md) | Productos, unidades y factores de conversión |
| [Ventas](sales.md) | Registro de ventas y descuento de stock |
| [Métricas](metrics.md) | Métricas de ventas por periodo e inventario |
| [Auditoría](audit.md) | Quién hizo qué y cuándo sobre productos y traslados |
```

Como referencia de tono y extensión, `docs/funcional/sales.md`:

```markdown
# Ventas

## 1. ¿Para qué sirve?

Registrar lo que se vende en cada almacén y mantener el inventario al día sin
un paso manual de descuento. Una venta puede llevar varios productos y devuelve
el importe total en la misma respuesta, para imprimir el ticket sin una segunda
llamada.

## 2. Conceptos clave

- **La venta es de un almacén.** El vendedor vende siempre en el suyo; el admin
  indica cuál.
- **Unidad de venta.** Cada línea puede venderse en cualquier unidad asignada al
  producto. La API convierte a unidad base (`cantidad × factor`) y descuenta ese
  valor: 2 cajas de 24 descuentan 48.
- **Todo o nada.** Si un solo producto no tiene stock suficiente, se rechaza la
  venta entera y el inventario queda intacto. La respuesta lista qué productos
  fallaron, cuánto se pedía y cuánto había.
- **Snapshot de precios.** Cada línea guarda el precio de venta y el de compra
  del momento, así que cambiar la tarifa mañana no altera el histórico ni las
  métricas de ayer.

## 3. Flujos de uso

1. El vendedor entra (`POST /v1/login`) y lista productos (`GET /v1/products`),
   donde ve nombre, precio de venta y la cantidad de su almacén.
2. Registra la venta (`POST /v1/sales`) con las líneas.
3. Si algo no tiene stock, recibe `422` con `productos_afectados` y corrige.
4. Consulta lo vendido (`GET /v1/sales`) y sus métricas semanales
   (`GET /v1/metrics/sales?period=weekly`).

## 4. Cómo lo usa el frontend

| Acción | Método y ruta | Permiso |
|---|---|---|
| Registrar venta | `POST /v1/sales` | `sales.create` |
| Listar ventas | `GET /v1/sales` | `sales.view` |
| Ver una venta | `GET /v1/sales/{id}` | `sales.view` |

El cuerpo del alta:

```json
{
  "warehouse_id": 1,
  "items": [
    {"product_id": 7, "cantidad": 3},
    {"product_id": 9, "unit_id": 2, "cantidad": 2}
  ]
}
```

`warehouse_id` es obligatorio para el admin; en el vendedor se ignora y se usa
siempre el suyo. `unit_id` es opcional: si falta, se vende en unidad base.

## 5. Qué no hace todavía

- No hay clientes ni facturación: la venta es un ticket interno.
- No hay devoluciones ni anulación de ventas.
- No hay descuentos, impuestos ni formas de pago.
- El stock es un número por almacén: sin lotes, caducidades ni ubicaciones.
```

Escribir los otros cinco (`access.md`, `warehouses.md`, `catalog.md`,
`metrics.md`, `audit.md`) con la misma estructura, cubriendo respectivamente:
login y gestión de usuarios; almacenes, stock y transferencias; productos y
unidades; los dos endpoints de métricas con la tabla de visibilidad por rol; y
qué acciones se auditan y cómo consultarlas.

- [ ] **Step 8: Escribir el `CLAUDE.md`**

Guía corta para futuras sesiones: qué es el proyecto, stack, comandos
(`php artisan test`, `vendor/bin/pint`, `scribe:generate`), la estructura de
módulos, las reglas de dominio críticas (unidad base con factor 1, venta todo o
nada, snapshots de precio, el vendedor nunca ve `precio_compra`), y el flujo de
trabajo (spec → plan → TDD). Referenciar el spec y el plan en lugar de repetir
su contenido.

- [ ] **Step 9: Verificación final**

```bash
php artisan test
vendor/bin/pint --test
php artisan migrate:fresh --seed
php artisan route:list --path=v1
```

Expected: suite en verde, Pint sin cambios pendientes, migraciones y seeders sin
error, y el listado de rutas mostrando exactamente los endpoints de la §6 del
spec.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "docs: README, guía funcional por módulo y seeder de demostración"
```

---

## Verificación de cobertura del spec

| Sección del spec | Tareas que la implementan |
|---|---|
| §2 Stack y configuración | 1 |
| §3 Arquitectura modular | 1, y cada tarea añade su módulo |
| §4 Modelo de datos (11 tablas) | 3 (`warehouses`), 4 (`users.warehouse_id`), 5 (`audit_logs`), 6 (`units`), 7 (`products`, `product_units`), 8 (`stocks`), 9 (`sales`, `sale_items`), 10 (`transfers`) |
| §4.1 Catálogo global + stock por almacén | 7, 8 |
| §4.1 Factor en `units`, unidad base factor 1 | 6, 7 |
| §4.1 Snapshots de precio | 9 |
| §5 Roles, permisos e invariantes | 2, 4 |
| §5 regla 3 (alcance del vendedor) | 4 (middleware), 9 (ventas), 11 (métricas) |
| §5 regla 4 (vendedor solo `weekly`) | 11 |
| §5 regla 5 (vendedor sin `precio_compra`) | 7 (`ProductResource`), 9 (`SaleItemResource`), 12 (`MetricsRoleFilter`) |
| §6 Endpoints | 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 13 |
| §6 Visibilidad de producto por rol | 7, 8 |
| §6 Contrato de error de stock | 9 |
| §7.1 Registrar venta | 9 |
| §7.2 Alta de producto y stock inicial | 7, 8 |
| §7.3 Transferencia | 10 |
| §7.4 Borrado lógico y guardas de borrado | 7 (productos, unidades), 8 (almacenes), 9 (almacenes con ventas) |
| §7.5 Auditoría | 5, invocada en 7, 8 y 10 |
| §8.1 Periodos y ventanas | 11 |
| §8.2 Métricas 1–5 | 11 |
| §8.2 Métricas 6–9 (top, vendedores, comparativa, serie) | 12 |
| §8.3 Recorte por rol | 12 |
| §8.4 Métricas de inventario | 13 |
| §9 Estrategia de pruebas | Todas: cada tarea abre con sus tests |
| §10 Riesgos y limitaciones | Documentados en el README y en `docs/funcional/*` |

## Orden de dependencias

```
1 esqueleto
└── 2 access (roles, login)
    ├── 3 warehouses
    │   └── 4 users + scope.warehouse
    ├── 5 audit
    └── 6 units
        └── 7 products (necesita 5 y 6)
            └── 8 stocks (necesita 3 y 7)
                ├── 9 sales (necesita 4 y 8)
                │   ├── 10 transfers (necesita 5, 8 y la excepción de 9)
                │   └── 11 metrics base (necesita 4 y 9)
                │       ├── 12 metrics detalle
                │       └── 13 metrics inventario (necesita 8)
                └── 14 docs y seeder (al final)
```

Las tareas 3 y 5 y 6 no dependen entre sí: pueden ir en paralelo tras la 2.
Igual las 12 y 13 tras la 11.
