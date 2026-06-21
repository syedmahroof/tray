<?php

use App\Models\Country;
use App\Models\State;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view countries', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('countries.index'))->assertForbidden();
});

test('admins can view, create, update, and delete a country', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('countries.index'))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->post(route('countries.store'), ['name' => 'India', 'code' => 'IN'])
        ->assertRedirect(route('countries.index'));

    $country = Country::where('code', 'IN')->first();
    expect($country)->not->toBeNull();

    $this->actingAs($admin)
        ->patch(route('countries.update', $country), ['name' => 'Bharat', 'code' => 'IN'])
        ->assertRedirect(route('countries.index'));

    expect($country->refresh()->name)->toBe('Bharat');

    $this->actingAs($admin)
        ->delete(route('countries.destroy', $country))
        ->assertRedirect(route('countries.index'));

    $this->assertModelMissing($country);
});

test('a country cannot be deleted while it still has states', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $country = Country::factory()->create();
    State::factory()->create(['country_id' => $country->id]);

    $this->actingAs($admin)
        ->delete(route('countries.destroy', $country))
        ->assertRedirect();

    $this->assertModelExists($country);
});

test('the country index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Country::factory()->create(['name' => 'Singapore', 'code' => 'SG']);
    Country::factory()->create(['name' => 'Malaysia', 'code' => 'MY']);

    $this->actingAs($admin)
        ->get(route('countries.index', ['search' => 'Singapore']))
        ->assertInertia(fn ($page) => $page
            ->has('countries.data', 1)
            ->where('countries.data.0.name', 'Singapore')
            ->where('filters.search', 'Singapore'));
});
