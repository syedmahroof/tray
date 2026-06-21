<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view products', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('products.index'))->assertForbidden();
});

test('managers can create, update, and delete a product', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $category = ProductCategory::factory()->create();

    $this->actingAs($manager)
        ->post(route('products.store'), [
            'product_category_id' => $category->id,
            'name' => 'Unit 12B',
            'price' => 4500000,
            'area_sqft' => 1200,
        ])
        ->assertRedirect(route('products.index'));

    $product = Product::where('name', 'Unit 12B')->first();
    expect($product)->not->toBeNull();
    expect($product->branch_id)->toBe($manager->branch_id);
    expect((float) $product->price)->toBe(4500000.0);

    $this->actingAs($manager)
        ->patch(route('products.update', $product), [
            'product_category_id' => $category->id,
            'name' => 'Unit 12B Renamed',
        ])
        ->assertRedirect(route('products.index'));

    expect($product->refresh()->name)->toBe('Unit 12B Renamed');

    $this->actingAs($manager)
        ->delete(route('products.destroy', $product))
        ->assertRedirect(route('products.index'));

    $this->assertModelMissing($product);
});

test('the product index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Product::factory()->create(['name' => 'Penthouse Suite']);
    Product::factory()->create(['name' => 'Studio Flat']);

    $this->actingAs($admin)
        ->get(route('products.index', ['search' => 'Penthouse']))
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Penthouse Suite')
            ->where('filters.search', 'Penthouse'));
});
