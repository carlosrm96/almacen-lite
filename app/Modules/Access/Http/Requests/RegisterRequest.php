<?php

namespace App\Modules\Access\Http\Requests;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * El registro es la puesta en marcha de la instalación, no un alta
     * cualquiera: sin multi-empresa que aísle a cada registrado, dejarlo
     * abierto convertiría a cualquiera que conozca la URL en admin del
     * almacén ajeno. Se cierra en cuanto existe el primer usuario; a partir
     * de ahí las altas van por `POST /v1/users`.
     */
    public function authorize(): Response
    {
        return User::query()->exists()
            ? Response::deny('El registro está cerrado: ya hay usuarios en el sistema. Pide a un administrador que te dé de alta.')
            : Response::allow();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Ni `rol` ni `warehouse_id`: el registrado es siempre el admin
        // dueño, y un admin no lleva almacén asignado.
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
