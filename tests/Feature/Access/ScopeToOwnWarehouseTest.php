<?php

namespace Tests\Feature\Access;

use App\Modules\Access\Http\Middleware\ScopeToOwnWarehouse;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ScopeToOwnWarehouseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pasa la petición directamente por el middleware (sin registrar una
     * ruta de usar y tirar) y devuelve la Request tal como llega al
     * siguiente eslabón del pipeline, para poder comprobar qué le hizo.
     */
    private function passThrough(Request $request): Request
    {
        $captured = null;

        (new ScopeToOwnWarehouse)->handle($request, function (Request $forwarded) use (&$captured) {
            $captured = $forwarded;

            return response()->noContent();
        });

        return $captured;
    }

    public function test_el_vendedor_no_puede_imponer_otro_almacen_por_el_cuerpo(): void
    {
        $suyo = Warehouse::factory()->create();
        $otro = Warehouse::factory()->create();
        $vendedor = $this->actingAsRole('vendedor', ['warehouse_id' => $suyo->id]);

        $request = Request::create('/scope-test', 'POST', ['warehouse_id' => $otro->id]);
        $request->setUserResolver(fn () => $vendedor);

        $result = $this->passThrough($request);

        $this->assertSame($suyo->id, $result->input('warehouse_id'));
    }

    public function test_el_vendedor_no_puede_imponer_otro_almacen_por_la_query_string(): void
    {
        $suyo = Warehouse::factory()->create();
        $otro = Warehouse::factory()->create();
        $vendedor = $this->actingAsRole('vendedor', ['warehouse_id' => $suyo->id]);

        $request = Request::create("/scope-test?warehouse_id={$otro->id}", 'GET');
        $request->setUserResolver(fn () => $vendedor);

        $result = $this->passThrough($request);

        $this->assertSame((string) $suyo->id, $result->query('warehouse_id'));
    }

    public function test_al_vendedor_se_le_inyecta_su_almacen_si_no_manda_ninguno(): void
    {
        $suyo = Warehouse::factory()->create();
        $vendedor = $this->actingAsRole('vendedor', ['warehouse_id' => $suyo->id]);

        $request = Request::create('/scope-test', 'POST', []);
        $request->setUserResolver(fn () => $vendedor);

        $result = $this->passThrough($request);

        $this->assertSame($suyo->id, $result->input('warehouse_id'));
    }

    public function test_el_admin_conserva_el_almacen_que_envia(): void
    {
        $warehouse = Warehouse::factory()->create();
        $admin = $this->actingAsRole('admin');

        $request = Request::create('/scope-test', 'POST', ['warehouse_id' => $warehouse->id]);
        $request->setUserResolver(fn () => $admin);

        $result = $this->passThrough($request);

        $this->assertSame($warehouse->id, $result->input('warehouse_id'));
    }

    public function test_al_admin_no_se_le_inyecta_ningun_almacen(): void
    {
        $admin = $this->actingAsRole('admin');

        $request = Request::create('/scope-test', 'POST', []);
        $request->setUserResolver(fn () => $admin);

        $result = $this->passThrough($request);

        $this->assertNull($result->input('warehouse_id'));
    }
}
