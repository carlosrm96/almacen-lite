<?php

namespace App\Modules\Warehouses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouses\Actions\SetProductStock;
use App\Modules\Warehouses\Http\Requests\SetStockRequest;
use App\Modules\Warehouses\Http\Resources\StockResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * @group Almacenes · Stock
 *
 * @authenticated
 */
class ProductStockController extends Controller
{
    use AuthorizesRequests;

    /** Fija la cantidad disponible del producto en un almacén. */
    public function store(SetStockRequest $request, Product $product, SetProductStock $action): JsonResponse
    {
        $this->authorize('setStock', $product);

        $stock = $action->handle(
            $request->user(),
            $product,
            (int) $request->validated('warehouse_id'),
            (float) $request->validated('cantidad'),
            $request->has('minimo') ? (float) $request->validated('minimo') : null,
        );

        // 200 explícito: fijar stock no crea un recurso nuevo desde el punto de
        // vista de la API, pero la primera vez `firstOrCreate` deja el modelo
        // marcado como recién creado y Laravel respondería 201.
        return (new StockResource($stock))->response()->setStatusCode(Response::HTTP_OK);
    }
}
