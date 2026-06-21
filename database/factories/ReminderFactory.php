<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'remindable_type' => Contact::class,
            'remindable_id' => Contact::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'remind_at' => fake()->dateTimeBetween('now', '+2 weeks'),
            'status' => 'pending',
        ];
    }
}
