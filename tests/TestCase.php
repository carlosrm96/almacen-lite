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
