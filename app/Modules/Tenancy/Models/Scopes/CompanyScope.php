<?php

namespace App\Modules\Tenancy\Models\Scopes;

use App\Modules\Tenancy\Support\CurrentCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtra todo modelo del inquilino por la empresa de contexto.
 *
 * Sin contexto no filtra, en vez de no devolver nada: los seeders, la consola
 * y el registro (que crea la empresa antes de que exista contexto) tienen que
 * poder trabajar. Toda petición autenticada sí lleva contexto, puesto por
 * `ResolveCurrentCompany`.
 *
 * La columna va cualificada con el nombre de la tabla porque las métricas
 * hacen `join()` sobre estos builders y `company_id` existe en casi todas las
 * tablas: sin cualificar, la consulta sería ambigua.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $current = app(CurrentCompany::class);

        if ($current->isSet()) {
            $builder->where($model->getTable().'.company_id', $current->id());
        }
    }
}
