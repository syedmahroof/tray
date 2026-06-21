<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'product_category_id' => ProductCategory::factory(),
            'name' => fake()->unique()->bothify('Unit ##??'),
            'price' => fake()->randomFloat(2, 500000, 20000000),
            'area_sqft' => fake()->randomFloat(2, 400, 4000),
            'description' => fake()->sentence(),
        ];
    }
}
