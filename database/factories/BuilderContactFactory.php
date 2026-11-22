<?php

namespace Database\Factories;

use App\Models\Builder;
use App\Models\BuilderContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BuilderContact>
 */
class BuilderContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'builder_id' => Builder::factory(),
            'name' => fake()->name(),
            'role' => fake()->jobTitle(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
        ];
    }
}
