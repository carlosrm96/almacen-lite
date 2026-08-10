<?php

namespace App\Modules\Access\Http\Requests;

use App\Modules\Access\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
        return $this->user()?->can('users.create') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'rol' => ['required', Rule::enum(Role::class)],
            // Un vendedor sin almacén es un estado inválido (spec §5, regla 2).
            'warehouse_id' => [
                Rule::requiredIf(fn (): bool => $this->input('rol') === Role::Vendedor->value),
                'nullable', 'integer', 'exists:warehouses,id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'Un vendedor debe estar asignado a un almacén.',
        ];
    }
}
