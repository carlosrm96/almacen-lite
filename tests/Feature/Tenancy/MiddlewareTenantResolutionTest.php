<?php

namespace Tests\Feature\Tenancy;

use App\Modules\Tenancy\Http\Middleware\ResolveCurrentCompany;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Support\CurrentCompany;
use App\Modules\Warehouses\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El aislamiento a través del middleware real ({@see ResolveCurrentCompany}),
 * autenticando con un token Bearer de verdad y sin fijar {@see CurrentCompany}
 * a mano.
 *
 * Los demás tests de aislamiento fijan el contexto en su arranque, y eso
 * enmascara un fallo de resolución en el propio middleware: pasarían aunque el
 * middleware no hiciera nada.
 */
class MiddlewareTenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> credenciales de un negocio recién registrado */
    private function negocio(string $nombre, string $email): array
    {
        $respuesta = $this->postJson('/v1/register', [
            'empresa' => $nombre,
            'name' => 'Dueño de '.$nombre,
            'email' => $email,
            'password' => 'secreto123',
            'password_confirmation' => 'secreto123',
        ])->assertCreated();

        return ['token' => $respuesta->json('token'), 'company_id' => $respuesta->json('company.id')];
    }

    public function test_el_middleware_resuelve_la_empresa_del_token_y_aisla_los_listados(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $a = $this->negocio('Bodega La Habana', 'ana@almacen.test');
        $b = $this->negocio('Bodega Santiago', 'beto@almacen.test');

        $this->withToken($a['token'])->postJson('/v1/warehouses', ['nombre' => 'Uno'])->assertCreated();
        $this->withToken($a['token'])->postJson('/v1/warehouses', ['nombre' => 'Dos'])->assertCreated();
        $this->withToken($b['token'])->postJson('/v1/warehouses', ['nombre' => 'Único'])->assertCreated();

        // Sin fijar el contexto a mano: lo resuelve el middleware desde el token.
        app(CurrentCompany::class)->set(null);

        $this->withToken($a['token'])->getJson('/v1/warehouses')->assertOk()->assertJsonCount(2, 'data');
        $this->withToken($b['token'])->getJson('/v1/warehouses')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_el_binding_de_ruta_tampoco_alcanza_al_recurso_de_otra_empresa(): void
    {
        // `SubstituteBindings` resuelve `{warehouse}` por su cuenta: si corriera
        // antes que el middleware, el binding se haría sin empresa de contexto
        // y devolvería el almacén ajeno con un 200.
        $this->seed(RolesAndPermissionsSeeder::class);

        $a = $this->negocio('Bodega La Habana', 'ana@almacen.test');
        $b = $this->negocio('Bodega Santiago', 'beto@almacen.test');

        $ajeno = $this->withToken($b['token'])
            ->postJson('/v1/warehouses', ['nombre' => 'Ajeno'])
            ->assertCreated()
            ->json('data.id');

        app(CurrentCompany::class)->set(null);

        $this->withToken($a['token'])->getJson("/v1/warehouses/{$ajeno}")->assertNotFound();
    }

    public function test_un_token_valido_de_otra_empresa_sigue_autenticando(): void
    {
        // La resolución del token busca a su dueño, y `User` también lleva el
        // scope: si el contexto de la petición anterior sobreviviera, el token
        // del segundo negocio respondería 401 en vez de servir sus datos.
        $this->seed(RolesAndPermissionsSeeder::class);

        $a = $this->negocio('Bodega La Habana', 'ana@almacen.test');
        $b = $this->negocio('Bodega Santiago', 'beto@almacen.test');

        $this->withToken($a['token'])->getJson('/v1/me')->assertOk();

        $this->withToken($b['token'])
            ->getJson('/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'beto@almacen.test');
    }

    public function test_una_empresa_desactivada_deja_fuera_a_los_suyos(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $a = $this->negocio('Bodega La Habana', 'ana@almacen.test');

        Company::findOrFail($a['company_id'])->update(['activo' => false]);

        $this->withToken($a['token'])->getJson('/v1/warehouses')->assertForbidden();
    }

    public function test_sin_token_no_hay_empresa_ni_datos(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $a = $this->negocio('Bodega La Habana', 'ana@almacen.test');
        $this->withToken($a['token'])->postJson('/v1/warehouses', ['nombre' => 'Uno'])->assertCreated();

        $this->withHeaders(['Authorization' => ''])->getJson('/v1/warehouses')->assertUnauthorized();

        $this->assertSame(1, Warehouse::withoutGlobalScopes()->count());
    }
}
