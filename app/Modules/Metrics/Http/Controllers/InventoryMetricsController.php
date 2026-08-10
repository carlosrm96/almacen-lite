<?php

namespace App\Modules\Metrics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Metrics\Http\Requests\InventoryMetricsRequest;
use App\Modules\Metrics\Support\InventoryMetricsReporter;
use Illuminate\Http\JsonResponse;

/**
 * @group Métricas · Inventario
 *
 * @authenticated
 */
class InventoryMetricsController extends Controller
{
    /** Valor del inventario y productos bajo mínimo (solo admin). */
    public function __invoke(InventoryMetricsRequest $request, InventoryMetricsReporter $reporter): JsonResponse
    {
        return new JsonResponse(['data' => $reporter->report(
            $request->filled('warehouse_id') ? (int) $request->validated('warehouse_id') : null,
            $request->filled('umbral') ? (float) $request->validated('umbral') : null,
        )]);
    }
}
