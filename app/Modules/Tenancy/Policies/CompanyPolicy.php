<?php

namespace App\Modules\Tenancy\Policies;

use App\Models\User;
use App\Modules\Tenancy\Models\Company;

/**
 * La empresa no lleva `CompanyScope` —es la raíz del aislamiento—, así que
 * aquí se comprueba a mano lo que el scope hace en los demás modelos: solo se
 * opera sobre la empresa propia.
 */
class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return $user->company_id === $company->id && $user->can('company.view');
    }

    public function update(User $user, Company $company): bool
    {
        return $user->company_id === $company->id && $user->can('company.update');
    }
}
