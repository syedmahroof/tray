<?php

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Reminder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('the notifications prop only includes the current user\'s due or overdue pending reminders', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('Sales Executive');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);

    $overdue = Reminder::factory()->create([
        'remindable_type' => Contact::class,
        'remindable_id' => $contact->id,
        'user_id' => $user->id,
        'title' => 'Overdue follow-up',
        'remind_at' => now()->subDay(),
        'status' => 'pending',
    ]);

    Reminder::factory()->create([
        'remindable_type' => Contact::class,
        'remindable_id' => $contact->id,
        'user_id' => $user->id,
        'title' => 'Future reminder',
        'remind_at' => now()->addWeek(),
        'status' => 'pending',
    ]);

    Reminder::factory()->create([
        'remindable_type' => Contact::class,
        'remindable_id' => $contact->id,
        'user_id' => $user->id,
        'title' => 'Already done',
        'remind_at' => now()->subDay(),
        'status' => 'done',
    ]);

    $otherUser = User::factory()->create(['branch_id' => $branch->id]);
    Reminder::factory()->create([
        'remindable_type' => Contact::class,
        'remindable_id' => $contact->id,
        'user_id' => $otherUser->id,
        'title' => "Someone else's reminder",
        'remind_at' => now()->subDay(),
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->has('notifications.items', 1)
        ->where('notifications.total', 1)
        ->where('notifications.items.0.title', 'Overdue follow-up')
        ->where('notifications.items.0.subject', $contact->name)
        ->where('notifications.items.0.url', route('contacts.show', $overdue->remindable_id)));
});

test('the reminders index page lists only the current user\'s due reminders', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('Sales Executive');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);

    Reminder::factory()->create([
        'remindable_type' => Contact::class,
        'remindable_id' => $contact->id,
        'user_id' => $user->id,
        'title' => 'Due reminder',
        'remind_at' => now()->subHour(),
        'status' => 'pending',
    ]);

    $response = $this->actingAs($user)->get(route('reminders.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('reminders.data', 1)
        ->where('reminders.data.0.title', 'Due reminder'));
});

test('users without the reminders permission cannot view the reminders index', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('reminders.index'))->assertForbidden();
});
