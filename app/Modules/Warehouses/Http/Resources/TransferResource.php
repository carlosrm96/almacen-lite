<?php

namespace App\Modules\Warehouses\Http\Resources;

use App\Modules\Warehouses\Models\Transfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Transfer */
class TransferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'producto' => $this->whenLoaded('product', fn (): string => $this->product->nombre),
            'from_warehouse_id' => $this->from_warehouse_id,
            'to_warehouse_id' => $this->to_warehouse_id,
            'cantidad_base' => number_format($this->cantidad_base, 3, '.', ''),
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
        ];
    }
}
