<?php

namespace App\Modules\Access\Http\Requests;

use App\Models\User;
use App\Modules\Access\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        return $this->user()?->can('users.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');
        $rolFinal = $this->input('rol', $target->getRoleNames()->first());

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target)],
            'password' => ['sometimes', 'string', 'min:8'],
            'rol' => ['sometimes', Rule::enum(Role::class)],
            'warehouse_id' => [
                Rule::requiredIf(fn (): bool => $rolFinal === Role::Vendedor->value),
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
