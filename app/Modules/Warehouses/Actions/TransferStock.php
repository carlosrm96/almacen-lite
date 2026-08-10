<?php

namespace App\Modules\Warehouses\Actions;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Warehouses\Exceptions\InsufficientStockException;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Transfer;
use Illuminate\Support\Facades\DB;

class TransferStock
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Mueve stock de un almacén a otro. Solo la ejecuta el admin, así que es
     * inmediata: no hay estado "en tránsito" ni aprobación.
     *
     * @throws InsufficientStockException si el origen no tiene cantidad suficiente
     */
    public function handle(
        User $user,
        int $productId,
        int $fromWarehouseId,
        int $toWarehouseId,
        float $cantidad,
        ?int $unitId = null,
    ): Transfer {
        return DB::transaction(function () use ($user, $productId, $fromWarehouseId, $toWarehouseId, $cantidad, $unitId): Transfer {
            // `units.unit` precarga también la unidad de cada ProductUnit, para
            // no disparar una consulta `belongsTo` perezosa al resolver la
            // unidad base cuando no se indica `unit_id`.
            $product = Product::with('units.unit')->findOrFail($productId);

            $unit = $unitId !== null
                ? Unit::findOrFail($unitId)
                : $product->units->firstWhere('is_base', true)->unit;

            $cantidadBase = $product->toBase($cantidad, $unit);

            $origen = Stock::where('product_id', $productId)
                ->where('warehouse_id', $fromWarehouseId)
                ->lockForUpdate()
                ->first();

            $disponible = (float) ($origen->cantidad ?? 0);

            // Sin fila de stock en origen no hay nada que transferir: se trata
            // como insuficiente en vez de desreferenciar un `null` al descontar.
            if ($origen === null || $cantidadBase > $disponible + 0.0001) {
                throw new InsufficientStockException([[
                    'product_id' => $productId,
                    'nombre' => $product->nombre,
                    'solicitado' => number_format($cantidadBase, 3, '.', ''),
                    'disponible' => number_format($disponible, 3, '.', ''),
                ]]);
            }

            $origen->decrement('cantidad', $cantidadBase);

            $destino = Stock::lockForUpdate()->firstOrCreate(
                ['product_id' => $productId, 'warehouse_id' => $toWarehouseId],
                ['cantidad' => 0, 'minimo' => 0],
            );
            $destino->increment('cantidad', $cantidadBase);

            $transfer = Transfer::create([
                'product_id' => $productId,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'cantidad_base' => $cantidadBase,
                'user_id' => $user->id,
            ]);

            $this->audit->log($user, AuditLogger::ACCION_TRANSFERENCIA, $product, $fromWarehouseId, [
                'desde' => $fromWarehouseId,
                'hacia' => $toWarehouseId,
                'cantidad_base' => number_format($cantidadBase, 3, '.', ''),
                'unidad' => $unit->nombre,
            ]);

            return $transfer;
        });
    }
}
