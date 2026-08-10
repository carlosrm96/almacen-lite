<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Warehouses\Models\Stock;
use App\Modules\Warehouses\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Stock> */
class StockFactory extends Factory
{
    protected $model = Stock::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'cantidad' => fake()->numberBetween(0, 500),
            'minimo' => 0,
        ];
    }
}
