<?php

namespace App\Modules\Access\Actions;

use App\Models\User;
use App\Modules\Access\Enums\Role;
use Illuminate\Support\Facades\DB;

class RegisterAdmin
{
    /**
     * Crea el usuario administrador de la instalación en una transacción.
     *
     * El rol no se recibe de fuera: quien se registra es siempre `admin`, y
     * un admin no lleva almacén (`warehouse_id` nulo), a diferencia del
     * vendedor.
     *
     * Requiere que `RolesAndPermissionsSeeder` haya corrido — es parte del
     * despliegue (`php artisan migrate --seed`).
     */
    public function handle(string $name, string $email, string $password): User
    {
        return DB::transaction(function () use ($name, $email, $password): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);

            $user->assignRole(Role::Admin->value);

            return $user;
        });
    }
}
