<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Builder;
use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
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
            'builder_id' => Builder::factory(),
            'project_category_id' => ProjectCategory::factory(),
            'name' => fake()->unique()->streetName().' Project',
            'address' => fake()->streetAddress(),
            'status' => fake()->randomElement(Project::STATUSES),
            'description' => fake()->paragraph(),
        ];
    }
}
