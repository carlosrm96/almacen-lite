<?php

namespace App\Modules\Warehouses\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouses\Actions\SetProductStock;
use App\Modules\Warehouses\Http\Requests\SetStockRequest;
use App\Modules\Warehouses\Http\Resources\StockResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * @group Almacenes · Stock
 *
 * @authenticated
 */
class ProductStockController extends Controller
{
    use AuthorizesRequests;

    /** Fija la cantidad disponible del producto en un almacén. */
    public function store(SetStockRequest $request, Product $product, SetProductStock $action): StockResource
    {
        $this->authorize('setStock', $product);

        $stock = $action->handle(
            $request->user(),
            $product,
            (int) $request->validated('warehouse_id'),
            (float) $request->validated('cantidad'),
            $request->has('minimo') ? (float) $request->validated('minimo') : null,
        );

        return new StockResource($stock);
    }
}
