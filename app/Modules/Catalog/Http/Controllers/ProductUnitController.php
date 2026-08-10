<?php

namespace App\Modules\Catalog\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\StoreProductUnitRequest;
use App\Modules\Catalog\Http\Resources\ProductUnitResource;
use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

/**
 * @group Catálogo · Unidades del producto
 *
 * @authenticated
 */
class ProductUnitController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreProductUnitRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $productUnit = $product->units()->create([
            'unit_id' => (int) $request->validated('unit_id'),
            'is_base' => false,
        ]);

        return (new ProductUnitResource($productUnit->load('unit')))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Product $product, int $unit): Response
    {
        $this->authorize('update', $product);

        $productUnit = $product->units()->where('unit_id', $unit)->firstOrFail();

        if ($productUnit->is_base) {
            throw ValidationException::withMessages([
                'unit' => ['No se puede desasignar la unidad base del producto.'],
            ]);
        }

        $productUnit->delete();

        return response()->noContent();
    }
}
