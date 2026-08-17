<?php

namespace App\Modules\Metrics\Http\Requests;

use App\Modules\Metrics\Enums\Period;
use App\Modules\Tenancy\Support\ScopesValidationToCompany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesMetricsRequest extends FormRequest
{
    use ScopesValidationToCompany;

    public function authorize(): bool
    {
        return $this->user()?->can('metrics.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['required', Rule::enum(Period::class)],
            'date' => ['sometimes', 'date'],
            'warehouse_id' => ['sometimes', 'nullable', 'integer', $this->companyScopedExists('warehouses', 'id')],
        ];
    }
}
