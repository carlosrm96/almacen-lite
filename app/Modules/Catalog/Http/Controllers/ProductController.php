<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Actions\CreateProduct;
use App\Modules\Catalog\Actions\DeleteProduct;
use App\Modules\Catalog\Actions\UpdateProduct;
use App\Modules\Catalog\Http\Requests\StoreProductRequest;
use App\Modules\Catalog\Http\Requests\UpdateProductRequest;
use App\Modules\Catalog\Http\Resources\ProductResource;
use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Catálogo · Productos
 *
 * @authenticated
 */
class ProductController extends Controller
{
    use AuthorizesRequests;

    /**
     * Listar productos.
     *
     * @queryParam filter[nombre] string Filtra por nombre (coincidencia parcial). Example: agua
     * @queryParam filter[almacen] integer Solo productos con stock en el almacén indicado. Example: 1
     * @queryParam filter[moneda] string Solo productos con precios en esa moneda (código ISO). Example: USD
     * @queryParam sort string Orden: nombre, precio_venta, created_at. Prefijo - para descendente. Example: -created_at
     * @queryParam page integer Número de página. Example: 1
     */
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        $products = QueryBuilder::for(Product::class)
            ->with(['units.unit', 'stocks', 'currency'])
            ->allowedFilters(
                AllowedFilter::partial('nombre'),
                AllowedFilter::exact('almacen', 'stocks.warehouse_id'),
                AllowedFilter::exact('moneda', 'currency.codigo'),
            )
            ->allowedSorts('nombre', 'precio_venta', 'created_at')
            ->paginate()
            ->appends(request()->query());

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request, CreateProduct $action): JsonResponse
    {
        $this->authorize('create', Product::class);

        $product = $action->handle(
            $request->user(),
            $request->safe()->only(['nombre', 'precio_compra', 'precio_venta', 'currency_id']),
            (int) $request->validated('base_unit_id'),
            $request->has('warehouse_id') ? [
                'warehouse_id' => (int) $request->validated('warehouse_id'),
                'cantidad' => (float) $request->validated('cantidad'),
            ] : null,
        );

        return (new ProductResource($product))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Product $product): ProductResource
    {
        $this->authorize('view', $product);

        return new ProductResource($product->load(['units.unit', 'stocks', 'currency']));
    }

    public function update(UpdateProductRequest $request, Product $product, UpdateProduct $action): ProductResource
    {
        $this->authorize('update', $product);

        return new ProductResource($action->handle($request->user(), $product, $request->validated())->load('units.unit', 'currency'));
    }

    public function destroy(Product $product, DeleteProduct $action): Response
    {
        $this->authorize('delete', $product);

        $action->handle(request()->user(), $product);

        return response()->noContent();
    }
}
