<?php

namespace Database\Factories;

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
            'product_category_id' => ProductCategory::factory(),
            'code' => fake()->unique()->bothify('PRD-#####'),
            'name' => fake()->unique()->bothify('Unit ##??'),
            'unit' => fake()->randomElement(['Mtr', 'Nos', 'Kg', 'Sqm']),
            'hsn_code' => (string) fake()->numberBetween(1000, 9999),
            'price' => $price = fake()->randomFloat(2, 500000, 20000000),
            'taxable_amount' => round($price / 1.18, 2),
            'tax_type' => 'GST 18%',
            'tax_percentage' => 18,
            'area_sqft' => fake()->randomFloat(2, 400, 4000),
            'description' => fake()->sentence(),
        ];
    }
}
