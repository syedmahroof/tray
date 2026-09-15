<?php

use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view brands', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('brands.index'))
        ->assertForbidden();
});

test('managers can create, update, and delete a brand', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->get(route('brands.index'))
        ->assertSuccessful();

    $this->actingAs($manager)
        ->post(route('brands.store'), ['name' => 'Astral', 'is_active' => true])
        ->assertRedirect(route('brands.index'));

    $brand = Brand::where('name', 'Astral')->first();
    expect($brand)->not->toBeNull();
    expect($brand->is_active)->toBeTrue();

    $this->actingAs($manager)
        ->patch(route('brands.update', $brand), ['name' => 'Astral'])
        ->assertRedirect(route('brands.index'));

    expect($brand->refresh()->is_active)->toBeFalse();

    $this->actingAs($manager)
        ->delete(route('brands.destroy', $brand))
        ->assertRedirect(route('brands.index'));

    $this->assertModelMissing($brand);
});

test('a brand logo can be uploaded, replaced and removed', function () {
    Storage::fake('public');
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->post(route('brands.store'), [
            'name' => 'Fibrocast',
            'is_active' => true,
            'logo' => UploadedFile::fake()->image('fibrocast.png'),
        ])
        ->assertRedirect(route('brands.index'));

    $brand = Brand::where('name', 'Fibrocast')->first();
    $first = $brand->logo_path;
    expect($first)->toStartWith('brands/');
    expect($brand->logo_url)->toContain('/storage/brands/');

    $this->actingAs($manager)
        ->get(route('brands.index'))
        ->assertInertia(fn ($page) => $page->where('brands.data.0.logo_url', $brand->logo_url));

    $this->actingAs($manager)
        ->post(route('brands.update', $brand), [
            '_method' => 'put',
            'name' => 'Fibrocast',
            'is_active' => true,
            'logo' => UploadedFile::fake()->image('fibrocast-2024.png'),
        ])
        ->assertRedirect(route('brands.index'));

    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($brand->refresh()->logo_path);

    $this->actingAs($manager)
        ->post(route('brands.update', $brand), [
            '_method' => 'put',
            'name' => 'Fibrocast',
            'remove_logo' => '1',
        ])
        ->assertRedirect(route('brands.index'));

    expect($brand->refresh()->logo_path)->toBeNull();
});

test('brand names must be unique', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    Brand::factory()->create(['name' => 'Astral']);

    $this->actingAs($manager)
        ->post(route('brands.store'), ['name' => 'Astral'])
        ->assertInvalid(['name']);
});

test('a brand with products cannot be deleted', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $brand = Brand::factory()->create();
    Product::factory()->create(['brand_id' => $brand->id]);

    $this->actingAs($manager)
        ->delete(route('brands.destroy', $brand))
        ->assertRedirect();

    $this->assertModelExists($brand);
});

test('the brand index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Brand::factory()->create(['name' => 'Finolex']);
    Brand::factory()->create(['name' => 'Supreme']);

    $this->actingAs($admin)
        ->get(route('brands.index', ['search' => 'Finolex']))
        ->assertInertia(fn ($page) => $page
            ->has('brands.data', 1)
            ->where('brands.data.0.name', 'Finolex')
            ->where('filters.search', 'Finolex'));
});
