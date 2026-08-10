<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Usuario fijado con `actingAsRole`, si lo hay. Se vuelve a autenticar
     * tras cada petición simulada porque `call()` olvida los guards (ver
     * más abajo), de forma que las peticiones encadenadas dentro de un
     * mismo test sigan actuando como este usuario.
     */
    private ?User $actingAsRoleUser = null;

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

        $this->actingAsRoleUser = $user;

        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Envuelve cada petición HTTP simulada para que el guard de
     * autenticación se resuelva de cero en la siguiente petición, en
     * lugar de arrastrar el usuario que `Illuminate\Auth\RequestGuard`
     * cachea en la primera resolución.
     *
     * Sin esto, dos peticiones encadenadas en el mismo método de test
     * (p. ej. login → logout → me) comparten el mismo contenedor de
     * aplicación y, por tanto, el mismo guard ya resuelto: un token
     * revocado en la segunda petición seguiría "autenticado" porque el
     * guard nunca vuelve a evaluarlo. En una petición real esto no pasa
     * nunca, porque cada request arranca un contenedor nuevo.
     *
     * Si el test fijó un usuario con `actingAsRole`, se le vuelve a
     * autenticar tras el reinicio para que las siguientes peticiones del
     * mismo test lo sigan viendo como el usuario autenticado.
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);

        Auth::forgetGuards();

        if ($this->actingAsRoleUser !== null) {
            Sanctum::actingAs($this->actingAsRoleUser);
        }

        return $response;
    }
}
