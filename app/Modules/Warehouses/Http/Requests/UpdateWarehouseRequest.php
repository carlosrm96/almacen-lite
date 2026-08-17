<?php

namespace App\Modules\Warehouses\Http\Requests;

use App\Modules\Tenancy\Support\ScopesValidationToCompany;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    use ScopesValidationToCompany;

    /**
     * Comprueba el permiso aquí (no solo en el controlador): el Form
     * Request se valida al resolver los parámetros del método, antes de
     * que se ejecute el cuerpo del controlador, así que es la única capa
     * capaz de devolver 403 en vez de 422 cuando quien llama no tiene
     * permiso y además manda un payload inválido.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('warehouses.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'string', 'max:255', $this->companyScopedUnique('warehouses', 'nombre')->ignore($this->route('warehouse'))],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
