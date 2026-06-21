<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Contact;
use App\Models\ContactType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
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
            'contact_type_id' => ContactType::factory(),
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->streetAddress(),
        ];
    }
}
