<?php

use App\Models\Branch;
use App\Models\Builder;
use App\Models\Country;
use App\Models\District;
use App\Models\Location;
use App\Models\Route;
use App\Models\State;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('the country picker defaults to India even when another country sorts first', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Branch::factory()->create(['name' => 'Head Office']);

    // "Australia" sorts ahead of "India", so a first-row default would pick it.
    Country::factory()->create(['name' => 'Australia', 'code' => 'AU']);
    $india = Country::factory()->create(['name' => 'India', 'code' => 'IN']);
    $state = State::factory()->create(['country_id' => $india->id, 'name' => 'Kerala']);
    $district = District::factory()->create(['state_id' => $state->id, 'name' => 'Ernakulam']);
    Location::factory()->create(['district_id' => $district->id, 'name' => 'Kakkanad']);
    Route::factory()->create(['name' => 'Kochi North']);

    $this->actingAs($admin);

    $page = visit('/builders/create');

    $page->assertSee('New builder')
        ->assertNoJavaScriptErrors();

    // The country combobox trigger renders the selected label.
    $page->assertSee('India');

    // The state list is populated from the defaulted country.
    $page->click('Select a state')
        ->wait(0.5)
        ->assertSee('Kerala');
});

test('choosing a district loads that district\'s locations', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Branch::factory()->create(['name' => 'Head Office']);

    $india = Country::factory()->create(['name' => 'India', 'code' => 'IN']);
    $state = State::factory()->create(['country_id' => $india->id, 'name' => 'Kerala']);
    $district = District::factory()->create(['state_id' => $state->id, 'name' => 'Ernakulam']);
    Location::factory()->create(['district_id' => $district->id, 'name' => 'Kakkanad']);

    $otherDistrict = District::factory()->create(['state_id' => $state->id, 'name' => 'Thrissur']);
    Location::factory()->create(['district_id' => $otherDistrict->id, 'name' => 'Guruvayur']);

    $this->actingAs($admin);

    $page = visit('/builders/create');
    $page->assertNoJavaScriptErrors();

    $page->click('Select a state')->wait(0.5)->click('Kerala')->wait(0.5);
    $page->click('Select a district')->wait(0.5)->click('Ernakulam')->wait(0.5);
    $page->click('Select a location')->wait(0.5);

    $page->assertSee('Kakkanad')
        ->assertDontSee('Guruvayur')
        ->assertNoJavaScriptErrors();
});

test('the builder list can be narrowed by location and cleared again', function () {
    $branch = Branch::factory()->create(['name' => 'Head Office']);
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $india = Country::factory()->create(['name' => 'India', 'code' => 'IN']);
    $state = State::factory()->create(['country_id' => $india->id, 'name' => 'Kerala']);
    $district = District::factory()->create(['state_id' => $state->id, 'name' => 'Ernakulam']);
    $kakkanad = Location::factory()->create(['district_id' => $district->id, 'name' => 'Kakkanad']);
    $aluva = Location::factory()->create(['district_id' => $district->id, 'name' => 'Aluva']);

    Builder::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Kakkanad Developers',
        'location_id' => $kakkanad->id,
    ]);
    Builder::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Aluva Developers',
        'location_id' => $aluva->id,
    ]);

    $this->actingAs($admin);

    $page = visit('/builders');
    $page->assertSee('Kakkanad Developers')
        ->assertSee('Aluva Developers')
        ->assertNoJavaScriptErrors();

    $page->click('All locations')->wait(0.5);
    $page->click('Kakkanad — Ernakulam')->wait(1);

    $page->assertSee('Kakkanad Developers')
        ->assertDontSee('Aluva Developers')
        ->assertNoJavaScriptErrors();

    $page->click('Clear')->wait(1);

    $page->assertSee('Kakkanad Developers')
        ->assertSee('Aluva Developers')
        ->assertNoJavaScriptErrors();
});

test('a location missing from the picker can be added without leaving the form', function () {
    $branch = Branch::factory()->create(['name' => 'Head Office']);
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $india = Country::factory()->create(['name' => 'India', 'code' => 'IN']);
    $state = State::factory()->create(['country_id' => $india->id, 'name' => 'Kerala']);
    $district = District::factory()->create(['state_id' => $state->id, 'name' => 'Ernakulam']);

    $this->actingAs($admin);

    $page = visit('/builders/create');

    $page->click('Select a state')->wait(0.5)->click('Kerala')->wait(0.5);
    $page->click('Select a district')->wait(0.5)->click('Ernakulam')->wait(0.5);
    $page->click('Select a location')->wait(0.5)->click('Add a new location')->wait(0.5);

    $page->assertSee('New location')
        ->fill('#new-place-name', 'Kakkanad')
        ->fill('#new-place-pincode', '682030')
        ->click('@create-place')
        ->wait(1);

    $location = Location::where('name', 'Kakkanad')->first();
    expect($location)->not->toBeNull();
    expect($location->district_id)->toBe($district->id);
    expect($location->is_active)->toBeTrue();

    // The new location is picked for the builder being created.
    $page->assertSee('Kakkanad')
        ->assertNoJavaScriptErrors();
});

test('the builder list can be narrowed by state, which narrows the district picker', function () {
    $branch = Branch::factory()->create(['name' => 'Head Office']);
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $india = Country::factory()->create(['name' => 'India', 'code' => 'IN']);
    $kerala = State::factory()->create(['country_id' => $india->id, 'name' => 'Kerala']);
    $tamilNadu = State::factory()->create(['country_id' => $india->id, 'name' => 'Tamil Nadu']);
    $ernakulam = District::factory()->create(['state_id' => $kerala->id, 'name' => 'Ernakulam']);
    $chennai = District::factory()->create(['state_id' => $tamilNadu->id, 'name' => 'Chennai']);
    $kakkanad = Location::factory()->create(['district_id' => $ernakulam->id, 'name' => 'Kakkanad']);
    $adyar = Location::factory()->create(['district_id' => $chennai->id, 'name' => 'Adyar']);

    Builder::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Kerala Developers',
        'country_id' => $india->id,
        'state_id' => $kerala->id,
        'district_id' => $ernakulam->id,
        'location_id' => $kakkanad->id,
    ]);
    Builder::factory()->create([
        'branch_id' => $branch->id,
        'name' => 'Chennai Developers',
        'country_id' => $india->id,
        'state_id' => $tamilNadu->id,
        'district_id' => $chennai->id,
        'location_id' => $adyar->id,
    ]);

    $this->actingAs($admin);

    $page = visit('/builders');
    $page->assertSee('Kerala Developers')
        ->assertSee('Chennai Developers')
        ->assertNoJavaScriptErrors();

    $page->click('All states')->wait(0.5)->click('Kerala')->wait(1);

    $page->assertSee('Kerala Developers')
        ->assertDontSee('Chennai Developers');

    // The district picker now only offers districts within the chosen state.
    $page->click('All districts')->wait(0.5);
    $page->assertSee('Ernakulam')
        ->assertDontSee('Chennai')
        ->assertNoJavaScriptErrors();
});

test('a location can be added from the visit report form, where locations are listed flat', function () {
    $branch = Branch::factory()->create(['name' => 'Head Office']);
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $india = Country::factory()->create(['name' => 'India', 'code' => 'IN']);
    $state = State::factory()->create(['country_id' => $india->id, 'name' => 'Kerala']);
    $district = District::factory()->create(['state_id' => $state->id, 'name' => 'Ernakulam']);

    $this->actingAs($admin);

    $page = visit('/visit-reports/create');
    $page->assertNoJavaScriptErrors();

    $page->click('Select a location…')->wait(0.5)->click('Add a new location')->wait(0.5);

    $page->assertSee('New location')
        ->click('Select a district')
        ->wait(0.5)
        ->click('Ernakulam — Kerala')
        ->wait(0.5)
        ->fill('#new-location-name', 'Kakkanad')
        ->click('@create-location')
        ->wait(1);

    $location = Location::where('name', 'Kakkanad')->first();
    expect($location)->not->toBeNull();
    expect($location->district_id)->toBe($district->id);

    // The new location is picked for the visit report being written.
    $page->assertSee('Kakkanad — Ernakulam')
        ->assertNoJavaScriptErrors();
});
