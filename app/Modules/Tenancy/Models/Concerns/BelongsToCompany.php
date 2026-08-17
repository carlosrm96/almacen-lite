<?php

namespace App\Modules\Tenancy\Models\Concerns;

use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Scopes\CompanyScope;
use App\Modules\Tenancy\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marca un modelo como perteneciente a una empresa.
 *
 * Hace dos cosas, y las dos importan: filtra las lecturas por la empresa de
 * contexto y rellena `company_id` al crear. Gracias a lo segundo, ninguna
 * Action existente tuvo que cambiar para escribir en la empresa correcta —
 * `company_id` no es fillable en ningún modelo, así que tampoco puede colarse
 * desde el cuerpo de una petición.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function (Model $model): void {
            $current = app(CurrentCompany::class);

            if (empty($model->company_id) && $current->isSet()) {
                $model->company_id = $current->id();
            }
        });
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
