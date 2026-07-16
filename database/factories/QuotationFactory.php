<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
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
            'customer_id' => Customer::factory(),
            'number' => 'QT-'.fake()->unique()->numerify('######'),
            'contact_id' => Contact::factory(),
            'project_id' => null,
            'quotation_date' => now()->toDateString(),
            'valid_until' => null,
            'status' => fake()->randomElement(Quotation::STATUSES),
            'subtotal' => 0,
            'discount' => 0,
            'tax_percent' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'notes' => null,
            'terms' => null,
            'created_by' => null,
        ];
    }
}
