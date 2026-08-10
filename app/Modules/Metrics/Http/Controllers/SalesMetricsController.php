<?php

namespace App\Modules\Metrics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Metrics\Enums\Period;
use App\Modules\Metrics\Http\Requests\SalesMetricsRequest;
use App\Modules\Metrics\Support\MetricsRoleFilter;
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
    public function __invoke(
        SalesMetricsRequest $request,
        SalesMetricsReporter $reporter,
        MetricsRoleFilter $filter,
    ): JsonResponse {
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

        $report = $reporter->report($period, $fecha, $warehouseId);

        // JSON_PRESERVE_ZERO_FRACTION: sin este flag, PHP serializa los
        // floats de `comparativa` que son enteros (p. ej. 50.0 o 0.0) sin el
        // punto decimal, y el enum queda como `int` al decodificar la
        // respuesta en los tests, rompiendo la comparación estricta del tipo
        // `float` declarado en la interfaz.
        return new JsonResponse(
            ['data' => $filter->filter($report, $user)],
            200,
            [],
            JSON_PRESERVE_ZERO_FRACTION,
        );
    }
}
