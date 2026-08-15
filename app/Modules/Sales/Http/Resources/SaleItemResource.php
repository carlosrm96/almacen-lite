<?php

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SaleItem */
class SaleItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'producto' => $this->whenLoaded('product', fn (): string => $this->product->nombre),
            'unit_id' => $this->unit_id,
            'unidad' => $this->whenLoaded('unit', fn (): string => $this->unit->nombre),
            'cantidad' => number_format($this->cantidad, 3, '.', ''),
            'cantidad_base' => number_format($this->cantidad_base, 3, '.', ''),
            // `precio_venta_unit` va en la moneda con la que se vendió;
            // `subtotal` ya está convertido a moneda base (ver RegisterSale).
            'precio_venta_unit' => number_format($this->precio_venta_unit, 2, '.', ''),
            'moneda' => $this->moneda_codigo,
            'tasa_cambio' => number_format($this->tasa_cambio, 6, '.', ''),
            'subtotal' => number_format($this->subtotal, 2, '.', ''),
        ];
    }
}
