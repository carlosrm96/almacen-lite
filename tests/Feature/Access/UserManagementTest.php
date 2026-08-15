<?php

namespace Tests\Feature\Access;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Models\Transfer;
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

    public function test_se_pueden_ordenar_los_usuarios_por_email(): void
    {
        $this->actingAsRole('admin');
        User::factory()->create(['email' => 'zzz@almacen.test']);
        User::factory()->create(['email' => 'aaa@almacen.test']);

        $emails = $this->getJson('/v1/users?sort=email')->assertOk()->json('data.*.email');

        $ordenados = $emails;
        sort($ordenados);
        $this->assertSame($ordenados, $emails);
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

    public function test_el_registro_publico_se_cierra_con_el_sistema_ya_en_marcha(): void
    {
        // El registro solo sirve para crear el primer admin; con usuarios ya
        // dados de alta, la única vía es `POST /v1/users`. Cobertura completa
        // en RegisterTest.
        $this->actingAsRole('admin');

        $this->postJson('/v1/register', [
            'name' => 'X', 'email' => 'x@x.test',
            'password' => 'secreto123', 'password_confirmation' => 'secreto123',
        ])->assertForbidden();
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

    public function test_cambiar_el_rol_a_vendedor_sin_almacen_es_rechazado(): void
    {
        $this->actingAsRole('admin');
        $otro = User::factory()->create();
        $otro->assignRole('admin');

        $this->putJson("/v1/users/{$otro->id}", ['rol' => 'vendedor'])
            ->assertStatus(422)->assertJsonValidationErrors('warehouse_id');
    }

    public function test_no_se_puede_borrar_un_usuario_con_ventas(): void
    {
        $this->actingAsRole('admin');
        $warehouse = Warehouse::factory()->create();
        $vendedor = User::factory()->create(['warehouse_id' => $warehouse->id]);
        $vendedor->assignRole('vendedor');
        Sale::factory()->for($vendedor)->for($warehouse)->create();

        // `sales.user_id` es restrictOnDelete: antes de esta guarda, borrar
        // este usuario llegaba a la base de datos y respondía con un 500.
        $this->deleteJson("/v1/users/{$vendedor->id}")->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $vendedor->id]);
    }

    public function test_no_se_puede_borrar_un_usuario_con_transferencias(): void
    {
        $this->actingAsRole('admin');
        $otro = User::factory()->create();
        $otro->assignRole('admin');

        $origen = Warehouse::factory()->create();
        $destino = Warehouse::factory()->create();
        $product = Product::factory()->create();
        Transfer::create([
            'product_id' => $product->id,
            'from_warehouse_id' => $origen->id,
            'to_warehouse_id' => $destino->id,
            'cantidad_base' => 10,
            'user_id' => $otro->id,
        ]);

        // `transfers.user_id` es restrictOnDelete por la misma razón que
        // `sales.user_id`: conservar la atribución histórica.
        $this->deleteJson("/v1/users/{$otro->id}")->assertStatus(422);
        $this->assertDatabaseHas('users', ['id' => $otro->id]);
    }
}
