<?php

use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view product categories', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('product-categories.index'))
        ->assertForbidden();
});

test('managers can create, update, and delete a product category', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->get(route('product-categories.index'))
        ->assertSuccessful();

    $this->actingAs($manager)
        ->post(route('product-categories.store'), ['name' => 'Apartment', 'is_active' => true])
        ->assertRedirect(route('product-categories.index'));

    $category = ProductCategory::where('name', 'Apartment')->first();
    expect($category)->not->toBeNull();
    expect($category->is_active)->toBeTrue();

    $this->actingAs($manager)
        ->patch(route('product-categories.update', $category), ['name' => 'Apartment'])
        ->assertRedirect(route('product-categories.index'));

    expect($category->refresh()->is_active)->toBeFalse();

    $this->actingAs($manager)
        ->delete(route('product-categories.destroy', $category))
        ->assertRedirect(route('product-categories.index'));

    $this->assertModelMissing($category);
});

test('product category names must be unique', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    ProductCategory::factory()->create(['name' => 'Apartment']);

    $this->actingAs($manager)
        ->post(route('product-categories.store'), ['name' => 'Apartment'])
        ->assertInvalid(['name']);
});

test('the product category index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    ProductCategory::factory()->create(['name' => 'Apartments']);
    ProductCategory::factory()->create(['name' => 'Villas']);

    $this->actingAs($admin)
        ->get(route('product-categories.index', ['search' => 'Apart']))
        ->assertInertia(fn ($page) => $page
            ->has('productCategories.data', 1)
            ->where('productCategories.data.0.name', 'Apartments')
            ->where('filters.search', 'Apart'));
});
