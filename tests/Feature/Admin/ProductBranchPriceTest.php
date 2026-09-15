<?php

use App\Actions\Products\SaveProductBranchPrice;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBranchPrice;
use App\Models\ProductCategory;
use App\Models\ProductPriceHistory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->action = app(SaveProductBranchPrice::class);
});

test('a product can hold a different price in each branch', function () {
    $product = Product::factory()->create(['tax_percentage' => 18]);
    $kochi = Branch::factory()->create();
    $calicut = Branch::factory()->create();

    $this->action->handle($product, $kochi, ['cost' => 40, 'sr_rate' => 62.40]);
    $this->action->handle($product, $calicut, ['cost' => 40, 'sr_rate' => 65.00]);

    expect($product->priceFor($kochi->id)->rate('SR'))->toBe('62.40');
    expect($product->priceFor($calicut->id)->rate('SR'))->toBe('65.00');
});

test('saving the same product and branch twice updates the existing row', function () {
    $product = Product::factory()->create();
    $branch = Branch::factory()->create();

    $this->action->handle($product, $branch, ['sr_rate' => 62.40]);
    $this->action->handle($product, $branch, ['sr_rate' => 70.00]);

    expect(ProductBranchPrice::withoutGlobalScopes()->where('product_id', $product->id)->count())->toBe(1);
    expect($product->fresh()->priceFor($branch->id)->rate('SR'))->toBe('70.00');
});

test('tax inclusive rates are derived from the product tax percentage', function () {
    $product = Product::factory()->create(['tax_percentage' => 18]);
    $branch = Branch::factory()->create();

    $price = $this->action->handle($product, $branch, [
        'sr_rate' => 62.40,
        'pr_rate' => 79.75,
        'cr_rate' => 314,
    ]);

    expect($price->rateWithTax('SR'))->toBe('73.63');
    expect($price->rateWithTax('PR'))->toBe('94.11');
    expect($price->rateWithTax('CR'))->toBe('370.52');
});

test('an explicitly supplied tax inclusive rate is not overwritten', function () {
    $product = Product::factory()->create(['tax_percentage' => 18]);
    $branch = Branch::factory()->create();

    $price = $this->action->handle($product, $branch, [
        'sr_rate' => 62.40,
        'sr_rate_with_tax' => 73.632,
    ]);

    expect($price->rateWithTax('SR'))->toBe('73.63');
});

test('creating the first price row writes no history unless a reason is given', function () {
    $product = Product::factory()->create();
    $branch = Branch::factory()->create();

    $this->action->handle($product, $branch, ['sr_rate' => 62.40]);

    expect(ProductPriceHistory::count())->toBe(0);

    $this->action->handle(Product::factory()->create(), $branch, ['sr_rate' => 10], null, 'Opening price');

    expect(ProductPriceHistory::where('reason', 'Opening price')->count())->toBeGreaterThan(0);
});

test('rate tiers reject an unknown key', function () {
    $price = ProductBranchPrice::factory()->make();

    expect(fn () => $price->rate('XX'))->toThrow(InvalidArgumentException::class);
});

test('a user only sees price rows for the branches assigned to them', function () {
    $mine = Branch::factory()->create();
    $theirs = Branch::factory()->create();

    ProductBranchPrice::factory()->create(['branch_id' => $mine->id]);
    ProductBranchPrice::factory()->create(['branch_id' => $theirs->id]);

    $user = User::factory()->create(['branch_id' => $mine->id]);
    $user->assignRole('Sales Executive');
    $user->branches()->sync([$mine->id]);

    $this->actingAs($user);

    expect(ProductBranchPrice::pluck('branch_id')->all())->toBe([$mine->id]);
});

test('a product form can price several branches at once', function () {
    $kochi = Branch::factory()->create();
    $calicut = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $kochi->id]);
    $manager->assignRole('Manager');
    $manager->branches()->sync([$kochi->id, $calicut->id]);
    $category = ProductCategory::factory()->create();

    $this->actingAs($manager)
        ->post(route('products.store'), [
            'product_category_id' => $category->id,
            'name' => 'Slotted Channel',
            'unit' => 'Mtr',
            'tax_percentage' => 18,
            'prices' => [
                ['branch_id' => $kochi->id, 'cost' => 58, 'sr_rate' => 79.75, 'sr_discount' => 30],
                ['branch_id' => $calicut->id, 'cost' => 58, 'sr_rate' => 85],
            ],
        ])
        ->assertRedirect(route('products.index'));

    $product = Product::where('name', 'Slotted Channel')->sole();

    expect($product->priceFor($kochi->id)->rate('SR'))->toBe('79.75');
    expect($product->priceFor($kochi->id)->rateWithTax('SR'))->toBe('94.11');
    expect($product->priceFor($kochi->id)->discount('SR'))->toBe('30.00');
    expect($product->priceFor($calicut->id)->rate('SR'))->toBe('85.00');
});

test('the same branch cannot be priced twice in one submission', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $manager->branches()->sync([$branch->id]);
    $category = ProductCategory::factory()->create();

    $this->actingAs($manager)
        ->post(route('products.store'), [
            'product_category_id' => $category->id,
            'name' => 'Duplicated branch',
            'prices' => [
                ['branch_id' => $branch->id, 'sr_rate' => 10],
                ['branch_id' => $branch->id, 'sr_rate' => 20],
            ],
        ])
        ->assertSessionHasErrors('prices.1.branch_id');
});

test('a user cannot price a branch they have no access to', function () {
    $mine = Branch::factory()->create();
    $theirs = Branch::factory()->create();

    $user = User::factory()->create(['branch_id' => $mine->id]);
    $user->assignRole('Manager');
    $user->branches()->sync([$mine->id]);

    $category = ProductCategory::factory()->create();

    $this->actingAs($user)
        ->post(route('products.store'), [
            'product_category_id' => $category->id,
            'name' => 'Out of reach',
            'prices' => [['branch_id' => $theirs->id, 'sr_rate' => 10]],
        ])
        ->assertSessionHasErrors('prices.0.branch_id');
});

test('editing a product logs the price change against the editor', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $manager->branches()->sync([$branch->id]);
    $product = Product::factory()->create(['tax_percentage' => 18]);

    app(SaveProductBranchPrice::class)->handle($product, $branch, ['sr_rate' => 100]);

    $this->actingAs($manager)
        ->patch(route('products.update', $product), [
            'product_category_id' => $product->product_category_id,
            'name' => $product->name,
            'tax_percentage' => 18,
            'price_reason' => 'April 2026 revision',
            'prices' => [['branch_id' => $branch->id, 'sr_rate' => 120]],
        ])
        ->assertRedirect(route('products.index'));

    $entry = ProductPriceHistory::where('field', 'sr_rate')->sole();

    expect((float) $entry->old_value)->toBe(100.0);
    expect((float) $entry->new_value)->toBe(120.0);
    expect($entry->user_id)->toBe($manager->id);
    expect($entry->reason)->toBe('April 2026 revision');
});

test('a branch price can be removed explicitly without touching the others', function () {
    $keep = Branch::factory()->create();
    $drop = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $keep->id]);
    $manager->assignRole('Manager');
    $manager->branches()->sync([$keep->id, $drop->id]);
    $product = Product::factory()->create();

    $action = app(SaveProductBranchPrice::class);
    $action->handle($product, $keep, ['sr_rate' => 10]);
    $action->handle($product, $drop, ['sr_rate' => 20]);

    $this->actingAs($manager)
        ->patch(route('products.update', $product), [
            'product_category_id' => $product->product_category_id,
            'name' => $product->name,
            'prices' => [['branch_id' => $drop->id, 'remove' => '1']],
        ])
        ->assertRedirect(route('products.index'));

    expect($product->fresh()->priceFor($drop->id))->toBeNull();
    expect($product->fresh()->priceFor($keep->id)->rate('SR'))->toBe('10.00');
});

test('a user without the price permission cannot change prices through the form', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->givePermissionTo(['products.view', 'products.update']);
    $user->branches()->sync([$branch->id]);
    $product = Product::factory()->create();

    app(SaveProductBranchPrice::class)->handle($product, $branch, ['sr_rate' => 10]);

    $this->actingAs($user)
        ->patch(route('products.update', $product), [
            'product_category_id' => $product->product_category_id,
            'name' => 'Renamed',
            'prices' => [['branch_id' => $branch->id, 'sr_rate' => 999]],
        ])
        ->assertRedirect(route('products.index'));

    expect($product->fresh()->name)->toBe('Renamed');
    expect($product->fresh()->priceFor($branch->id)->rate('SR'))->toBe('10.00');
});
