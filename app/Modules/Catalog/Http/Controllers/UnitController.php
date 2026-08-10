<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\StoreUnitRequest;
use App\Modules\Catalog\Http\Requests\UpdateUnitRequest;
use App\Modules\Catalog\Http\Resources\UnitResource;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Catálogo · Unidades
 *
 * @authenticated
 */
class UnitController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Unit::class);

        $units = QueryBuilder::for(Unit::class)
            ->allowedFilters(AllowedFilter::partial('nombre'))
            ->allowedSorts('nombre', 'factor')
            ->paginate()
            ->appends(request()->query());

        return UnitResource::collection($units);
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        $this->authorize('create', Unit::class);

        $unit = Unit::create($request->validated());

        return (new UnitResource($unit))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Unit $unit): UnitResource
    {
        $this->authorize('view', $unit);

        return new UnitResource($unit);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): UnitResource
    {
        $this->authorize('update', $unit);

        $unit->update($request->validated());

        return new UnitResource($unit);
    }

    public function destroy(Unit $unit): Response
    {
        $this->authorize('delete', $unit);

        $unit->delete();

        return response()->noContent();
    }
}
