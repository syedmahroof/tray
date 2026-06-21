<?php

use App\Models\District;
use App\Models\State;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view districts', function () {
    $user = User::factory()->create();
    $state = State::factory()->create();

    $this->actingAs($user)
        ->get(route('states.districts.index', $state))
        ->assertForbidden();
});

test('admins can view, create, update, and delete a district for a state', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $state = State::factory()->create();

    $this->actingAs($admin)
        ->get(route('states.districts.index', $state))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->post(route('states.districts.store', $state), ['name' => 'Bengaluru Urban'])
        ->assertRedirect(route('states.districts.index', $state));

    $district = District::where('name', 'Bengaluru Urban')->first();
    expect($district)->not->toBeNull();
    expect($district->state_id)->toBe($state->id);

    $this->actingAs($admin)
        ->patch(route('states.districts.update', [$state, $district]), ['name' => 'Renamed District'])
        ->assertRedirect(route('states.districts.index', $state));

    expect($district->refresh()->name)->toBe('Renamed District');

    $this->actingAs($admin)
        ->delete(route('states.districts.destroy', [$state, $district]))
        ->assertRedirect(route('states.districts.index', $state));

    $this->assertModelMissing($district);
});

test('a district route is scoped to its parent state', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $stateA = State::factory()->create();
    $stateB = State::factory()->create();
    $district = District::factory()->create(['state_id' => $stateA->id]);

    $this->actingAs($admin)
        ->get(route('states.districts.edit', [$stateB, $district]))
        ->assertNotFound();
});

test('the district index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $state = State::factory()->create();
    District::factory()->create(['state_id' => $state->id, 'name' => 'Ernakulam']);
    District::factory()->create(['state_id' => $state->id, 'name' => 'Thrissur']);

    $this->actingAs($admin)
        ->get(route('states.districts.index', ['state' => $state->id, 'search' => 'Ernakulam']))
        ->assertInertia(fn ($page) => $page
            ->has('districts.data', 1)
            ->where('districts.data.0.name', 'Ernakulam')
            ->where('filters.search', 'Ernakulam'));
});
