<?php

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Tenancy\Support\ScopesValidationToCompany;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    use ScopesValidationToCompany;

    public function authorize(): bool
    {
        return $this->user()?->can('products.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            // Omitirlo significa "moneda base", que es el caso normal.
            'currency_id' => ['sometimes', 'nullable', 'integer', $this->companyScopedExists('currencies', 'id')->where('activo', true)],
            'base_unit_id' => [
                'required', 'integer',
                // La unidad base es, por definición, la de factor 1 (spec §4.1).
                $this->companyScopedExists('units', 'id')->where('factor', 1),
            ],
            'warehouse_id' => ['required_with:cantidad', 'integer', $this->companyScopedExists('warehouses', 'id')],
            'cantidad' => ['required_with:warehouse_id', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'base_unit_id.exists' => 'La unidad base debe existir y tener factor 1.',
            'currency_id.exists' => 'La moneda debe existir y estar activa.',
        ];
    }
}
