<?php

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Models\Company;

/**
 * Empresa de contexto de la petición actual (singleton).
 *
 * Todo el aislamiento cuelga de aquí: el scope global lee esta empresa para
 * filtrar, el trait la usa para rellenar `company_id` al crear, y los Form
 * Requests para acotar sus reglas `exists`/`unique`.
 *
 * Fuera de una petición HTTP —consola, seeders, el propio registro— no hay
 * empresa fijada y el scope queda inerte: ahí el `company_id` se pasa a mano.
 */
class CurrentCompany
{
    private ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
    }

    public function get(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function isSet(): bool
    {
        return $this->company !== null;
    }
}
