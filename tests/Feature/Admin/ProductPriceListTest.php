<?php

use App\Actions\Products\SaveProductBranchPrice;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->kochi = Branch::factory()->create(['name' => 'Kochi', 'code' => 'KOC']);
    $this->calicut = Branch::factory()->create(['name' => 'Calicut', 'code' => 'CAL']);

    $this->supports = ProductCategory::factory()->create(['name' => 'Pipe Supports']);
    $this->product = Product::factory()->create([
        'name' => 'Slotted Channel 1.2 MM',
        'code' => 'SUP-00001',
        'unit' => 'Mtr',
        'product_category_id' => $this->supports->id,
        'tax_percentage' => 18,
    ]);

    $save = app(SaveProductBranchPrice::class);
    $save->handle($this->product, $this->kochi, ['cost' => 58, 'sr_discount' => 12.5, 'sr_rate' => 79.75]);
    $save->handle($this->product, $this->calicut, ['cost' => 58, 'sr_rate' => 85]);
});

test('the price list shows every branch side by side', function () {
    $this->actingAs($this->admin)
        ->get(route('products.price-list'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/products/PriceList')
            ->has('branches', 2)
            ->has('products.data', 1)
            ->where('products.data.0.code', 'SUP-00001')
            ->where('products.data.0.unit', 'Mtr')
            ->where('products.data.0.category', 'Pipe Supports')
            ->where("products.data.0.prices.{$this->kochi->id}.sr_discount", '12.50')
            ->where("products.data.0.prices.{$this->kochi->id}.sr_rate", '79.75')
            ->where("products.data.0.prices.{$this->kochi->id}.sr_rate_with_tax", '94.11')
            ->where("products.data.0.prices.{$this->kochi->id}.pr_discount", null)
            ->where("products.data.0.prices.{$this->calicut->id}.sr_rate", '85.00'));
});

test('the price list can be narrowed to selected branches', function () {
    $this->actingAs($this->admin)
        ->get(route('products.price-list', ['branch_ids' => [$this->kochi->id]]))
        ->assertInertia(fn ($page) => $page
            ->has('branches', 1)
            ->where('branches.0.name', 'Kochi')
            ->has("products.data.0.prices.{$this->kochi->id}")
            ->missing("products.data.0.prices.{$this->calicut->id}"));
});

test('the price list can be filtered by category, brand and unit', function () {
    $brand = Brand::factory()->create(['name' => 'Kaptech']);
    $other = ProductCategory::factory()->create(['name' => 'Valves']);
    Product::factory()->create([
        'name' => 'Ball Valve',
        'product_category_id' => $other->id,
        'brand_id' => $brand->id,
        'unit' => 'Nos',
    ]);

    $this->actingAs($this->admin)
        ->get(route('products.price-list', ['product_category_ids' => [$other->id]]))
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Ball Valve'));

    $this->actingAs($this->admin)
        ->get(route('products.price-list', ['brand_ids' => [$brand->id]]))
        ->assertInertia(fn ($page) => $page->has('products.data', 1));

    $this->actingAs($this->admin)
        ->get(route('products.price-list', ['unit' => 'Mtr']))
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Slotted Channel 1.2 MM'));
});

test('the price list searches name, code and category', function () {
    foreach (['Slotted', 'SUP-00001', 'Pipe Supports'] as $term) {
        $this->actingAs($this->admin)
            ->get(route('products.price-list', ['search' => $term]))
            ->assertInertia(fn ($page) => $page->has('products.data', 1));
    }
});

test('pagination links keep the active filters', function () {
    Product::factory()->count(60)->create(['product_category_id' => $this->supports->id]);

    $this->actingAs($this->admin)
        ->get(route('products.price-list', ['search' => 'Slotted', 'per_page' => 1]))
        ->assertInertia(fn ($page) => $page
            ->where('products.per_page', 1)
            ->has('products.links'));
});

test('the price list is gated on the price permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('products.view');

    $this->actingAs($user)->get(route('products.price-list'))->assertForbidden();

    $user->givePermissionTo('products.price.view');

    $this->actingAs($user)->get(route('products.price-list'))->assertOk();
});

test('a branch a user cannot reach is never shown in the comparison', function () {
    $user = User::factory()->create(['branch_id' => $this->kochi->id]);
    $user->assignRole('Sales Executive');
    $user->branches()->sync([$this->kochi->id]);

    $this->actingAs($user)
        ->get(route('products.price-list', ['branch_ids' => [$this->calicut->id]]))
        ->assertInertia(fn ($page) => $page
            ->has('branches', 1)
            ->where('branches.0.name', 'Kochi'));
});

test('the category page lists its products with their rates', function () {
    $this->actingAs($this->admin)
        ->get(route('product-categories.show', $this->supports))
        ->assertInertia(fn ($page) => $page
            ->component('admin/product-categories/Show')
            ->where('productCategory.name', 'Pipe Supports')
            ->where('stats.products', 1)
            ->where('stats.priced', 1)
            ->has('products.data', 1)
            ->where("products.data.0.prices.{$this->kochi->id}.sr_rate", '79.75'));
});

test('a figure can be edited in place from the price list', function () {
    $this->actingAs($this->admin)
        ->patchJson(route('products.branch-prices.update', [$this->product, $this->kochi]), [
            'sr_discount' => 20,
            'sr_rate' => 90,
        ])
        ->assertOk()
        ->assertJsonPath('sr_discount', '20.00')
        ->assertJsonPath('sr_rate', '90.00')
        // Re-derived from the new rate at the product's 18%.
        ->assertJsonPath('sr_rate_with_tax', '106.20');

    $this->assertDatabaseHas('product_price_histories', [
        'product_id' => $this->product->id,
        'branch_id' => $this->kochi->id,
        'field' => 'sr_rate',
        'reason' => 'Edited on the price list',
    ]);
});

test('clearing a rate on the price list clears its tax inclusive figure', function () {
    $this->actingAs($this->admin)
        ->patchJson(route('products.branch-prices.update', [$this->product, $this->kochi]), ['sr_rate' => null])
        ->assertOk()
        ->assertJsonPath('sr_rate', null)
        ->assertJsonPath('sr_rate_with_tax', null);
});

test('in-place edits need the price permission, branch access and a figure', function () {
    $url = fn (Branch $branch): string => route('products.branch-prices.update', [$this->product, $branch]);

    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['products.view', 'products.price.view']);
    $this->actingAs($viewer)->patchJson($url($this->kochi), ['sr_rate' => 90])->assertForbidden();

    $editor = User::factory()->create(['branch_id' => $this->kochi->id]);
    $editor->givePermissionTo(['products.view', 'products.price.view', 'products.price.update']);
    $editor->branches()->sync([$this->kochi->id]);
    $this->actingAs($editor)->patchJson($url($this->calicut), ['sr_rate' => 90])->assertForbidden();
    $this->actingAs($editor)->patchJson($url($this->kochi), ['sr_rate' => 90])->assertOk();

    $this->actingAs($this->admin)->patchJson($url($this->kochi), ['sr_discount' => -5])->assertJsonValidationErrors('sr_discount');
    $this->actingAs($this->admin)->patchJson($url($this->kochi), [])->assertUnprocessable();
});

test('the product list exposes the code, unit and price count', function () {
    $this->actingAs($this->admin)
        ->get(route('products.index'))
        ->assertInertia(fn ($page) => $page
            ->where('products.data.0.code', 'SUP-00001')
            ->where('products.data.0.unit', 'Mtr')
            ->where('products.data.0.branch_prices_count', 2));
});
