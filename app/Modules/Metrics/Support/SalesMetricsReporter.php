<?php

namespace App\Modules\Metrics\Support;

use App\Modules\Catalog\Models\Currency;
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
        [$desdeAnterior, $hastaAnterior] = $period->rangoAnterior($fecha);

        $actual = $this->agregados($desde, $hasta, $warehouseId);
        $anterior = $this->agregados($desdeAnterior, $hastaAnterior, $warehouseId);

        return [
            'periodo' => $period->value,
            'desde' => $desde->toDateTimeString(),
            'hasta' => $hasta->toDateTimeString(),
            'warehouse_id' => $warehouseId,
            // Todas las cifras de este informe van en moneda base.
            'moneda' => Currency::base()->codigo,
            ...$actual,
            'serie' => $this->serie($period, $desde, $hasta, $warehouseId),
            'top_productos' => $this->topProductos($desde, $hasta, $warehouseId),
            'ventas_por_vendedor' => $this->ventasPorVendedor($desde, $hasta, $warehouseId),
            'comparativa' => [
                'ingresos_anterior' => $anterior['ingresos'],
                'numero_ventas_anterior' => $anterior['numero_ventas'],
                'variacion_ingresos' => $this->variacion((float) $anterior['ingresos'], (float) $actual['ingresos']),
                'variacion_numero_ventas' => $this->variacion((float) $anterior['numero_ventas'], (float) $actual['numero_ventas']),
            ],
        ];
    }

    /**
     * Serie de tiempo del periodo, con los puntos vacíos incluidos para que el
     * panel pueda graficar sin rellenar huecos.
     *
     * @return list<array{etiqueta: string, ingresos: string, numero_ventas: int}>
     */
    private function serie(Period $period, CarbonImmutable $desde, CarbonImmutable $hasta, ?int $warehouseId): array
    {
        $ventas = $this->ventasEn($desde, $hasta, $warehouseId)->get(['created_at', 'total']);

        $puntos = [];
        $cursor = $desde;
        $paso = $period === Period::Daily ? 'addHour' : 'addDay';
        $formato = $period === Period::Daily ? 'H' : 'Y-m-d';

        while ($cursor < $hasta) {
            $puntos[$cursor->format($formato)] = ['ingresos' => 0.0, 'numero_ventas' => 0];
            $cursor = $cursor->{$paso}();
        }

        foreach ($ventas as $venta) {
            $clave = CarbonImmutable::instance($venta->created_at)->format($formato);

            if (! isset($puntos[$clave])) {
                continue;
            }

            $puntos[$clave]['ingresos'] += (float) $venta->total;
            $puntos[$clave]['numero_ventas']++;
        }

        $serie = [];

        foreach ($puntos as $etiqueta => $valores) {
            $serie[] = [
                'etiqueta' => (string) $etiqueta,
                'ingresos' => number_format($valores['ingresos'], 2, '.', ''),
                'numero_ventas' => $valores['numero_ventas'],
            ];
        }

        return $serie;
    }

    /**
     * @return array{por_unidades: list<array<string, mixed>>, por_ingresos: list<array<string, mixed>>}
     */
    private function topProductos(CarbonImmutable $desde, CarbonImmutable $hasta, ?int $warehouseId): array
    {
        $base = fn (): EloquentBuilder => SaleItem::query()
            ->whereIn('sale_id', $this->ventasEn($desde, $hasta, $warehouseId)->select('sales.id'))
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->groupBy('sale_items.product_id', 'products.nombre')
            ->limit(10);

        $porUnidades = $base()
            ->selectRaw('sale_items.product_id, products.nombre, SUM(sale_items.cantidad_base) as unidades')
            ->orderByDesc('unidades')
            ->get()
            ->map(fn ($fila): array => [
                'product_id' => (int) $fila->product_id,
                'nombre' => $fila->nombre,
                'unidades' => number_format((float) $fila->unidades, 3, '.', ''),
            ])->all();

        $porIngresos = $base()
            ->selectRaw('sale_items.product_id, products.nombre, SUM(sale_items.subtotal) as ingresos')
            ->orderByDesc('ingresos')
            ->get()
            ->map(fn ($fila): array => [
                'product_id' => (int) $fila->product_id,
                'nombre' => $fila->nombre,
                'ingresos' => number_format((float) $fila->ingresos, 2, '.', ''),
            ])->all();

        return ['por_unidades' => $porUnidades, 'por_ingresos' => $porIngresos];
    }

    /**
     * @return list<array{user_id: int, nombre: string, ingresos: string, numero_ventas: int}>
     */
    private function ventasPorVendedor(CarbonImmutable $desde, CarbonImmutable $hasta, ?int $warehouseId): array
    {
        return $this->ventasEn($desde, $hasta, $warehouseId)
            ->join('users', 'users.id', '=', 'sales.user_id')
            ->groupBy('sales.user_id', 'users.name')
            ->selectRaw('sales.user_id, users.name, SUM(sales.total) as ingresos, COUNT(*) as numero_ventas')
            ->orderByDesc('ingresos')
            ->get()
            ->map(fn ($fila): array => [
                'user_id' => (int) $fila->user_id,
                'nombre' => $fila->name,
                'ingresos' => number_format((float) $fila->ingresos, 2, '.', ''),
                'numero_ventas' => (int) $fila->numero_ventas,
            ])->all();
    }

    /** Variación porcentual; `null` si el periodo anterior fue cero (no hay base de comparación). */
    private function variacion(float $anterior, float $actual): ?float
    {
        if (abs($anterior) < 0.0001) {
            return null;
        }

        return round((($actual - $anterior) / $anterior) * 100, 2);
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
            // `precio_*_unit` están en la moneda de la línea; `tasa_cambio` los
            // lleva a moneda base para que la suma no mezcle CUP con USD.
            ->selectRaw('COALESCE(SUM((precio_venta_unit - precio_compra_unit) * cantidad_base * tasa_cambio), 0) as ganancia')
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
