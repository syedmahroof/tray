<?php

use App\Models\Branch;
use App\Models\Contact;
use App\Models\ContactType;
use App\Models\Enquiry;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk();
});

test('a user with no permissions sees no counts or charts', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('counts.contacts', null)
        ->where('counts.enquiries', null)
        ->where('enquiriesByStatus', null)
        ->where('enquiriesByMonth', null)
        ->where('contactsByType', null));
});

test('an admin sees counts and chart data reflecting the seeded records', function () {
    $this->seed(RolePermissionSeeder::class);
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');
    $contactType = ContactType::factory()->create(['name' => 'Lead']);
    $contact = Contact::factory()->create(['branch_id' => $branch->id, 'contact_type_id' => $contactType->id]);
    Enquiry::factory()->create(['branch_id' => $branch->id, 'contact_id' => $contact->id, 'status' => 'new']);

    $response = $this->actingAs($admin)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('counts.contacts', 1)
        ->where('counts.enquiries', 1)
        ->where('counts.branches', 1)
        ->has('enquiriesByStatus', 4)
        ->has('enquiriesByMonth', 6)
        ->has('contactsByType', 1)
        ->where('contactsByType.0.type', 'Lead')
        ->where('contactsByType.0.count', 1));
});
