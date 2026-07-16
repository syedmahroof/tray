<?php

use App\Models\ProjectCategory;
use App\Models\Reminder;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('dashboard returns todays follow-ups, separating overdue from due today', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $dueToday = Reminder::factory()->create([
        'user_id' => $user->id,
        'title' => 'Call the site engineer',
        'remind_at' => now()->setTime(15, 0),
        'status' => 'pending',
    ]);

    $overdue = Reminder::factory()->create([
        'user_id' => $user->id,
        'title' => 'Chase last week quote',
        'remind_at' => now()->subDays(3),
        'status' => 'pending',
    ]);

    // None of these should surface on today's list.
    Reminder::factory()->create([
        'user_id' => $user->id,
        'remind_at' => now()->addDays(2),
        'status' => 'pending',
    ]);
    Reminder::factory()->create([
        'user_id' => $user->id,
        'remind_at' => now()->setTime(9, 0),
        'status' => 'completed',
    ]);
    Reminder::factory()->create([
        'user_id' => $other->id,
        'remind_at' => now()->setTime(9, 0),
        'status' => 'pending',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/dashboard')->assertOk();

    $items = $response->json('followups.items');

    expect($items)->toHaveCount(2);
    expect($response->json('followups.today_count'))->toBe(1);
    expect($response->json('followups.overdue_count'))->toBe(1);

    // Ordered by remind_at, so the overdue one leads.
    expect($items[0]['id'])->toBe($overdue->id);
    expect($items[0]['is_overdue'])->toBeTrue();
    expect($items[1]['id'])->toBe($dueToday->id);
    expect($items[1]['is_overdue'])->toBeFalse();
    expect($items[1]['title'])->toBe('Call the site engineer');
});

test('dashboard reports a customers count', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonStructure(['counts' => ['customers', 'contacts', 'enquiries', 'projects', 'quotations', 'visit_reports']]);
});

test('metadata exposes project categories for the project form', function () {
    $category = ProjectCategory::factory()->create(['name' => 'Residential Tower']);

    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson('/api/metadata')->assertOk();

    expect($response->json('project_categories'))
        ->toContain(['id' => $category->id, 'name' => 'Residential Tower']);
});
