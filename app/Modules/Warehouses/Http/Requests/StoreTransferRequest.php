<?php

namespace App\Modules\Warehouses\Http\Requests;

use App\Modules\Catalog\Models\ProductUnit;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transfers.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // `exists` no respeta el borrado lógico; hay que excluirlo a mano.
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'from_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'integer', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            // Las columnas de cantidad son decimal(14,3): por debajo de 0.001 no
            // hay nada representable, y admitir valores menores abriría una
            // rendija entre la validación y el margen de tolerancia interno.
            'cantidad' => ['required', 'numeric', 'min:0.001'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_warehouse_id.different' => 'El almacén de destino debe ser distinto del de origen.',
        ];
    }

    /**
     * La unidad, si se indica, debe estar asignada al producto.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $productId = $this->input('product_id');
            $unitId = $this->input('unit_id');

            if (! isset($productId, $unitId)) {
                return;
            }

            $asignada = ProductUnit::where('product_id', $productId)
                ->where('unit_id', $unitId)
                ->exists();

            if (! $asignada) {
                $validator->errors()->add('unit_id', 'Esa unidad no está asignada al producto.');
            }
        });
    }
}
