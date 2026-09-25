<?php

use App\Models\Branch;
use App\Models\Builder;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\District;
use App\Models\Location;
use App\Models\Project;
use App\Models\Route;
use App\Models\User;
use App\Models\VisitReport;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->branch = Branch::factory()->create();
    $this->admin = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->admin->assignRole('Admin');

    $this->district = District::factory()->create();
    $this->otherDistrict = District::factory()->create();
    $this->location = Location::factory()->create(['district_id' => $this->district->id, 'name' => 'Kakkanad']);
    $this->otherLocation = Location::factory()->create(['district_id' => $this->district->id, 'name' => 'Aluva']);
    $this->otherDistrictLocation = Location::factory()->create(['district_id' => $this->otherDistrict->id, 'name' => 'Guruvayur']);
    $this->salesRoute = Route::factory()->create(['name' => 'Kochi North']);
    $this->otherRoute = Route::factory()->create(['name' => 'Thrissur East']);
});

test('builders can be filtered by location and by route', function () {
    $match = Builder::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Acme Developers',
        'location_id' => $this->location->id,
        'route_id' => $this->salesRoute->id,
    ]);
    Builder::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Other Developers',
        'location_id' => $this->otherLocation->id,
        'route_id' => $this->otherRoute->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('builders.index', ['location_id' => $this->location->id]))
        ->assertInertia(fn ($page) => $page
            ->has('builders.data', 1)
            ->where('builders.data.0.id', $match->id)
            ->where('filters.location_id', (string) $this->location->id));

    $this->actingAs($this->admin)
        ->get(route('builders.index', ['route_id' => $this->salesRoute->id]))
        ->assertInertia(fn ($page) => $page
            ->has('builders.data', 1)
            ->where('builders.data.0.id', $match->id));
});

test('the two filters combine rather than replace each other', function () {
    Builder::factory()->create([
        'branch_id' => $this->branch->id,
        'location_id' => $this->location->id,
        'route_id' => $this->otherRoute->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('builders.index', [
            'location_id' => $this->location->id,
            'route_id' => $this->salesRoute->id,
        ]))
        ->assertInertia(fn ($page) => $page->has('builders.data', 0));
});

test('customers can be filtered by location and route', function () {
    $match = Customer::factory()->create([
        'branch_id' => $this->branch->id,
        'location_id' => $this->location->id,
        'route_id' => $this->salesRoute->id,
    ]);
    Customer::factory()->create(['branch_id' => $this->branch->id]);

    $this->actingAs($this->admin)
        ->get(route('customers.index', ['location_id' => $this->location->id]))
        ->assertInertia(fn ($page) => $page
            ->has('customers.data', 1)
            ->where('customers.data.0.id', $match->id));

    $this->actingAs($this->admin)
        ->get(route('customers.index', ['route_id' => $this->salesRoute->id]))
        ->assertInertia(fn ($page) => $page->has('customers.data', 1));
});

test('contacts can be filtered by location and route', function () {
    $match = Contact::factory()->create([
        'branch_id' => $this->branch->id,
        'location_id' => $this->location->id,
        'route_id' => $this->salesRoute->id,
    ]);
    Contact::factory()->create(['branch_id' => $this->branch->id]);

    $this->actingAs($this->admin)
        ->get(route('contacts.index', ['location_id' => $this->location->id]))
        ->assertInertia(fn ($page) => $page
            ->has('contacts.data', 1)
            ->where('contacts.data.0.id', $match->id));

    $this->actingAs($this->admin)
        ->get(route('contacts.index', ['route_id' => $this->salesRoute->id]))
        ->assertInertia(fn ($page) => $page->has('contacts.data', 1));
});

test('projects can be filtered by location and route', function () {
    $match = Project::factory()->create([
        'branch_id' => $this->branch->id,
        'location_id' => $this->location->id,
        'route_id' => $this->salesRoute->id,
    ]);
    Project::factory()->create(['branch_id' => $this->branch->id]);

    $this->actingAs($this->admin)
        ->get(route('projects.index', ['location_id' => $this->location->id]))
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.id', $match->id));

    $this->actingAs($this->admin)
        ->get(route('projects.index', ['route_id' => $this->salesRoute->id]))
        ->assertInertia(fn ($page) => $page->has('projects.data', 1));
});

test('visit reports can be filtered by location and route', function () {
    $match = VisitReport::factory()->create([
        'branch_id' => $this->branch->id,
        'location_id' => $this->location->id,
        'route_id' => $this->salesRoute->id,
    ]);
    VisitReport::factory()->create(['branch_id' => $this->branch->id]);

    $this->actingAs($this->admin)
        ->get(route('visit-reports.index', ['location_id' => $this->location->id]))
        ->assertInertia(fn ($page) => $page
            ->has('visitReports.data', 1)
            ->where('visitReports.data.0.id', $match->id));

    $this->actingAs($this->admin)
        ->get(route('visit-reports.index', ['route_id' => $this->salesRoute->id]))
        ->assertInertia(fn ($page) => $page->has('visitReports.data', 1));
});

test('the pickers offer every active location and route, used or not', function () {
    Builder::factory()->create([
        'branch_id' => $this->branch->id,
        'location_id' => $this->location->id,
        'route_id' => $this->salesRoute->id,
    ]);
    Location::factory()->create(['district_id' => $this->district->id, 'name' => 'Closed', 'is_active' => false]);
    Route::factory()->create(['name' => 'Retired Route', 'is_active' => false]);

    // The other locations and route belong to no builder, but are still offered.
    $this->actingAs($this->admin)
        ->get(route('builders.index'))
        ->assertInertia(fn ($page) => $page
            ->has('locations', 3)
            ->where('locations.0.name', 'Aluva')
            ->where('locations.1.name', 'Guruvayur')
            ->where('locations.2.name', 'Kakkanad')
            ->where('locations.2.district.name', $this->location->district->name)
            ->has('routes', 2)
            ->where('routes.0.name', 'Kochi North')
            ->where('routes.1.name', 'Thrissur East'));
});

test('the builder export honours the location and route filters', function () {
    Builder::factory()->create([
        'branch_id' => $this->branch->id,
        'location_id' => $this->location->id,
    ]);
    Builder::factory()->create(['branch_id' => $this->branch->id]);

    $this->actingAs($this->admin)
        ->get(route('builders.export', ['location_id' => $this->location->id]))
        ->assertSuccessful();
});

test('builders can be filtered by district, state, and country', function () {
    $match = Builder::factory()->create([
        'branch_id' => $this->branch->id,
        'country_id' => $this->district->state->country_id,
        'state_id' => $this->district->state_id,
        'district_id' => $this->district->id,
        'location_id' => $this->location->id,
    ]);
    Builder::factory()->create([
        'branch_id' => $this->branch->id,
        'country_id' => $this->otherDistrict->state->country_id,
        'state_id' => $this->otherDistrict->state_id,
        'district_id' => $this->otherDistrict->id,
        'location_id' => $this->otherDistrictLocation->id,
    ]);

    foreach ([
        'district_id' => $this->district->id,
        'state_id' => $this->district->state_id,
        'country_id' => $this->district->state->country_id,
    ] as $filter => $value) {
        $this->actingAs($this->admin)
            ->get(route('builders.index', [$filter => $value]))
            ->assertInertia(fn ($page) => $page
                ->has('builders.data', 1)
                ->where('builders.data.0.id', $match->id)
                ->where("filters.{$filter}", (string) $value));
    }
});

test('visit reports are filtered by district through their location', function () {
    $match = VisitReport::factory()->create([
        'branch_id' => $this->branch->id,
        'location_id' => $this->location->id,
    ]);
    VisitReport::factory()->create([
        'branch_id' => $this->branch->id,
        'location_id' => $this->otherDistrictLocation->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('visit-reports.index', ['district_id' => $this->district->id]))
        ->assertInertia(fn ($page) => $page
            ->has('visitReports.data', 1)
            ->where('visitReports.data.0.id', $match->id));

    $this->actingAs($this->admin)
        ->get(route('visit-reports.index', ['state_id' => $this->district->state_id]))
        ->assertInertia(fn ($page) => $page->has('visitReports.data', 1));
});

test('the place pickers only offer the districts, states, and countries in use', function () {
    Builder::factory()->create([
        'branch_id' => $this->branch->id,
        'country_id' => $this->district->state->country_id,
        'state_id' => $this->district->state_id,
        'district_id' => $this->district->id,
        'location_id' => $this->location->id,
    ]);

    // $otherDistrict belongs to no builder, so neither it nor its state shows up.
    $this->actingAs($this->admin)
        ->get(route('builders.index'))
        ->assertInertia(fn ($page) => $page
            ->has('districts', 1)
            ->where('districts.0.id', $this->district->id)
            ->where('districts.0.state_id', $this->district->state_id)
            ->has('states', 1)
            ->where('states.0.id', $this->district->state_id)
            ->has('countries', 1)
            ->where('countries.0.id', $this->district->state->country_id));
});

test('place filters narrow each other rather than replace', function () {
    Builder::factory()->create([
        'branch_id' => $this->branch->id,
        'state_id' => $this->district->state_id,
        'district_id' => $this->district->id,
        'location_id' => $this->location->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('builders.index', [
            'state_id' => $this->district->state_id,
            'district_id' => $this->otherDistrict->id,
        ]))
        ->assertInertia(fn ($page) => $page->has('builders.data', 0));
});
