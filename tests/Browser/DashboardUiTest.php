<?php

use App\Models\Branch;
use App\Models\Contact;
use App\Models\ContactType;
use App\Models\Enquiry;
use App\Models\Reminder;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('the dashboard renders stat cards and charts without errors', function () {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');
    $contactType = ContactType::factory()->create(['name' => 'Lead']);
    $contact = Contact::factory()->create(['branch_id' => $branch->id, 'contact_type_id' => $contactType->id]);
    Enquiry::factory()->create(['branch_id' => $branch->id, 'contact_id' => $contact->id, 'status' => 'new']);
    $this->actingAs($admin);

    $page = visit('/dashboard');

    $page->assertSee('Dashboard')
        ->assertSee('Contacts')
        ->assertSee('Enquiries')
        ->assertSee('Enquiries by status')
        ->assertSee('Enquiries per month')
        ->assertSee('Contacts by type')
        ->assertNoJavaScriptErrors();

    $page->screenshot(true, 'dashboard-overview');
});

test('the top bar theme toggle switches between light and dark mode', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $page = visit('/dashboard');
    $page->assertNoJavaScriptErrors();

    $page->click('@theme-toggle');
    $page->click('Dark');
    $page->wait(0.5);
    $page->assertPresent('html.dark');

    $page->click('@theme-toggle');
    $page->click('Light');
    $page->wait(0.5);
    $page->assertMissing('html.dark');
});

test('the notifications dropdown shows the current user\'s due reminders', function () {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');
    $contact = Contact::factory()->create(['branch_id' => $branch->id, 'name' => 'Jane Prospect']);
    Reminder::factory()->create([
        'remindable_type' => Contact::class,
        'remindable_id' => $contact->id,
        'user_id' => $admin->id,
        'title' => 'Call Jane back',
        'remind_at' => now()->subHour(),
        'status' => 'pending',
    ]);
    $this->actingAs($admin);

    $page = visit('/dashboard');
    $page->assertNoJavaScriptErrors();

    $page->click('@notifications-toggle');
    $page->assertSee('Call Jane back');
    $page->assertSee('Jane Prospect');
    $page->assertSee('Overdue');
    $page->assertSee('View more');
});

test('the header profile dropdown shows settings and log out', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $page = visit('/dashboard');
    $page->assertNoJavaScriptErrors();

    $page->click('@header-user-menu');
    $page->assertSee('Settings');
    $page->assertSee('Log out');
});
