<?php

namespace Tests\Feature\Access;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
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
        $this->seed(RolesAndPermissionsSeeder::class);

        $vendedor = Role::findByName('vendedor');

        $this->assertTrue($vendedor->hasPermissionTo('sales.create'));
        $this->assertFalse($vendedor->hasPermissionTo('products.create'));
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('metrics.full'));
    }
}
