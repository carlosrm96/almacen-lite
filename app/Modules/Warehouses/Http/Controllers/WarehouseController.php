<?php

namespace App\Modules\Warehouses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Sales\Models\Sale;
use App\Modules\Warehouses\Http\Requests\StoreWarehouseRequest;
use App\Modules\Warehouses\Http\Requests\UpdateWarehouseRequest;
use App\Modules\Warehouses\Http\Resources\WarehouseResource;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Almacenes
 *
 * @authenticated
 */
class WarehouseController extends Controller
{
    use AuthorizesRequests;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Warehouse::class);

        $warehouses = QueryBuilder::for(Warehouse::class)
            ->allowedFilters(AllowedFilter::partial('nombre'), AllowedFilter::exact('activo'))
            ->allowedSorts('nombre', 'created_at')
            ->paginate()
            ->appends(request()->query());

        return WarehouseResource::collection($warehouses);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $this->authorize('create', Warehouse::class);

        $warehouse = Warehouse::create($request->validated());

        return (new WarehouseResource($warehouse))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Warehouse $warehouse): WarehouseResource
    {
        $this->authorize('view', $warehouse);

        return new WarehouseResource($warehouse);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        $this->authorize('update', $warehouse);

        $warehouse->update($request->validated());

        return new WarehouseResource($warehouse);
    }

    public function destroy(Warehouse $warehouse): Response
    {
        $this->authorize('delete', $warehouse);

        $tieneStock = Stock::where('warehouse_id', $warehouse->id)->where('cantidad', '>', 0)->exists();
        $tieneUsuarios = User::where('warehouse_id', $warehouse->id)->exists();
        $tieneVentas = Sale::where('warehouse_id', $warehouse->id)->exists();

        if ($tieneStock || $tieneUsuarios || $tieneVentas) {
            throw ValidationException::withMessages([
                'warehouse' => ['El almacén tiene stock o usuarios asignados. Desactívalo en lugar de borrarlo.'],
            ]);
        }

        $warehouse->delete();

        return response()->noContent();
    }
}
