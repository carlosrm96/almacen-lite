<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Unit> */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->word(),
            'factor' => fake()->randomElement([6, 12, 24]),
        ];
    }

    /** Unidad base: factor 1. */
    public function base(): static
    {
        return $this->state(fn (): array => ['nombre' => 'unidad', 'factor' => 1]);
    }
}
