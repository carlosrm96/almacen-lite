<?php

namespace App\Modules\Tenancy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Público y repetible: cada registro crea su propia empresa aislada, así
     * que un registrado no llega a los almacenes de otro. Lo que protege este
     * endpoint es `throttle:auth`, no una guarda de un solo uso.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Ni `rol` ni `warehouse_id`: el registrado es siempre el admin dueño
        // de la empresa que acaba de crear, y un admin no lleva almacén.
        return [
            'empresa' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            // Único global, no por empresa: el login ocurre antes de saber de
            // qué empresa es quien entra, así que un email repetido lo dejaría
            // sin resolver.
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
