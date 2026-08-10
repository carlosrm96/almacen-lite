<?php

namespace App\Modules\Metrics\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryMetricsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('metrics.full') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['sometimes', 'nullable', 'integer', 'exists:warehouses,id'],
            'umbral' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
