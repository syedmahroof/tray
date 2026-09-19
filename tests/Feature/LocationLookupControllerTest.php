<?php

use App\Models\Country;
use App\Models\District;
use App\Models\Location;
use App\Models\State;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot add a place from a picker', function () {
    $user = User::factory()->create();
    $district = District::factory()->create();

    $this->actingAs($user)
        ->postJson(route('location.locations.store', $district), ['name' => 'Kakkanad'])
        ->assertForbidden();
});

test('admins can add a state, district, and location straight from a picker', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $country = Country::factory()->create();

    $state = $this->actingAs($admin)
        ->postJson(route('location.states.store', $country), ['name' => 'Kerala', 'code' => 'KL'])
        ->assertCreated()
        ->assertJsonStructure(['id', 'name'])
        ->json();

    expect($state['name'])->toBe('Kerala');
    $this->assertDatabaseHas('states', ['id' => $state['id'], 'country_id' => $country->id, 'code' => 'KL']);

    $district = $this->actingAs($admin)
        ->postJson(route('location.districts.store', $state['id']), ['name' => 'Ernakulam'])
        ->assertCreated()
        ->json();

    $this->assertDatabaseHas('districts', ['id' => $district['id'], 'state_id' => $state['id'], 'name' => 'Ernakulam']);

    $location = $this->actingAs($admin)
        ->postJson(route('location.locations.store', $district['id']), ['name' => 'Kakkanad', 'pincode' => '682030'])
        ->assertCreated()
        ->json();

    $this->assertDatabaseHas('locations', [
        'id' => $location['id'],
        'district_id' => $district['id'],
        'pincode' => '682030',
        'is_active' => true,
    ]);
});

test('a place added from a picker shows up in the lookup it was added to', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $district = District::factory()->create();

    $this->actingAs($admin)
        ->postJson(route('location.locations.store', $district), ['name' => 'Kakkanad'])
        ->assertCreated();

    $this->actingAs($admin)
        ->getJson(route('location.locations', ['district_id' => $district->id]))
        ->assertSuccessful()
        ->assertJsonFragment(['name' => 'Kakkanad']);
});

test('a picker cannot add a location twice to the same district', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $district = District::factory()->create();
    Location::factory()->for($district)->create(['name' => 'Kakkanad']);

    $this->actingAs($admin)
        ->postJson(route('location.locations.store', $district), ['name' => 'Kakkanad'])
        ->assertJsonValidationErrors('name');
});

test('a district added from a picker belongs to the state it was added under', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $state = State::factory()->create();

    $district = $this->actingAs($admin)
        ->postJson(route('location.districts.store', $state), ['name' => 'Thrissur'])
        ->assertCreated()
        ->json();

    expect(District::find($district['id'])->state_id)->toBe($state->id);
});

test('sales executives can add a missing location but not a district', function () {
    $salesExecutive = User::factory()->create();
    $salesExecutive->assignRole('Sales Executive');
    $district = District::factory()->create();

    $this->actingAs($salesExecutive)
        ->postJson(route('location.locations.store', $district), ['name' => 'Kakkanad'])
        ->assertCreated();

    $this->actingAs($salesExecutive)
        ->postJson(route('location.districts.store', $district->state_id), ['name' => 'Thrissur'])
        ->assertForbidden();
});
