<?php

use App\Models\Builder;
use App\Models\Route;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('routes.index'))->assertForbidden();
});

test('admins can view, create, update, and delete a route', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('routes.index'))
        ->assertSuccessful();

    $this->actingAs($admin)
        ->post(route('routes.store'), ['name' => 'Kochi North', 'is_active' => true])
        ->assertRedirect(route('routes.index'));

    $sales = Route::where('name', 'Kochi North')->first();
    expect($sales)->not->toBeNull();
    expect($sales->is_active)->toBeTrue();

    $this->actingAs($admin)
        ->patch(route('routes.update', $sales), ['name' => 'Kochi South', 'is_active' => true])
        ->assertRedirect(route('routes.index'));

    expect($sales->refresh()->name)->toBe('Kochi South');

    $this->actingAs($admin)
        ->delete(route('routes.destroy', $sales))
        ->assertRedirect(route('routes.index'));

    $this->assertModelMissing($sales);
});

test('a route name must be unique', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Route::factory()->create(['name' => 'Kochi North']);

    $this->actingAs($admin)
        ->post(route('routes.store'), ['name' => 'Kochi North'])
        ->assertInvalid(['name']);
});

test('a route still in use cannot be deleted', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $sales = Route::factory()->create();
    Builder::factory()->create(['route_id' => $sales->id]);

    $this->actingAs($admin)
        ->delete(route('routes.destroy', $sales))
        ->assertRedirect();

    $this->assertModelExists($sales);
});

test('the route index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Route::factory()->create(['name' => 'Kochi North']);
    Route::factory()->create(['name' => 'Thrissur East']);

    $this->actingAs($admin)
        ->get(route('routes.index', ['search' => 'Kochi']))
        ->assertInertia(fn ($page) => $page
            ->has('routes.data', 1)
            ->where('routes.data.0.name', 'Kochi North')
            ->where('filters.search', 'Kochi'));
});
