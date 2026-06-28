<?php

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Contact;
use App\Models\ContactType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('contact creation and update creates audit log entries', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('Super Admin');
    $contactType = ContactType::factory()->create();

    // 1. Create Contact
    $this->actingAs($user)
        ->post(route('contacts.store'), [
            'contact_type_id' => $contactType->id,
            'name' => 'John Doe',
            'phone' => '1234567890',
            'email' => 'john@example.com',
            'branch_id' => $branch->id,
        ]);

    $contact = Contact::where('name', 'John Doe')->first();
    expect($contact)->not->toBeNull();

    // Check created log
    $creationLog = AuditLog::where('auditable_type', Contact::class)
        ->where('auditable_id', $contact->id)
        ->where('action', 'created')
        ->first();
    expect($creationLog)->not->toBeNull();
    expect($creationLog->user_id)->toBe($user->id);

    // 2. Update Contact (change phone and name)
    $this->actingAs($user)
        ->patch(route('contacts.update', $contact), [
            'contact_type_id' => $contactType->id,
            'name' => 'John Updated',
            'phone' => '0987654321',
            'branch_id' => $branch->id,
        ]);

    // Check updated log
    $updateLog = AuditLog::where('auditable_type', Contact::class)
        ->where('auditable_id', $contact->id)
        ->where('action', 'updated')
        ->first();
    expect($updateLog)->not->toBeNull();
    expect($updateLog->description)->toContain('Name changed from');
    expect($updateLog->description)->toContain('Phone changed from');

    // 3. Assign Contact
    $anotherUser = User::factory()->create(['branch_id' => $branch->id]);
    $this->actingAs($user)
        ->patch(route('contacts.update', $contact), [
            'contact_type_id' => $contactType->id,
            'name' => 'John Updated',
            'phone' => '0987654321',
            'assigned_to' => $anotherUser->id,
            'branch_id' => $branch->id,
        ]);

    // Check assigned log
    $assignedLog = AuditLog::where('auditable_type', Contact::class)
        ->where('auditable_id', $contact->id)
        ->where('action', 'assigned')
        ->first();
    expect($assignedLog)->not->toBeNull();
    expect($assignedLog->description)->toContain('Assignment changed');
});

test('contact analytics returns correct inertia data structure', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('Super Admin');

    $response = $this->actingAs($user)->get(route('contacts.analytics'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('contacts/Analytics')
        ->has('stats')
        ->has('contactsByType')
        ->has('contactsByBranch')
        ->has('contactsByStaff')
        ->has('contactTrends')
    );
});
