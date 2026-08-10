<?php

namespace App\Modules\Sales\Http\Requests;

use App\Modules\Catalog\Models\ProductUnit;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('sales.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // El middleware `scope.warehouse` ya lo ha fijado para el vendedor;
            // el admin debe indicarlo explícitamente.
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'items' => ['required', 'array', 'min:1'],
            // `exists` no respeta el borrado lógico; hay que excluirlo a mano.
            'items.*.product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
            // Las columnas de cantidad son decimal(14,3): por debajo de 0.001 no
            // hay nada representable, y admitir valores menores abriría una
            // rendija entre la validación y el margen de tolerancia interno.
            'items.*.cantidad' => ['required', 'numeric', 'min:0.001'],
        ];
    }

    /**
     * La unidad de cada línea debe estar asignada a su producto.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ((array) $this->input('items', []) as $i => $item) {
                if (! isset($item['unit_id'], $item['product_id'])) {
                    continue;
                }

                $asignada = ProductUnit::where('product_id', $item['product_id'])
                    ->where('unit_id', $item['unit_id'])
                    ->exists();

                if (! $asignada) {
                    $validator->errors()->add("items.{$i}.unit_id", 'Esa unidad no está asignada al producto.');
                }
            }
        });
    }
}
