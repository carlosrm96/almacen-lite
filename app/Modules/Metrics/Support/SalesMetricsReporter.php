<?php

namespace App\Modules\Metrics\Support;

use App\Modules\Metrics\Enums\Period;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

/**
 * Calcula las métricas de ventas al vuelo sobre `sales` y `sale_items`.
 *
 * No hay tablas de agregados: con el volumen esperado la agregación SQL basta
 * y no hay riesgo de que los agregados queden desincronizados.
 */
class SalesMetricsReporter
{
    /**
     * @return array<string, mixed>
     */
    public function report(Period $period, CarbonInterface $fecha, ?int $warehouseId): array
    {
        [$desde, $hasta] = $period->rango($fecha);

        return [
            'periodo' => $period->value,
            'desde' => $desde->toDateTimeString(),
            'hasta' => $hasta->toDateTimeString(),
            'warehouse_id' => $warehouseId,
            ...$this->agregados($desde, $hasta, $warehouseId),
        ];
    }

    /**
     * Ingresos, nº de ventas, unidades, ganancia y ticket promedio de una ventana.
     *
     * @return array<string, mixed>
     */
    public function agregados(CarbonImmutable $desde, CarbonImmutable $hasta, ?int $warehouseId): array
    {
        $ventas = $this->ventasEn($desde, $hasta, $warehouseId);

        $ingresos = (float) $ventas->clone()->sum('total');
        $numeroVentas = (int) $ventas->clone()->count();

        $lineas = SaleItem::whereIn('sale_id', $ventas->clone()->select('sales.id'))
            ->selectRaw('COALESCE(SUM(cantidad_base), 0) as unidades')
            ->selectRaw('COALESCE(SUM((precio_venta_unit - precio_compra_unit) * cantidad_base), 0) as ganancia')
            ->first();

        return [
            'ingresos' => number_format($ingresos, 2, '.', ''),
            'numero_ventas' => $numeroVentas,
            'unidades_vendidas' => number_format((float) $lineas->unidades, 3, '.', ''),
            'ganancia' => number_format((float) $lineas->ganancia, 2, '.', ''),
            'ticket_promedio' => number_format($numeroVentas > 0 ? $ingresos / $numeroVentas : 0, 2, '.', ''),
        ];
    }

    /**
     * Columnas cualificadas con `sales.`: `ventasPorVendedor()` hace `join` con
     * `users`, que también tiene `created_at`, y sin el prefijo la consulta es
     * ambigua.
     *
     * @return EloquentBuilder<Sale>
     */
    public function ventasEn(CarbonImmutable $desde, CarbonImmutable $hasta, ?int $warehouseId): EloquentBuilder
    {
        return Sale::query()
            ->where('sales.created_at', '>=', $desde)
            ->where('sales.created_at', '<', $hasta)
            ->when($warehouseId !== null, fn (Builder $q) => $q->where('sales.warehouse_id', $warehouseId));
    }
}
