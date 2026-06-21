<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Contact;
use App\Models\User;
use App\Models\VisitReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitReport>
 */
class VisitReportFactory extends Factory
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
            'user_id' => User::factory(),
            'visit_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'visit_type' => fake()->randomElement(VisitReport::VISIT_TYPES),
            'objective' => fake()->sentence(3),
            'report' => fake()->paragraph(),
            'next_meeting_date' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'next_call_date' => fake()->optional()->dateTimeBetween('now', '+2 weeks'),
        ];
    }

    /**
     * @return Factory<VisitReport>
     */
    public function configure(): Factory
    {
        return $this->afterCreating(function (VisitReport $visitReport) {
            if ($visitReport->projects()->count() === 0 && $visitReport->customers()->count() === 0 && $visitReport->contacts()->count() === 0) {
                $visitReport->contacts()->attach(Contact::factory()->create(['branch_id' => $visitReport->branch_id]));
            }
        });
    }
}
