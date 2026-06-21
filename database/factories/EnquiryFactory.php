<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Enquiry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
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
            'contact_id' => Contact::factory(),
            'status' => fake()->randomElement(Enquiry::STATUSES),
            'source' => fake()->randomElement(['Website', 'Walk-in', 'Referral', 'Phone Call']),
            'remarks' => fake()->sentence(),
        ];
    }
}
