<?php

namespace App\Modules\Catalog\Actions;

use App\Models\User;
use App\Modules\Audit\Services\AuditLogger;
use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouses\Actions\SetProductStock;
use Illuminate\Support\Facades\DB;

class CreateProduct
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SetProductStock $setStock,
    ) {}

    /**
     * @param  array<string, mixed>  $datos  nombre, precio_compra, precio_venta
     * @param  array{warehouse_id: int, cantidad: float}|null  $stockInicial
     */
    public function handle(User $user, array $datos, int $baseUnitId, ?array $stockInicial = null): Product
    {
        return DB::transaction(function () use ($user, $datos, $baseUnitId, $stockInicial): Product {
            $product = Product::create($datos);
            $product->units()->create(['unit_id' => $baseUnitId, 'is_base' => true]);

            $this->audit->log($user, AuditLogger::ACCION_PRODUCTO_CREADO, $product, null, [
                'nombre' => $product->nombre,
                'precio_compra' => number_format($product->precio_compra, 2, '.', ''),
                'precio_venta' => number_format($product->precio_venta, 2, '.', ''),
            ]);

            if ($stockInicial !== null) {
                $this->setStock->handle(
                    $user,
                    $product,
                    $stockInicial['warehouse_id'],
                    $stockInicial['cantidad'],
                );

                return $product->load('units.unit', 'stocks');
            }

            return $product->load('units.unit');
        });
    }
}
