<?php

namespace App\Modules\Catalog\Listeners;

use App\Modules\Catalog\Actions\SeedCompanyCurrencies;
use App\Modules\Tenancy\Events\CompanyRegistered;
use App\Modules\Tenancy\Support\CurrentCompany;

/**
 * Un negocio recién registrado arranca con sus monedas: CUP y USD, con la tasa
 * de `ALMACEN_TASA_USD`. A partir de ahí la tasa es suya y la ajusta él.
 *
 * Va por evento y no por llamada directa desde `RegisterCompany` para que
 * Tenancy no tenga que conocer a Catalog.
 */
class SeedCurrenciesOnCompanyRegistered
{
    public function __construct(
        private SeedCompanyCurrencies $action,
        private CurrentCompany $current,
    ) {}

    public function handle(CompanyRegistered $event): void
    {
        // La empresa de contexto se fija aquí, y no se da por supuesta: sembrar
        // sin contexto —o con el de otra empresa— crearía las monedas en el
        // negocio equivocado sin que nada avise.
        $this->current->set($event->company);

        $this->action->handle();
    }
}
