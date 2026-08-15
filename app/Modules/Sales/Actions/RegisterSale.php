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
            // `units.unit` precarga también la unidad de cada ProductUnit: sin
            // eso, resolver la unidad base de una línea sin `unit_id` disparaba
            // una consulta `belongsTo` perezosa por línea, dentro de la
            // transacción que mantiene los locks de stock.
            $products = Product::with('units.unit', 'currency')
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
                    : $product->units->firstWhere('is_base', true)->unit;

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
                $stock = $stocks[$productId] ?? null;
                $disponible = (float) ($stock->cantidad ?? 0);

                // Sin fila de stock para el producto en este almacén no hay nada
                // que descontar: se trata como insuficiente, nunca se llega a
                // desreferenciar un `null` en el paso de escritura.
                if ($stock === null || $solicitado > $disponible + 0.0001) {
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

            // 4. Descontar. Todo lo que llega aquí ya tiene fila de stock (paso 3).
            foreach ($demandaPorProducto as $productId => $solicitado) {
                $stocks[$productId]->decrement('cantidad', $solicitado);
            }

            // 5. Registrar la venta con los snapshots de precio, moneda y tasa.
            //
            // Los precios unitarios se guardan en la moneda del producto (el
            // ticket tiene que poder mostrar lo que se cobró), pero `subtotal` y
            // `total` se guardan ya convertidos a moneda base: así toda la
            // agregación de métricas suma importes comparables aunque el
            // catálogo mezcle CUP y USD. La tasa se congela por línea, igual que
            // los precios, para que una devaluación futura no reescriba la
            // ganancia del pasado.
            $sale = Sale::create(['warehouse_id' => $warehouseId, 'user_id' => $user->id, 'total' => 0]);
            $total = 0.0;

            foreach ($lineas as $linea) {
                $product = $linea['product'];
                $moneda = $product->moneda();
                $tasa = (float) $moneda->tasa;

                $subtotal = round($product->precio_venta * $linea['cantidad_base'] * $tasa, 2);
                $total += $subtotal;

                $sale->items()->create([
                    'product_id' => $product->id,
                    'unit_id' => $linea['unit']->id,
                    'cantidad' => $linea['cantidad'],
                    'cantidad_base' => $linea['cantidad_base'],
                    'precio_venta_unit' => $product->precio_venta,
                    'precio_compra_unit' => $product->precio_compra,
                    'moneda_codigo' => $moneda->codigo,
                    'tasa_cambio' => $tasa,
                    'subtotal' => $subtotal,
                ]);
            }

            $sale->update(['total' => round($total, 2)]);

            return $sale->load('items.unit', 'items.product');
        });
    }
}
