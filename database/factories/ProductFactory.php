<?php

namespace Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->words(2, true),
            'precio_compra' => fake()->randomFloat(2, 1, 50),
            'precio_venta' => fake()->randomFloat(2, 51, 100),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            $base = Unit::firstWhere('factor', 1) ?? Unit::factory()->base()->create();

            $product->units()->create(['unit_id' => $base->id, 'is_base' => true]);
        });
    }
}
