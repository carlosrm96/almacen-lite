<?php

namespace App\Modules\Warehouses\Actions;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouses\Models\Stock;
use Illuminate\Support\Facades\DB;

class SetProductStock
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Fija (no incrementa) la cantidad del producto en el almacén, en unidad base.
     */
    public function handle(User $user, Product $product, int $warehouseId, float $cantidad, ?float $minimo = null): Stock
    {
        return DB::transaction(function () use ($user, $product, $warehouseId, $cantidad, $minimo): Stock {
            $stock = Stock::lockForUpdate()->firstOrCreate(
                ['product_id' => $product->id, 'warehouse_id' => $warehouseId],
                ['cantidad' => 0, 'minimo' => 0],
            );

            // Este endpoint siempre "fija" el stock, nunca lo "crea" desde el punto
            // de vista de la API: forzamos wasRecentlyCreated=false para que el
            // JsonResource no responda 201 cuando el registro no existía aún.
            $stock->wasRecentlyCreated = false;

            $anterior = $stock->cantidad;

            $stock->cantidad = $cantidad;
            if ($minimo !== null) {
                $stock->minimo = $minimo;
            }
            $stock->save();

            $this->audit->log($user, AuditLogger::ACCION_STOCK_FIJADO, $product, $warehouseId, [
                'anterior' => number_format($anterior, 3, '.', ''),
                'nuevo' => number_format($cantidad, 3, '.', ''),
            ]);

            return $stock;
        });
    }
}
