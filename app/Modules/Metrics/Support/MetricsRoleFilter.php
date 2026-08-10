<?php

namespace App\Modules\Metrics\Support;

use App\Models\User;

/**
 * Recorta el informe según el rol.
 *
 * Filtrar después de calcular mantiene el cálculo en un solo sitio; lo que el
 * vendedor no puede ver se elimina aquí, en un único punto auditable.
 */
class MetricsRoleFilter
{
    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    public function filter(array $report, User $user): array
    {
        if ($user->isAdmin()) {
            return $report;
        }

        // La ganancia y el top de productos exponen el precio de compra o
        // información de negocio que el vendedor no debe ver (spec §8.3).
        unset($report['ganancia'], $report['top_productos'], $report['comparativa']);

        $report['ventas_por_vendedor'] = array_values(array_filter(
            $report['ventas_por_vendedor'],
            fn (array $fila): bool => $fila['user_id'] === $user->id,
        ));

        return $report;
    }
}
