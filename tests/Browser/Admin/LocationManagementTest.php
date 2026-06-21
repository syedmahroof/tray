<?php

use App\Models\Country;
use App\Models\ProjectCategory;
use App\Models\State;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('an admin can drill down from country to state to district through the UI', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $page = visit('/countries');

    $page->assertSee('Countries')
        ->assertNoJavaScriptErrors()
        ->click('New country')
        ->fill('name', 'India')
        ->fill('code', 'IN')
        ->click('Create country')
        ->assertSee('Country created.')
        ->assertSee('India');

    $country = Country::where('code', 'IN')->first();

    $page->click("@states-{$country->id}")
        ->assertSee('India states')
        ->click('New state')
        ->fill('name', 'Karnataka')
        ->fill('code', 'KA')
        ->click('Create state')
        ->assertSee('State created.')
        ->assertSee('Karnataka');

    $state = State::where('name', 'Karnataka')->first();

    $page->click("@districts-{$state->id}")
        ->assertSee('Karnataka districts')
        ->click('New district')
        ->fill('name', 'Bengaluru Urban')
        ->click('Create district')
        ->assertSee('District created.')
        ->assertSee('Bengaluru Urban');

    $this->assertDatabaseHas('districts', ['name' => 'Bengaluru Urban', 'state_id' => $state->id]);
});

test('an admin can create and edit a project category through the modal UI', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $page = visit('/project-categories');

    $page->assertSee('Project Categories')
        ->assertNoJavaScriptErrors()
        ->click('New project category')
        ->fill('name', 'Residential')
        ->click('Create')
        ->assertSee('Project category created.')
        ->assertSee('Residential')
        ->assertSee('Active');

    $category = ProjectCategory::where('name', 'Residential')->first();

    $page->click("@edit-{$category->id}")
        ->assertSee('Edit Residential')
        ->uncheck('edit-is-active')
        ->click('Save')
        ->assertSee('Project category updated.')
        ->assertSee('Inactive');
});
