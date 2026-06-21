<?php

use App\Models\ContactType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view contact types', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('contact-types.index'))
        ->assertForbidden();
});

test('managers can create, update, and delete a contact type', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->get(route('contact-types.index'))
        ->assertSuccessful();

    $this->actingAs($manager)
        ->post(route('contact-types.store'), ['name' => 'Lead', 'is_active' => true])
        ->assertRedirect(route('contact-types.index'));

    $contactType = ContactType::where('name', 'Lead')->first();
    expect($contactType)->not->toBeNull();
    expect($contactType->is_active)->toBeTrue();

    $this->actingAs($manager)
        ->patch(route('contact-types.update', $contactType), ['name' => 'Lead'])
        ->assertRedirect(route('contact-types.index'));

    expect($contactType->refresh()->is_active)->toBeFalse();

    $this->actingAs($manager)
        ->delete(route('contact-types.destroy', $contactType))
        ->assertRedirect(route('contact-types.index'));

    $this->assertModelMissing($contactType);
});

test('contact type names must be unique', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    ContactType::factory()->create(['name' => 'Lead']);

    $this->actingAs($manager)
        ->post(route('contact-types.store'), ['name' => 'Lead'])
        ->assertInvalid(['name']);
});

test('the contact type index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    ContactType::factory()->create(['name' => 'Investor']);
    ContactType::factory()->create(['name' => 'Tenant']);

    $this->actingAs($admin)
        ->get(route('contact-types.index', ['search' => 'Investor']))
        ->assertInertia(fn ($page) => $page
            ->has('contactTypes.data', 1)
            ->where('contactTypes.data.0.name', 'Investor')
            ->where('filters.search', 'Investor'));
});
