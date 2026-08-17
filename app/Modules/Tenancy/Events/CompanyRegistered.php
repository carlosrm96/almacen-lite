<?php

namespace App\Modules\Tenancy\Events;

use App\Modules\Tenancy\Models\Company;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Una empresa acaba de registrarse y todavía no tiene nada dentro.
 *
 * Existe para que otros módulos siembren lo suyo (Catalog siembra las monedas)
 * sin que Tenancy tenga que conocerlos.
 */
class CompanyRegistered
{
    use Dispatchable;

    public function __construct(public readonly Company $company) {}
}
