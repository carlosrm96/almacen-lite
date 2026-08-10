<?php

namespace App\Modules\Warehouses\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    /**
     * Comprueba el permiso aquí (no solo en el controlador): el Form
     * Request se valida al resolver los parámetros del método, antes de
     * que se ejecute el cuerpo del controlador, así que es la única capa
     * capaz de devolver 403 en vez de 422 cuando quien llama no tiene
     * permiso y además manda un payload inválido.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('warehouses.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255', 'unique:warehouses,nombre'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
