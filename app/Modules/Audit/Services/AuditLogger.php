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
