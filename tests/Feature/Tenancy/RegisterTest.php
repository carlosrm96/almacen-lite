<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Modules\Catalog\Models\Currency;
use App\Modules\Tenancy\Models\Company;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registro público, como el de `almacen-backend`: cada registro crea su propia
 * empresa y a su admin dueño. Es lo que permite dejarlo abierto — un registrado
 * no aterriza en los almacenes de otro, sino en los suyos, vacíos.
 *
 * Ver docs/superpowers/specs/2026-08-17-multi-empresa-y-registro-design.md
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $payload = [
        'empresa' => 'Bodega La Habana',
        'name' => 'Ana',
        'email' => 'ana@almacen.test',
        'password' => 'secreto123',
        'password_confirmation' => 'secreto123',
    ];

    public function test_el_registro_crea_la_empresa_y_su_admin_dueno(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->postJson('/v1/register', $this->payload)
            ->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'rol'], 'company' => ['id', 'nombre']])
            ->assertJsonPath('user.email', 'ana@almacen.test')
            ->assertJsonPath('user.rol', 'admin')
            ->assertJsonPath('user.warehouse_id', null)
            ->assertJsonPath('company.nombre', 'Bodega La Habana');

        $user = User::withoutGlobalScopes()->firstWhere('email', 'ana@almacen.test');

        $this->assertTrue($user->isAdmin());
        $this->assertSame('Bodega La Habana', $user->company->nombre);
    }

    public function test_el_registro_es_repetible_y_cada_negocio_queda_aislado(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $primero = $this->postJson('/v1/register', $this->payload)->assertCreated();
        $segundo = $this->postJson('/v1/register', [
            ...$this->payload,
            'empresa' => 'Bodega Santiago',
            'email' => 'beto@almacen.test',
        ])->assertCreated();

        $this->assertNotSame($primero->json('company.id'), $segundo->json('company.id'));

        // Cada uno crea su almacén y no ve el del otro.
        $this->withToken($primero->json('token'))
            ->postJson('/v1/warehouses', ['nombre' => 'Central'])->assertCreated();
        $this->withToken($segundo->json('token'))
            ->postJson('/v1/warehouses', ['nombre' => 'Central'])->assertCreated();

        $this->withToken($segundo->json('token'))
            ->getJson('/v1/warehouses')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_el_negocio_nuevo_arranca_con_sus_propias_monedas(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $token = $this->postJson('/v1/register', $this->payload)->assertCreated()->json('token');

        $this->withToken($token)->getJson('/v1/currencies')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $company = Company::firstWhere('nombre', 'Bodega La Habana');
        $monedas = Currency::withoutGlobalScopes()->where('company_id', $company->id)->get();

        $this->assertEqualsCanonicalizing(['CUP', 'USD'], $monedas->pluck('codigo')->all());
        $this->assertSame(1.0, (float) $monedas->firstWhere('es_base', true)->tasa);
    }

    public function test_el_token_devuelto_autentica_peticiones(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $token = $this->postJson('/v1/register', $this->payload)->assertCreated()->json('token');

        $this->withToken($token)
            ->getJson('/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ana@almacen.test');
    }

    public function test_el_email_es_unico_en_toda_la_instalacion(): void
    {
        // Único global, no por empresa: el login ocurre antes de saber de qué
        // empresa es quien entra.
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->postJson('/v1/register', $this->payload)->assertCreated();

        $this->postJson('/v1/register', [...$this->payload, 'empresa' => 'Otro Negocio'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_el_nombre_del_negocio_es_obligatorio(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $payload = $this->payload;
        unset($payload['empresa']);

        $this->postJson('/v1/register', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('empresa');
    }

    public function test_el_registro_no_deja_elegir_rol_ni_almacen(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->postJson('/v1/register', $this->payload + ['rol' => 'vendedor', 'warehouse_id' => 1])
            ->assertCreated()
            ->assertJsonPath('user.rol', 'admin')
            ->assertJsonPath('user.warehouse_id', null);
    }

    public function test_la_contrasena_debe_confirmarse(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->postJson('/v1/register', [
            ...$this->payload,
            'password_confirmation' => 'otra-cosa',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertSame(0, User::withoutGlobalScopes()->count());
    }

    public function test_el_login_funciona_con_la_contrasena_registrada(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->postJson('/v1/register', $this->payload)->assertCreated();

        $this->postJson('/v1/login', [
            'email' => 'ana@almacen.test',
            'password' => 'secreto123',
        ])->assertOk()->assertJsonPath('user.rol', 'admin');
    }
}
