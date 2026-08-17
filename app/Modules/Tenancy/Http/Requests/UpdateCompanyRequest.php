<?php

namespace App\Modules\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * Comprueba el permiso aquí, no solo en el controlador: el Form Request se
     * resuelve antes del cuerpo del método, así que es la única capa capaz de
     * devolver 403 en vez de 422 cuando quien llama no tiene permiso y además
     * manda un payload inválido.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('company.update') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Ni `activo`: desactivar una empresa deja fuera a todos sus usuarios
        // (403 en `ResolveCurrentCompany`), y nadie debería poder hacerse eso
        // a sí mismo desde su propia API.
        return [
            'nombre' => ['required', 'string', 'max:255'],
        ];
    }
}
