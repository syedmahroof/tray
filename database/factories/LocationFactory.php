<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'district_id' => District::factory(),
            'name' => fake()->unique()->streetName(),
            'pincode' => fake()->numerify('6#####'),
            'is_active' => true,
        ];
    }
}
