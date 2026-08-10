<?php

namespace App\Modules\Metrics\Support;

use App\Modules\Warehouses\Models\Stock;
use Illuminate\Database\Eloquent\Builder;

/**
 * Valor del inventario y productos bajo mínimo. Solo para el panel del admin:
 * expone el precio de compra.
 */
class InventoryMetricsReporter
{
    /**
     * @param  float|null  $umbral  si se indica, sustituye al mínimo de cada fila
     * @return array<string, mixed>
     */
    public function report(?int $warehouseId, ?float $umbral): array
    {
        $valor = $this->stocksVivos($warehouseId)
            ->join('warehouses', 'warehouses.id', '=', 'stocks.warehouse_id')
            ->groupBy('stocks.warehouse_id', 'warehouses.nombre')
            ->selectRaw('stocks.warehouse_id, warehouses.nombre')
            ->selectRaw('SUM(stocks.cantidad * products.precio_compra) as a_coste')
            ->selectRaw('SUM(stocks.cantidad * products.precio_venta) as a_venta')
            ->get();

        $stockBajo = $this->stocksVivos($warehouseId)
            ->when(
                $umbral !== null,
                fn (Builder $q) => $q->where('stocks.cantidad', '<=', $umbral),
                fn (Builder $q) => $q->whereColumn('stocks.cantidad', '<=', 'stocks.minimo'),
            )
            ->orderBy('stocks.cantidad')
            ->get(['stocks.warehouse_id', 'stocks.product_id', 'products.nombre', 'stocks.cantidad', 'stocks.minimo']);

        return [
            'valor_inventario' => $valor->map(fn ($fila): array => [
                'warehouse_id' => (int) $fila->warehouse_id,
                'nombre' => $fila->nombre,
                'a_coste' => number_format((float) $fila->a_coste, 2, '.', ''),
                'a_venta' => number_format((float) $fila->a_venta, 2, '.', ''),
            ])->all(),
            'total_a_coste' => number_format((float) $valor->sum('a_coste'), 2, '.', ''),
            'total_a_venta' => number_format((float) $valor->sum('a_venta'), 2, '.', ''),
            'stock_bajo' => $stockBajo->map(fn ($fila): array => [
                'warehouse_id' => (int) $fila->warehouse_id,
                'product_id' => (int) $fila->product_id,
                'nombre' => $fila->nombre,
                'cantidad' => number_format((float) $fila->cantidad, 3, '.', ''),
                'minimo' => number_format((float) $fila->minimo, 3, '.', ''),
            ])->all(),
        ];
    }

    /**
     * Stock de productos no eliminados. El `join` con `products` filtra por
     * `deleted_at` para que el borrado lógico no infle el valor del inventario.
     *
     * @return Builder<Stock>
     */
    private function stocksVivos(?int $warehouseId): Builder
    {
        return Stock::query()
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->whereNull('products.deleted_at')
            ->when($warehouseId !== null, fn (Builder $q) => $q->where('stocks.warehouse_id', $warehouseId));
    }
}
