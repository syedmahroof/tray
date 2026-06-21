<?php

use App\Models\ProjectCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view project categories', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('project-categories.index'))
        ->assertForbidden();
});

test('managers can create, update, and delete a project category', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->get(route('project-categories.index'))
        ->assertSuccessful();

    $this->actingAs($manager)
        ->post(route('project-categories.store'), ['name' => 'Residential', 'is_active' => true])
        ->assertRedirect(route('project-categories.index'));

    $category = ProjectCategory::where('name', 'Residential')->first();
    expect($category)->not->toBeNull();
    expect($category->is_active)->toBeTrue();

    $this->actingAs($manager)
        ->patch(route('project-categories.update', $category), ['name' => 'Residential'])
        ->assertRedirect(route('project-categories.index'));

    expect($category->refresh()->is_active)->toBeFalse();

    $this->actingAs($manager)
        ->delete(route('project-categories.destroy', $category))
        ->assertRedirect(route('project-categories.index'));

    $this->assertModelMissing($category);
});

test('project category names must be unique', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    ProjectCategory::factory()->create(['name' => 'Residential']);

    $this->actingAs($manager)
        ->post(route('project-categories.store'), ['name' => 'Residential'])
        ->assertInvalid(['name']);
});

test('the project category index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    ProjectCategory::factory()->create(['name' => 'Residential']);
    ProjectCategory::factory()->create(['name' => 'Commercial']);

    $this->actingAs($admin)
        ->get(route('project-categories.index', ['search' => 'Resid']))
        ->assertInertia(fn ($page) => $page
            ->has('projectCategories.data', 1)
            ->where('projectCategories.data.0.name', 'Residential')
            ->where('filters.search', 'Resid'));
});
