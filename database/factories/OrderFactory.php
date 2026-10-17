<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
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
            'type' => 'sale',
            'number' => 'SO-'.fake()->unique()->numerify('######'),
            'customer_id' => Customer::factory(),
            'supplier_id' => null,
            'gstin' => null,
            'supply_type' => 'intra',
            'order_date' => now()->toDateString(),
            'expected_date' => null,
            'status' => 'draft',
            'subtotal' => 0,
            'discount' => 0,
            'tax_amount' => 0,
            'cgst_amount' => 0,
            'sgst_amount' => 0,
            'igst_amount' => 0,
            'total' => 0,
            'notes' => null,
            'terms' => null,
            'created_by' => null,
        ];
    }

    public function purchase(): static
    {
        return $this->state(fn (): array => [
            'type' => 'purchase',
            'number' => 'PO-'.fake()->unique()->numerify('######'),
            'customer_id' => null,
            'supplier_id' => Supplier::factory(),
        ]);
    }
}
