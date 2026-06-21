<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Builder>
 */
class BuilderFactory extends Factory
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
            'name' => fake()->company(),
            'contact_person' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->streetAddress(),
            'is_active' => true,
        ];
    }
}
