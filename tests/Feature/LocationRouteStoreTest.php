<?php

use App\Models\Route;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('a route can be added from the location picker', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $manager->givePermissionTo('routes.create');

    $this->actingAs($manager)
        ->postJson(route('location.routes.store'), ['name' => 'Kochi South'])
        ->assertCreated()
        ->assertJsonPath('name', 'Kochi South');

    expect(Route::where('name', 'Kochi South')->value('is_active'))->toBeTrue();
});

test('a duplicate route name is rejected', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Route::factory()->create(['name' => 'Kochi South']);

    $this->actingAs($admin)
        ->postJson(route('location.routes.store'), ['name' => 'Kochi South'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('users without the route create permission cannot add one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('location.routes.store'), ['name' => 'Kochi South'])
        ->assertForbidden();
});
