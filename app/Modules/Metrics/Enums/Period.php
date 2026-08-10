<?php

namespace App\Modules\Metrics\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

enum Period: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * Ventana del periodo que contiene `$fecha`: inicio inclusivo, fin exclusivo.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function rango(CarbonInterface $fecha): array
    {
        $fecha = CarbonImmutable::instance($fecha);

        return match ($this) {
            self::Daily => [$fecha->startOfDay(), $fecha->startOfDay()->addDay()],
            self::Weekly => [$fecha->startOfWeek(CarbonInterface::MONDAY), $fecha->startOfWeek(CarbonInterface::MONDAY)->addWeek()],
            self::Monthly => [$fecha->startOfMonth(), $fecha->startOfMonth()->addMonth()],
        };
    }

    /**
     * Ventana del periodo inmediatamente anterior, para la comparativa.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function rangoAnterior(CarbonInterface $fecha): array
    {
        [$desde] = $this->rango($fecha);

        return $this->rango(match ($this) {
            self::Daily => $desde->subDay(),
            self::Weekly => $desde->subWeek(),
            self::Monthly => $desde->subMonth(),
        });
    }
}
