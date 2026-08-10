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
                'user_id',
                'accion',
                'auditable_id',
                AllowedFilter::callback('desde', fn (Builder $q, $value) => $q->where('created_at', '>=', $value)),
                AllowedFilter::callback('hasta', fn (Builder $q, $value) => $q->where('created_at', '<=', $value)),
            )
            ->defaultSort('-created_at')
            ->allowedSorts('created_at')
            ->paginate()
            ->appends(request()->query());

        return AuditLogResource::collection($logs);
    }
}
