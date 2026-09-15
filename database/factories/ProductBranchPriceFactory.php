<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBranchPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBranchPrice>
 */
class ProductBranchPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost = fake()->randomFloat(2, 20, 5000);

        $rates = [];

        foreach (['sr' => 1.3, 'pr' => 1.4, 'cr' => 1.5] as $tier => $multiplier) {
            $rate = round($cost * $multiplier, 2);

            $rates["{$tier}_discount"] = round(($multiplier - 1) * 100, 2);
            $rates["{$tier}_rate"] = $rate;
            $rates["{$tier}_rate_with_tax"] = round($rate * 1.18, 2);
        }

        return [
            'product_id' => Product::factory(),
            'branch_id' => Branch::factory(),
            'cost' => $cost,
            'mrp' => round($cost * 2, 2),
            ...$rates,
            'is_active' => true,
        ];
    }
}
