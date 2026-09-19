<?php

use App\Models\Branch;
use App\Models\Builder;
use App\Models\Contact;
use App\Models\ContactType;
use App\Models\Customer;
use App\Models\District;
use App\Models\Location;
use App\Models\Project;
use App\Models\ProjectCategory;
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
    $this->location = Location::factory()->create(['district_id' => $this->district->id]);
    $this->salesRoute = Route::factory()->create();
});

test('a builder stores its location and route', function () {
    $this->actingAs($this->admin)
        ->post(route('builders.store'), [
            'name' => 'Acme Developers',
            'branch_id' => $this->branch->id,
            'district_id' => $this->district->id,
            'location_id' => $this->location->id,
            'route_id' => $this->salesRoute->id,
        ])
        ->assertRedirect(route('builders.index'));

    $builder = Builder::where('name', 'Acme Developers')->firstOrFail();
    expect($builder->location_id)->toBe($this->location->id);
    expect($builder->route_id)->toBe($this->salesRoute->id);
});

test('a customer stores its location and route', function () {
    $this->actingAs($this->admin)
        ->post(route('customers.store'), [
            'name' => 'Kochi Interiors',
            'branch_id' => $this->branch->id,
            'district_id' => $this->district->id,
            'location_id' => $this->location->id,
            'route_id' => $this->salesRoute->id,
        ])
        ->assertRedirect();

    $customer = Customer::where('name', 'Kochi Interiors')->firstOrFail();
    expect($customer->location_id)->toBe($this->location->id);
    expect($customer->route_id)->toBe($this->salesRoute->id);
});

test('a contact stores its location and route', function () {
    $contactType = ContactType::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('contacts.store'), [
            'contact_type_id' => $contactType->id,
            'name' => 'Jane Prospect',
            'branch_id' => $this->branch->id,
            'district_id' => $this->district->id,
            'location_id' => $this->location->id,
            'route_id' => $this->salesRoute->id,
        ])
        ->assertRedirect();

    $contact = Contact::where('name', 'Jane Prospect')->firstOrFail();
    expect($contact->location_id)->toBe($this->location->id);
    expect($contact->route_id)->toBe($this->salesRoute->id);
});

test('a project stores its location and route', function () {
    $category = ProjectCategory::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('projects.store'), [
            'name' => 'Marina Towers',
            'project_category_id' => $category->id,
            'status' => 'planning',
            'branch_id' => $this->branch->id,
            'district_id' => $this->district->id,
            'location_id' => $this->location->id,
            'route_id' => $this->salesRoute->id,
        ])
        ->assertRedirect();

    $project = Project::where('name', 'Marina Towers')->firstOrFail();
    expect($project->location_id)->toBe($this->location->id);
    expect($project->route_id)->toBe($this->salesRoute->id);
    expect($project->locationMaster->is($this->location))->toBeTrue();
});

test('a visit report stores its location and route', function () {
    $builder = Builder::factory()->create(['branch_id' => $this->branch->id]);

    $this->actingAs($this->admin)
        ->post(route('visit-reports.store'), [
            'visit_date' => '2026-06-01',
            'visit_type' => 'Site Visit',
            'objective' => 'Discuss the tower handover',
            'branch_id' => $this->branch->id,
            'builder_ids' => [$builder->id],
            'location_id' => $this->location->id,
            'route_id' => $this->salesRoute->id,
        ])
        ->assertRedirect();

    $report = VisitReport::query()->latest('id')->firstOrFail();
    expect($report->location_id)->toBe($this->location->id);
    expect($report->route_id)->toBe($this->salesRoute->id);
});

test('a location outside the chosen district is rejected', function () {
    $otherLocation = Location::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('builders.store'), [
            'name' => 'Acme Developers',
            'branch_id' => $this->branch->id,
            'district_id' => $this->district->id,
            'location_id' => $otherLocation->id,
        ])
        ->assertInvalid(['location_id']);
});

test('the builder and contact forms are given the route master to choose from', function () {
    $this->actingAs($this->admin)
        ->get(route('builders.create'))
        ->assertInertia(fn ($page) => $page
            ->has('routes', 1)
            ->where('routes.0.name', $this->salesRoute->name));

    $this->actingAs($this->admin)
        ->get(route('contacts.create'))
        ->assertInertia(fn ($page) => $page->has('routes', 1));
});

test('inactive routes are kept out of the pickers', function () {
    Route::factory()->create(['is_active' => false]);

    $this->actingAs($this->admin)
        ->get(route('builders.create'))
        ->assertInertia(fn ($page) => $page->has('routes', 1));
});
