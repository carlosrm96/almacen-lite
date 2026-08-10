<?php

namespace App\Modules\Metrics\Http\Requests;

use App\Modules\Metrics\Enums\Period;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesMetricsRequest extends FormRequest
{
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
            'warehouse_id' => ['sometimes', 'nullable', 'integer', 'exists:warehouses,id'],
        ];
    }
}
