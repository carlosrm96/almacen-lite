<?php

namespace App\Modules\Tenancy\Actions;

use App\Models\User;
use App\Modules\Access\Enums\Role;
use App\Modules\Tenancy\Events\CompanyRegistered;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Support\CurrentCompany;
use Illuminate\Support\Facades\DB;

class RegisterCompany
{
    /**
     * Crea la empresa y su usuario administrador (dueño) en una transacción.
     *
     * Corre sin contexto de empresa —la empresa se está creando aquí—, así que
     * el `company_id` del usuario se asigna a mano: el hook de
     * `BelongsToCompany` no tiene de dónde sacarlo.
     *
     * El rol no se recibe de fuera: quien se registra es siempre `admin` de su
     * propia empresa, y un admin no lleva almacén (`warehouse_id` nulo).
     *
     * Deja fijada la empresa como contexto de la petición para que lo que
     * escuche `CompanyRegistered` —y el Resource de la respuesta— trabajen ya
     * dentro de ella.
     *
     * Requiere que `RolesAndPermissionsSeeder` haya corrido: es parte del
     * despliegue (`php artisan migrate --seed`).
     */
    public function handle(string $companyName, string $name, string $email, string $password): User
    {
        $user = DB::transaction(function () use ($companyName, $name, $email, $password): User {
            $company = Company::create(['nombre' => $companyName]);

            $user = new User([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
            $user->company_id = $company->id;
            $user->save();

            $user->assignRole(Role::Admin->value);

            app(CurrentCompany::class)->set($company);

            CompanyRegistered::dispatch($company);

            return $user;
        });

        return $user->fresh(['company', 'roles']);
    }
}
