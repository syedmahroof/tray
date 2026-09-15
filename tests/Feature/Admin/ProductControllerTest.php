<?php

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view products', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('products.index'))->assertForbidden();
});

test('the product show page lists the projects it is linked to', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $product = Product::factory()->create();
    $project = Project::factory()->create(['name' => 'Linked Project']);
    $project->products()->attach($product);

    $this->actingAs($admin)
        ->get(route('products.show', $product))
        ->assertInertia(fn ($page) => $page
            ->has('product.projects', 1)
            ->where('product.projects.0.name', 'Linked Project'));
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
    expect($product->created_by)->toBe($manager->id);
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

test('a product image can be uploaded, replaced and removed', function () {
    Storage::fake('public');
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $category = ProductCategory::factory()->create();
    $fields = ['product_category_id' => $category->id, 'name' => 'Slotted Channel'];

    $this->actingAs($manager)
        ->post(route('products.store'), [...$fields, 'image' => UploadedFile::fake()->image('channel.jpg')])
        ->assertRedirect(route('products.index'));

    $product = Product::where('name', 'Slotted Channel')->first();
    $first = $product->image_path;
    expect($first)->toStartWith('products/');
    expect($product->image_url)->toContain('/storage/products/');
    Storage::disk('public')->assertExists($first);

    $this->actingAs($manager)
        ->get(route('products.index'))
        ->assertInertia(fn ($page) => $page->where('products.data.0.image_url', $product->image_url));

    // A new upload replaces the file rather than leaving the old one behind.
    $this->actingAs($manager)
        ->patch(route('products.update', $product), [...$fields, 'image' => UploadedFile::fake()->image('channel-v2.png')])
        ->assertRedirect();

    $second = $product->refresh()->image_path;
    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);

    $this->actingAs($manager)
        ->patch(route('products.update', $product), [...$fields, 'remove_image' => '1'])
        ->assertRedirect();

    expect($product->refresh()->image_path)->toBeNull();
    Storage::disk('public')->assertMissing($second);
});

test('a product image must be a picture of a sensible size', function () {
    Storage::fake('public');
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $fields = ['product_category_id' => ProductCategory::factory()->create()->id, 'name' => 'Channel'];

    $this->actingAs($manager)
        ->post(route('products.store'), [...$fields, 'image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('image');

    $this->actingAs($manager)
        ->post(route('products.store'), [...$fields, 'image' => UploadedFile::fake()->image('huge.jpg')->size(3000)])
        ->assertSessionHasErrors('image');
});

test('a product can be created with HSN code and GST tax fields', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $category = ProductCategory::factory()->create();

    $this->actingAs($manager)
        ->post(route('products.store'), [
            'product_category_id' => $category->id,
            'name' => 'Taxed Unit',
            'hsn_code' => '9403',
            'price' => 118000,
            'taxable_amount' => 100000,
            'tax_type' => 'GST 18%',
            'tax_percentage' => 18,
        ])
        ->assertRedirect(route('products.index'));

    $product = Product::where('name', 'Taxed Unit')->first();
    expect($product->hsn_code)->toBe('9403');
    expect($product->tax_type)->toBe('GST 18%');
    expect((float) $product->tax_percentage)->toBe(18.0);
    expect((float) $product->taxable_amount)->toBe(100000.0);
});

test('a product can be created with a brand', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $category = ProductCategory::factory()->create();
    $brand = Brand::factory()->create();

    $this->actingAs($manager)
        ->post(route('products.store'), [
            'product_category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Branded Unit',
        ])
        ->assertRedirect(route('products.index'));

    expect(Product::where('name', 'Branded Unit')->first()->brand_id)->toBe($brand->id);
});

test('an empty brand selection is stored as no brand', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $category = ProductCategory::factory()->create();

    $this->actingAs($manager)
        ->post(route('products.store'), [
            'product_category_id' => $category->id,
            'brand_id' => '',
            'name' => 'Brandless Unit',
        ])
        ->assertRedirect(route('products.index'));

    expect(Product::where('name', 'Brandless Unit')->first()->brand_id)->toBeNull();
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

test('the product index can be filtered by creator and created date range', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $creator = User::factory()->create();
    Product::factory()->create([
        'name' => 'Creator Unit',
        'created_by' => $creator->id,
        'created_at' => '2026-06-15 12:00:00',
    ]);
    Product::factory()->create([
        'name' => 'Older Unit',
        'created_at' => '2026-01-10 12:00:00',
    ]);

    $this->actingAs($admin)
        ->get(route('products.index', ['created_by' => $creator->id]))
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Creator Unit'));

    $this->actingAs($admin)
        ->get(route('products.index', ['created_from' => '2026-06-01', 'created_to' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Creator Unit'));
});

test('a product created without a code is given a generated one', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $category = ProductCategory::factory()->create(['name' => 'Supports']);
    $brand = Brand::factory()->create(['name' => 'Leader']);

    $this->actingAs($manager)
        ->post(route('products.store'), [
            'product_category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'Slotted Channel 1.2 MM',
            'code' => '',
        ])
        ->assertRedirect(route('products.index'));

    expect(Product::where('name', 'Slotted Channel 1.2 MM')->value('code'))->toBe('SUP-LEA-00001');
});

test('a product keeps the code the user supplies', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $category = ProductCategory::factory()->create();

    $this->actingAs($manager)
        ->post(route('products.store'), [
            'product_category_id' => $category->id,
            'name' => 'Threaded Rod 8 MM',
            'code' => 'KAP-TR-008',
            'unit' => 'Mtr',
        ])
        ->assertRedirect(route('products.index'));

    $product = Product::where('name', 'Threaded Rod 8 MM')->first();

    expect($product->code)->toBe('KAP-TR-008');
    expect($product->unit)->toBe('Mtr');
});

test('a product code must be unique', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $category = ProductCategory::factory()->create();
    Product::factory()->create(['code' => 'DUP-001']);

    $this->actingAs($manager)
        ->post(route('products.store'), [
            'product_category_id' => $category->id,
            'name' => 'Clashing Product',
            'code' => 'DUP-001',
        ])
        ->assertSessionHasErrors('code');
});
