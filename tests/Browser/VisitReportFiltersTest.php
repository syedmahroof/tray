<?php

use App\Models\Branch;
use App\Models\Location;
use App\Models\Project;
use App\Models\Route;
use App\Models\User;
use App\Models\VisitReport;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('every visit report filter carries a title', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $branch = Branch::factory()->create();

    $location = Location::factory()->create();
    Route::factory()->create();
    $project = Project::factory()->create([
        'branch_id' => $branch->id,
        'country_id' => $location->district->state->country_id,
        'state_id' => $location->district->state_id,
        'district_id' => $location->district_id,
        'location_id' => $location->id,
    ]);
    VisitReport::factory()->create(['branch_id' => $branch->id])->projects()->attach($project);

    $this->actingAs($admin);

    visit('/visit-reports')
        ->assertNoJavaScriptErrors()
        ->assertSee('Search')
        ->assertSee('Visit Type')
        ->assertSee('Reported By')
        ->assertSee('Project')
        ->assertSee('Visit Date')
        ->assertSee('Country')
        ->assertSee('State')
        ->assertSee('District')
        ->assertSee('Location')
        ->assertSee('Route');
});
