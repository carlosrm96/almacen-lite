<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Audit\Http\Resources\AuditLogResource;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Auditoría
 *
 * @authenticated
 */
class AuditLogController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = QueryBuilder::for(AuditLog::class)
            ->with('user')
            ->allowedFilters(
                // `exact`, no el `partial` que spatie aplica a un filtro declarado como
                // string suelto: con LIKE '%1%' el usuario 1 arrastraría al 10, 11, 21...
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('accion'),
                AllowedFilter::exact('auditable_id'),
                AllowedFilter::callback('desde', fn (Builder $q, $value) => $q->where('created_at', '>=', $value)),
                AllowedFilter::callback('hasta', fn (Builder $q, $value) => $q->where('created_at', '<=', $value)),
            )
            ->defaultSort('-created_at', '-id')
            ->allowedSorts('created_at')
            ->paginate()
            ->appends(request()->query());

        return AuditLogResource::collection($logs);
    }
}
