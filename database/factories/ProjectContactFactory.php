<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectContact>
 */
class ProjectContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->name(),
            'role' => fake()->jobTitle(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
        ];
    }
}
