<?php

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Currency */
class CurrencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'simbolo' => $this->simbolo,
            'tasa' => number_format($this->tasa, 6, '.', ''),
            'es_base' => $this->es_base,
            'activo' => $this->activo,
        ];
    }
}
