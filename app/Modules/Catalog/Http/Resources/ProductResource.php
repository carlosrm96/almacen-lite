<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Product;
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
     * @return array<string, mixed>
     */
    private function paraAdmin(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'precio_compra' => number_format($this->precio_compra, 2, '.', ''),
            'precio_venta' => number_format($this->precio_venta, 2, '.', ''),
            'unidades' => ProductUnitResource::collection($this->whenLoaded('units')),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * El vendedor solo ve nombre, precio de venta y las unidades con las que
     * puede vender. Nunca el precio de compra ni nada derivado de él.
     *
     * @return array<string, mixed>
     */
    private function paraVendedor(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'precio_venta' => number_format($this->precio_venta, 2, '.', ''),
            'unidades' => ProductUnitResource::collection($this->whenLoaded('units')),
        ];
    }
}
