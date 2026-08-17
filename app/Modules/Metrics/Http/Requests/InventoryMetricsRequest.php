<?php

namespace App\Modules\Metrics\Http\Requests;

use App\Modules\Tenancy\Support\ScopesValidationToCompany;
use Illuminate\Foundation\Http\FormRequest;

class InventoryMetricsRequest extends FormRequest
{
    use ScopesValidationToCompany;

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
            'warehouse_id' => ['sometimes', 'nullable', 'integer', $this->companyScopedExists('warehouses', 'id')],
            'umbral' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
