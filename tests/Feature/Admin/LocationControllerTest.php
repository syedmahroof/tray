<?php

use App\Models\Contact;
use App\Models\District;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view locations', function () {
    $user = User::factory()->create();
    $district = District::factory()->create();

    $this->actingAs($user)
        ->get(route('districts.locations.index', $district))
        ->assertForbidden();
});

test('admins can view, create, update, and delete a location for a district', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $district = District::factory()->create();

    $this->actingAs($admin)
        ->get(route('districts.locations.index', $district))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->post(route('districts.locations.store', $district), [
            'name' => 'Kakkanad',
            'pincode' => '682030',
            'is_active' => true,
        ])
        ->assertRedirect(route('districts.locations.index', $district));

    $location = Location::where('name', 'Kakkanad')->first();
    expect($location)->not->toBeNull();
    expect($location->district_id)->toBe($district->id);
    expect($location->pincode)->toBe('682030');

    $this->actingAs($admin)
        ->patch(route('districts.locations.update', [$district, $location]), [
            'name' => 'Kalamassery',
            'is_active' => true,
        ])
        ->assertRedirect(route('districts.locations.index', $district));

    expect($location->refresh()->name)->toBe('Kalamassery');

    $this->actingAs($admin)
        ->delete(route('districts.locations.destroy', [$district, $location]))
        ->assertRedirect(route('districts.locations.index', $district));

    $this->assertModelMissing($location);
});

test('a location route is scoped to its parent district', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $districtA = District::factory()->create();
    $districtB = District::factory()->create();
    $location = Location::factory()->create(['district_id' => $districtA->id]);

    $this->actingAs($admin)
        ->get(route('districts.locations.edit', [$districtB, $location]))
        ->assertNotFound();
});

test('a location name must be unique within its district but may repeat across districts', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $districtA = District::factory()->create();
    $districtB = District::factory()->create();
    Location::factory()->create(['district_id' => $districtA->id, 'name' => 'Kakkanad']);

    $this->actingAs($admin)
        ->post(route('districts.locations.store', $districtA), ['name' => 'Kakkanad'])
        ->assertInvalid(['name']);

    $this->actingAs($admin)
        ->post(route('districts.locations.store', $districtB), ['name' => 'Kakkanad'])
        ->assertRedirect(route('districts.locations.index', $districtB));
});

test('a location still in use cannot be deleted', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $district = District::factory()->create();
    $location = Location::factory()->create(['district_id' => $district->id]);
    Contact::factory()->create(['location_id' => $location->id]);

    $this->actingAs($admin)
        ->delete(route('districts.locations.destroy', [$district, $location]))
        ->assertRedirect();

    $this->assertModelExists($location);
});

test('the location index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $district = District::factory()->create();
    Location::factory()->create(['district_id' => $district->id, 'name' => 'Kakkanad']);
    Location::factory()->create(['district_id' => $district->id, 'name' => 'Aluva']);

    $this->actingAs($admin)
        ->get(route('districts.locations.index', ['district' => $district->id, 'search' => 'Kakkanad']))
        ->assertInertia(fn ($page) => $page
            ->has('locations.data', 1)
            ->where('locations.data.0.name', 'Kakkanad')
            ->where('filters.search', 'Kakkanad'));
});

test('the location lookup returns only the active locations of the given district', function () {
    $user = User::factory()->create();
    $district = District::factory()->create();
    $otherDistrict = District::factory()->create();

    $active = Location::factory()->create(['district_id' => $district->id, 'name' => 'Kakkanad']);
    Location::factory()->create(['district_id' => $district->id, 'is_active' => false]);
    Location::factory()->create(['district_id' => $otherDistrict->id]);

    $this->actingAs($user)
        ->getJson(route('location.locations', ['district_id' => $district->id]))
        ->assertSuccessful()
        ->assertExactJson([['id' => $active->id, 'name' => 'Kakkanad']]);
});

test('a location can be deactivated, which hides it from the lookup', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $district = District::factory()->create();
    $location = Location::factory()->create(['district_id' => $district->id]);

    // An unchecked "Active" box submits nothing at all.
    $this->actingAs($admin)
        ->patch(route('districts.locations.update', [$district, $location]), ['name' => $location->name])
        ->assertRedirect(route('districts.locations.index', $district));

    expect($location->refresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->getJson(route('location.locations', ['district_id' => $district->id]))
        ->assertExactJson([]);
});
