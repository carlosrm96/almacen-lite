<?php

namespace App\Modules\Sales\Actions;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Exceptions\InsufficientStockException;
use App\Modules\Warehouses\Models\Stock;
use Illuminate\Support\Facades\DB;

class RegisterSale
{
    /**
     * Registra una venta y descuenta el stock del almacén.
     *
     * Valida la disponibilidad de **todos** los ítems antes de escribir nada:
     * si alguno no cabe, no se registra la venta ni se altera el inventario.
     *
     * @param  list<array{product_id: int, unit_id?: int|null, cantidad: float}>  $items
     *
     * @throws InsufficientStockException
     */
    public function handle(User $user, int $warehouseId, array $items): Sale
    {
        return DB::transaction(function () use ($user, $warehouseId, $items): Sale {
            $products = Product::with('units')
                ->whereIn('id', array_column($items, 'product_id'))
                ->get()
                ->keyBy('id');

            $units = Unit::whereIn('id', array_filter(array_column($items, 'unit_id')))->get()->keyBy('id');

            // 1. Resolver cada línea a unidad base.
            $lineas = [];
            $demandaPorProducto = [];

            foreach ($items as $item) {
                $product = $products[$item['product_id']];
                $unit = isset($item['unit_id'])
                    ? $units[$item['unit_id']]
                    : $product->units->firstWhere('is_base', true)->unit()->first();

                $cantidadBase = $product->toBase((float) $item['cantidad'], $unit);

                $lineas[] = [
                    'product' => $product,
                    'unit' => $unit,
                    'cantidad' => (float) $item['cantidad'],
                    'cantidad_base' => $cantidadBase,
                ];

                $demandaPorProducto[$product->id] = ($demandaPorProducto[$product->id] ?? 0) + $cantidadBase;
            }

            // 2. Bloquear las filas de stock implicadas.
            $stocks = Stock::where('warehouse_id', $warehouseId)
                ->whereIn('product_id', array_keys($demandaPorProducto))
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            // 3. Validar TODO antes de tocar nada.
            $faltantes = [];

            foreach ($demandaPorProducto as $productId => $solicitado) {
                $disponible = (float) ($stocks[$productId]->cantidad ?? 0);

                if ($solicitado > $disponible + 0.0001) {
                    $faltantes[] = [
                        'product_id' => (int) $productId,
                        'nombre' => $products[$productId]->nombre,
                        'solicitado' => number_format($solicitado, 3, '.', ''),
                        'disponible' => number_format($disponible, 3, '.', ''),
                    ];
                }
            }

            if ($faltantes !== []) {
                throw new InsufficientStockException($faltantes);
            }

            // 4. Descontar.
            foreach ($demandaPorProducto as $productId => $solicitado) {
                $stocks[$productId]->decrement('cantidad', $solicitado);
            }

            // 5. Registrar la venta con los snapshots de precio.
            $sale = Sale::create(['warehouse_id' => $warehouseId, 'user_id' => $user->id, 'total' => 0]);
            $total = 0.0;

            foreach ($lineas as $linea) {
                $product = $linea['product'];
                $subtotal = round($product->precio_venta * $linea['cantidad_base'], 2);
                $total += $subtotal;

                $sale->items()->create([
                    'product_id' => $product->id,
                    'unit_id' => $linea['unit']->id,
                    'cantidad' => $linea['cantidad'],
                    'cantidad_base' => $linea['cantidad_base'],
                    'precio_venta_unit' => $product->precio_venta,
                    'precio_compra_unit' => $product->precio_compra,
                    'subtotal' => $subtotal,
                ]);
            }

            $sale->update(['total' => round($total, 2)]);

            return $sale->load('items.unit', 'items.product');
        });
    }
}
