<?php

use App\Models\Branch;
use App\Models\Builder;
use App\Models\Country;
use App\Models\District;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\State;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('an admin can create a builder with a cascading location select', function () {
    $branch = Branch::factory()->create(['name' => 'Head Office']);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $country = Country::factory()->create(['name' => 'India']);
    $state = State::factory()->create(['country_id' => $country->id, 'name' => 'Karnataka']);
    District::factory()->create(['state_id' => $state->id, 'name' => 'Bengaluru Urban']);
    $this->actingAs($admin);

    $page = visit('/builders');
    $page->assertSee('Builders')->assertNoJavaScriptErrors();

    $page->click('New builder')->assertSee('New builder');
    $page->fill('name', 'Acme Developers');

    $page->click('Select a country');
    $page->click('India');

    $page->click('Select a state');
    $page->click('Karnataka');

    $page->click('Select a district');
    $page->click('Bengaluru Urban');

    $page->select('branch_id', 'Head Office');

    $page->click('Create builder');
    $page->assertSee('Builder created.');
    $page->assertSee('Acme Developers');
    $page->assertSee('Bengaluru Urban');

    $builder = Builder::where('name', 'Acme Developers')->first();
    expect($builder->district_id)->not->toBeNull();
    expect($builder->branch_id)->toBe($branch->id);
});

test('an admin can create a project through the UI', function () {
    $branch = Branch::factory()->create(['name' => 'Head Office']);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Builder::factory()->create(['branch_id' => $branch->id, 'name' => 'Acme Developers']);
    ProjectCategory::factory()->create(['name' => 'Residential']);
    $this->actingAs($admin);

    $page = visit('/projects');
    $page->assertSee('Projects')->assertNoJavaScriptErrors();

    $page->click('New project')->assertSee('New project');
    $page->fill('name', 'Skyline Residency');

    $page->click('Select a builder');
    $page->click('Acme Developers');

    $page->click('Select a category');
    $page->click('Residential');

    $page->select('branch_id', 'Head Office');

    $page->click('Create project');
    $page->wait(1.5);
    $page->assertNoJavaScriptErrors();
    $page->assertSee('Skyline Residency');

    $project = Project::where('name', 'Skyline Residency')->first();
    expect($project)->not->toBeNull();
    expect($project->branch_id)->toBe($branch->id);
});

test('an admin can create a product through the UI', function () {
    $branch = Branch::factory()->create(['name' => 'Head Office']);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    ProductCategory::factory()->create(['name' => 'Apartment']);
    $this->actingAs($admin);

    $page = visit('/products');
    $page->assertSee('Products')->assertNoJavaScriptErrors();

    $page->click('New product')->assertSee('New product');
    $page->fill('name', 'Unit 101');

    $page->click('Select a category');
    $page->click('Apartment');

    $page->select('branch_id', 'Head Office');

    $page->click('Create product');
    $page->wait(1.5);
    $page->assertNoJavaScriptErrors();
    $page->assertSee('Unit 101');

    $this->assertDatabaseHas('products', ['name' => 'Unit 101']);
});
