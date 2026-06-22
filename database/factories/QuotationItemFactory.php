<?php

namespace Database\Factories;

use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationItem>
 */
class QuotationItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quotation_id' => Quotation::factory(),
            'product_id' => null,
            'description' => fake()->sentence(3),
            'quantity' => fake()->numberBetween(1, 5),
            'unit_price' => fake()->numberBetween(100, 5000),
        ];
    }
}
