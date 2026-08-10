<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'unit_id' => [
                'required', 'integer', 'exists:units,id',
                Rule::unique('product_units', 'unit_id')
                    ->where('product_id', $this->route('product')->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_id.unique' => 'Esa unidad ya está asignada al producto.',
        ];
    }
}
