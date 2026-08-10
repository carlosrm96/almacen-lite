<?php

namespace App\Modules\Warehouses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Warehouses\Actions\TransferStock;
use App\Modules\Warehouses\Http\Requests\StoreTransferRequest;
use App\Modules\Warehouses\Http\Resources\TransferResource;
use App\Modules\Warehouses\Models\Transfer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Almacenes · Transferencias
 *
 * @authenticated
 */
class TransferController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Transfer::class);

        $transfers = QueryBuilder::for(Transfer::class)
            ->with('product')
            ->allowedFilters(
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('from_warehouse_id'),
                AllowedFilter::exact('to_warehouse_id'),
                AllowedFilter::exact('user_id'),
            )
            ->defaultSort('-created_at', '-id')
            ->allowedSorts('created_at')
            ->paginate()
            ->appends(request()->query());

        return TransferResource::collection($transfers);
    }

    /** Transferencia inmediata entre almacenes (solo admin). */
    public function store(StoreTransferRequest $request, TransferStock $action): JsonResponse
    {
        $this->authorize('create', Transfer::class);

        $transfer = $action->handle(
            $request->user(),
            (int) $request->validated('product_id'),
            (int) $request->validated('from_warehouse_id'),
            (int) $request->validated('to_warehouse_id'),
            (float) $request->validated('cantidad'),
            $request->filled('unit_id') ? (int) $request->validated('unit_id') : null,
        );

        return (new TransferResource($transfer))->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
