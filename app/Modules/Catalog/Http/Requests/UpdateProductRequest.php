<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
            'nombre' => ['sometimes', 'string', 'max:255'],
            'precio_compra' => ['sometimes', 'numeric', 'min:0'],
            'precio_venta' => ['sometimes', 'numeric', 'min:0'],
            'currency_id' => ['sometimes', 'nullable', 'integer', Rule::exists('currencies', 'id')->where('activo', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currency_id.exists' => 'La moneda debe existir y estar activa.',
        ];
    }
}
