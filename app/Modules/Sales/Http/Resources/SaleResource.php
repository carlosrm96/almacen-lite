<?php

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sale */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse_id,
            'user_id' => $this->user_id,
            'total' => number_format($this->total, 2, '.', ''),
            'fecha' => $this->created_at,
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
