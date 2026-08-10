<?php

namespace Tests\Unit\Metrics;

use App\Modules\Metrics\Enums\Period;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PeriodTest extends TestCase
{
    public function test_el_periodo_diario_va_de_medianoche_a_medianoche(): void
    {
        [$desde, $hasta] = Period::Daily->rango(CarbonImmutable::parse('2026-03-11 15:42:00'));

        $this->assertSame('2026-03-11 00:00:00', $desde->toDateTimeString());
        $this->assertSame('2026-03-12 00:00:00', $hasta->toDateTimeString());
    }

    public function test_el_periodo_semanal_va_de_lunes_a_lunes(): void
    {
        // 2026-03-11 es miércoles.
        [$desde, $hasta] = Period::Weekly->rango(CarbonImmutable::parse('2026-03-11 15:42:00'));

        $this->assertSame('2026-03-09 00:00:00', $desde->toDateTimeString());
        $this->assertSame('2026-03-16 00:00:00', $hasta->toDateTimeString());
    }

    public function test_el_periodo_mensual_cubre_el_mes_natural(): void
    {
        [$desde, $hasta] = Period::Monthly->rango(CarbonImmutable::parse('2026-03-11 15:42:00'));

        $this->assertSame('2026-03-01 00:00:00', $desde->toDateTimeString());
        $this->assertSame('2026-04-01 00:00:00', $hasta->toDateTimeString());
    }

    public function test_el_periodo_anterior_es_el_inmediatamente_previo(): void
    {
        $fecha = CarbonImmutable::parse('2026-03-11 15:42:00');

        $this->assertSame('2026-03-10 00:00:00', Period::Daily->rangoAnterior($fecha)[0]->toDateTimeString());
        $this->assertSame('2026-03-02 00:00:00', Period::Weekly->rangoAnterior($fecha)[0]->toDateTimeString());
        $this->assertSame('2026-02-01 00:00:00', Period::Monthly->rangoAnterior($fecha)[0]->toDateTimeString());
        $this->assertSame('2026-03-01 00:00:00', Period::Monthly->rangoAnterior($fecha)[1]->toDateTimeString());
    }
}
