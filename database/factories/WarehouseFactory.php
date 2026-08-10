<?php

namespace Database\Factories;

use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Warehouse> */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Almacén '.fake()->unique()->city(),
            'activo' => true,
        ];
    }
}
