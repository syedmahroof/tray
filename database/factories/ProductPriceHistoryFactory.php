<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPriceHistory>
 */
class ProductPriceHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $old = fake()->randomFloat(2, 20, 5000);

        return [
            'product_id' => Product::factory(),
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'field' => 'sr_rate',
            'old_value' => $old,
            'new_value' => round($old * 1.1, 2),
            'changed_at' => now(),
        ];
    }
}
