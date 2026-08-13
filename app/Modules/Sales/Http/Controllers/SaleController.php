<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Actions\RegisterSale;
use App\Modules\Sales\Http\Requests\StoreSaleRequest;
use App\Modules\Sales\Http\Resources\SaleResource;
use App\Modules\Sales\Models\Sale;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Ventas
 *
 * @authenticated
 */
class SaleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Listar ventas.
     *
     * @queryParam filter[warehouse_id] integer Filtra por almacén. Example: 1
     * @queryParam filter[user_id] integer Filtra por vendedor (id de usuario). Example: 5
     * @queryParam filter[vendedor] integer Alias de user_id. Example: 5
     * @queryParam sort string Orden: created_at, total. Prefijo - para descendente. Example: -created_at
     * @queryParam page integer Número de página. Example: 1
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Sale::class);

        $user = request()->user();

        $sales = QueryBuilder::for(Sale::class)
            ->with('items.product', 'items.unit')
            ->when($user->isVendedor(), fn ($q) => $q->where('warehouse_id', $user->warehouse_id))
            ->allowedFilters(
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('user_id'),
                AllowedFilter::exact('vendedor', 'user_id'),
            )
            ->defaultSort('-created_at', '-id')
            ->allowedSorts('created_at', 'total')
            ->paginate()
            ->appends(request()->query());

        return SaleResource::collection($sales);
    }

    /**
     * Registrar una venta.
     *
     * Descuenta el stock y devuelve el total de los productos vendidos.
     */
    public function store(StoreSaleRequest $request, RegisterSale $action): JsonResponse
    {
        $this->authorize('create', Sale::class);

        $sale = $action->handle(
            $request->user(),
            (int) $request->validated('warehouse_id'),
            $request->validated('items'),
        );

        return (new SaleResource($sale))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Sale $sale): SaleResource
    {
        $this->authorize('view', $sale);

        return new SaleResource($sale->load('items.product', 'items.unit'));
    }
}
