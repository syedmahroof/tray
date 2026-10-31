<?php

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
});

test('a product can be deactivated and reactivated from the admin form', function () {
    $category = ProductCategory::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('products.store'), [
            'product_category_id' => $category->id,
            'name' => 'GI Clamp',
            'is_active' => '1',
        ])
        ->assertRedirect(route('products.index'));

    $product = Product::where('name', 'GI Clamp')->firstOrFail();
    expect($product->is_active)->toBeTrue();

    $this->actingAs($this->admin)
        ->patch(route('products.update', $product), [
            'product_category_id' => $category->id,
            'name' => 'GI Clamp',
        ])
        ->assertRedirect(route('products.index'));

    expect($product->refresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)
        ->patch(route('products.update', $product), [
            'product_category_id' => $category->id,
            'name' => 'GI Clamp',
            'is_active' => '1',
        ]);

    expect($product->refresh()->is_active)->toBeTrue();
});

test('the admin product list still shows inactive products', function () {
    Product::factory()->inactive()->create(['name' => 'Retired Cover']);

    $this->actingAs($this->admin)
        ->get(route('products.index'))
        ->assertInertia(fn ($page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.is_active', false));
});

test('inactive products are not offered on the quotation form', function () {
    Product::factory()->create(['name' => 'Active Cover']);
    Product::factory()->inactive()->create(['name' => 'Retired Cover']);

    $this->actingAs($this->admin)
        ->get(route('quotations.create'))
        ->assertInertia(fn ($page) => $page
            ->has('products', 1)
            ->where('products.0.name', 'Active Cover'));
});

test('editing a quotation keeps the inactive products it already uses', function () {
    $branch = Branch::factory()->create();
    Product::factory()->create(['name' => 'Active Cover']);
    $retired = Product::factory()->inactive()->create(['name' => 'Retired Cover']);
    Product::factory()->inactive()->create(['name' => 'Unused Retired Cover']);

    $quotation = Quotation::factory()->create([
        'branch_id' => $branch->id,
        'contact_id' => Contact::factory()->create(['branch_id' => $branch->id])->id,
    ]);
    $quotation->items()->create(['product_id' => $retired->id, 'description' => 'Retired Cover', 'quantity' => 1, 'unit_price' => 50]);

    $this->actingAs($this->admin)
        ->get(route('quotations.edit', $quotation))
        ->assertInertia(fn ($page) => $page
            ->has('products', 2)
            ->where('products.0.name', 'Active Cover')
            ->where('products.1.name', 'Retired Cover'));
});

test('inactive products are hidden from order, enquiry and project pickers', function () {
    Product::factory()->create(['name' => 'Active Cover']);
    Product::factory()->inactive()->create(['name' => 'Retired Cover']);

    foreach (['sales-orders.create', 'enquiries.create', 'projects.create'] as $routeName) {
        $this->actingAs($this->admin)
            ->get(route($routeName))
            ->assertInertia(fn ($page) => $page
                ->has('products', 1)
                ->where('products.0.name', 'Active Cover'));
    }
});

test('editing an order keeps the inactive products it already uses', function () {
    Product::factory()->create(['name' => 'Active Cover']);
    $retired = Product::factory()->inactive()->create(['name' => 'Retired Cover']);

    $order = Order::factory()->create();
    $order->items()->create(['product_id' => $retired->id, 'description' => 'Retired Cover', 'quantity' => 1, 'unit_price' => 50, 'tax_percentage' => 0, 'tax_amount' => 0]);

    $this->actingAs($this->admin)
        ->get(route('sales-orders.edit', $order))
        ->assertInertia(fn ($page) => $page->has('products', 2));
});

test('the product api only lists active products', function () {
    Product::factory()->create(['name' => 'Active Cover']);
    Product::factory()->inactive()->create(['name' => 'Retired Cover']);

    $this->actingAs($this->admin)
        ->getJson('/api/products')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Active Cover');
});
