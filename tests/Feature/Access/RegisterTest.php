<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registro público, calcado del de `almacen-backend` (`POST /v1/auth/register`)
 * pero sin multi-empresa: allí cada registro crea su propia empresa aislada,
 * aquí solo hay una instalación, así que el registro es la puesta en marcha —
 * el primero que llega es el admin dueño y después la puerta se cierra.
 */
class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $payload = [
        'name' => 'Ana',
        'email' => 'ana@almacen.test',
        'password' => 'secreto123',
        'password_confirmation' => 'secreto123',
    ];

    public function test_el_primer_registro_crea_el_admin_de_la_instalacion(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->postJson('/v1/register', $this->payload)
            ->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'rol']])
            ->assertJsonPath('user.email', 'ana@almacen.test')
            ->assertJsonPath('user.rol', 'admin')
            ->assertJsonPath('user.warehouse_id', null);

        $this->assertTrue(User::firstWhere('email', 'ana@almacen.test')->isAdmin());
    }

    public function test_el_token_devuelto_autentica_peticiones(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $token = $this->postJson('/v1/register', $this->payload)
            ->assertCreated()
            ->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ana@almacen.test');
    }

    public function test_el_registro_se_cierra_en_cuanto_existe_un_usuario(): void
    {
        // Sin empresas que aíslen a cada registrado, un registro siempre
        // abierto convertiría a cualquiera en admin del almacén ajeno.
        $this->seed(RolesAndPermissionsSeeder::class);
        User::factory()->create();

        $this->postJson('/v1/register', $this->payload)->assertForbidden();

        $this->assertNull(User::firstWhere('email', 'ana@almacen.test'));
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
            'name' => 'Ana',
            'email' => 'ana@almacen.test',
            'password' => 'secreto123',
            'password_confirmation' => 'otra-cosa',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertSame(0, User::count());
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
