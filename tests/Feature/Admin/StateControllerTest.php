<?php

use App\Models\Country;
use App\Models\District;
use App\Models\State;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view states', function () {
    $user = User::factory()->create();
    $country = Country::factory()->create();

    $this->actingAs($user)
        ->get(route('countries.states.index', $country))
        ->assertForbidden();
});

test('admins can view, create, update, and delete a state for a country', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $country = Country::factory()->create();

    $this->actingAs($admin)
        ->get(route('countries.states.index', $country))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->post(route('countries.states.store', $country), ['name' => 'Karnataka', 'code' => 'KA'])
        ->assertRedirect(route('countries.states.index', $country));

    $state = State::where('name', 'Karnataka')->first();
    expect($state)->not->toBeNull();
    expect($state->country_id)->toBe($country->id);

    $this->actingAs($admin)
        ->patch(route('countries.states.update', [$country, $state]), ['name' => 'KA Renamed', 'code' => 'KA'])
        ->assertRedirect(route('countries.states.index', $country));

    expect($state->refresh()->name)->toBe('KA Renamed');

    $this->actingAs($admin)
        ->delete(route('countries.states.destroy', [$country, $state]))
        ->assertRedirect(route('countries.states.index', $country));

    $this->assertModelMissing($state);
});

test('a state cannot be deleted while it still has districts', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $country = Country::factory()->create();
    $state = State::factory()->create(['country_id' => $country->id]);
    District::factory()->create(['state_id' => $state->id]);

    $this->actingAs($admin)
        ->delete(route('countries.states.destroy', [$country, $state]))
        ->assertRedirect();

    $this->assertModelExists($state);
});

test('a state route is scoped to its parent country', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $countryA = Country::factory()->create();
    $countryB = Country::factory()->create();
    $state = State::factory()->create(['country_id' => $countryA->id]);

    $this->actingAs($admin)
        ->get(route('countries.states.edit', [$countryB, $state]))
        ->assertNotFound();
});

test('the state index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $country = Country::factory()->create();
    State::factory()->create(['country_id' => $country->id, 'name' => 'Kerala']);
    State::factory()->create(['country_id' => $country->id, 'name' => 'Goa']);

    $this->actingAs($admin)
        ->get(route('countries.states.index', ['country' => $country->id, 'search' => 'Kerala']))
        ->assertInertia(fn ($page) => $page
            ->has('states.data', 1)
            ->where('states.data.0.name', 'Kerala')
            ->where('filters.search', 'Kerala'));
});
