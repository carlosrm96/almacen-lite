<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouses\Http\Resources\StockResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Product */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($request->user()?->isAdmin() === true) {
            return $this->paraAdmin();
        }

        return $this->paraVendedor();
    }

    /**
     * Moneda en la que están los precios de este producto. `currency_id` nulo
     * significa moneda base, así que aquí nunca sale `null`.
     *
     * @return array<string, string>
     */
    private function moneda(): array
    {
        $moneda = $this->resource->moneda();

        return ['codigo' => $moneda->codigo, 'simbolo' => $moneda->simbolo];
    }

    /**
     * @return array<string, mixed>
     */
    private function paraAdmin(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'precio_compra' => number_format($this->precio_compra, 2, '.', ''),
            'precio_venta' => number_format($this->precio_venta, 2, '.', ''),
            'moneda' => $this->moneda(),
            'unidades' => ProductUnitResource::collection($this->whenLoaded('units')),
            'stocks' => StockResource::collection($this->whenLoaded('stocks')),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * El vendedor solo ve nombre, precio de venta, la cantidad de su propio
     * almacén y las unidades con las que puede vender. Nunca el precio de
     * compra ni nada derivado de él.
     *
     * La moneda sí la ve: un precio sin moneda no es un precio, y el código no
     * se deriva del precio de compra.
     *
     * @return array<string, mixed>
     */
    private function paraVendedor(): array
    {
        $warehouseId = (int) request()->user()->warehouse_id;

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'precio_venta' => number_format($this->precio_venta, 2, '.', ''),
            'moneda' => $this->moneda(),
            'cantidad' => number_format($this->cantidadEn($warehouseId), 3, '.', ''),
            'unidades' => ProductUnitResource::collection($this->whenLoaded('units')),
        ];
    }
}
