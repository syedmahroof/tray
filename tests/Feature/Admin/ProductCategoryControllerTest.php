<?php

use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

test('a product category image can be uploaded and removed', function () {
    Storage::fake('public');
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->post(route('product-categories.store'), [
            'name' => 'Cable Tray',
            'is_active' => true,
            'image' => UploadedFile::fake()->image('tray.jpg'),
        ])
        ->assertRedirect(route('product-categories.index'));

    $category = ProductCategory::where('name', 'Cable Tray')->first();
    $path = $category->image_path;
    expect($path)->toStartWith('product-categories/');
    Storage::disk('public')->assertExists($path);

    $this->actingAs($manager)
        ->get(route('product-categories.index'))
        ->assertInertia(fn ($page) => $page->where('productCategories.data.0.image_url', $category->image_url));

    $this->actingAs($manager)
        ->get(route('product-categories.show', $category))
        ->assertInertia(fn ($page) => $page->where('productCategory.image_url', $category->image_url));

    // The edit dialog posts with a spoofed PUT so files survive the trip.
    $this->actingAs($manager)
        ->post(route('product-categories.update', $category), [
            '_method' => 'put',
            'name' => 'Cable Tray',
            'is_active' => true,
            'remove_image' => '1',
        ])
        ->assertRedirect(route('product-categories.index'));

    expect($category->refresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
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
