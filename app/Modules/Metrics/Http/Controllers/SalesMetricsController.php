<?php

namespace App\Modules\Metrics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Metrics\Enums\Period;
use App\Modules\Metrics\Http\Requests\SalesMetricsRequest;
use App\Modules\Metrics\Support\SalesMetricsReporter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @group Métricas · Ventas
 *
 * @authenticated
 */
class SalesMetricsController extends Controller
{
    /**
     * Métricas de ventas del periodo.
     *
     * El vendedor solo puede pedir `weekly` y siempre de su almacén.
     */
    public function __invoke(SalesMetricsRequest $request, SalesMetricsReporter $reporter): JsonResponse
    {
        $user = $request->user();
        $period = Period::from($request->validated('period'));

        if ($user->isVendedor() && $period !== Period::Weekly) {
            throw new AccessDeniedHttpException('El vendedor solo puede consultar métricas semanales.');
        }

        $fecha = $request->filled('date')
            ? CarbonImmutable::parse($request->validated('date'))
            : CarbonImmutable::now();

        // El middleware `scope.warehouse` ya ha forzado el almacén del vendedor.
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->validated('warehouse_id') : null;

        return new JsonResponse(['data' => $reporter->report($period, $fecha, $warehouseId)]);
    }
}
