<?php

namespace App\Modules\Warehouses\Http\Resources;

use App\Modules\Warehouses\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Stock */
class StockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'cantidad' => number_format($this->cantidad, 3, '.', ''),
            'minimo' => number_format($this->minimo, 3, '.', ''),
        ];
    }
}
