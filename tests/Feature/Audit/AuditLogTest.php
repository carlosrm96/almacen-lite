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

    public function test_el_filtro_por_user_id_es_exacto_y_no_por_subcadena(): void
    {
        $admin = $this->actingAsRole('admin');
        $warehouse = Warehouse::factory()->create();

        // Creamos usuarios de relleno hasta obtener uno cuyo id contenga el
        // del admin como subcadena (p. ej. admin=1, otro=11): con un filtro
        // LIKE '%1%' (AllowedFilter::partial, el que aplica spatie a un
        // filtro declarado como string suelto) el 11 colaría también.
        User::factory()->count(9)->create();
        $otro = User::factory()->create();

        $this->assertNotSame($admin->id, $otro->id);
        $this->assertStringContainsString((string) $admin->id, (string) $otro->id);

        app(AuditLogger::class)->log($admin, AuditLogger::ACCION_PRODUCTO_CREADO, $warehouse);
        app(AuditLogger::class)->log($otro, AuditLogger::ACCION_PRODUCTO_ACTUALIZADO, $warehouse);

        $this->getJson("/v1/audit-logs?filter[user_id]={$admin->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $admin->id);
    }

    public function test_el_vendedor_no_puede_ver_la_auditoria(): void
    {
        $this->actingAsRole('vendedor', ['warehouse_id' => Warehouse::factory()->create()->id]);

        $this->getJson('/v1/audit-logs')->assertForbidden();
    }
}
