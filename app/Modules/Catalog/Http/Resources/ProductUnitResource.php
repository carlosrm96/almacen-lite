<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\ProductUnit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProductUnit */
class ProductUnitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'nombre' => $this->whenLoaded('unit', fn (): string => $this->unit->nombre),
            'factor' => $this->whenLoaded('unit', fn (): string => number_format($this->unit->factor, 3, '.', '')),
            'is_base' => $this->is_base,
        ];
    }
}
