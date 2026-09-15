<?php

use App\Actions\Products\SaveProductBranchPrice;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('quotation lines are priced from the chosen rate tier and re-priced when it changes', function () {
    $branch = Branch::factory()->create(['name' => 'Kochi']);
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    Customer::factory()->create(['branch_id' => $branch->id, 'name' => 'Acme Customer', 'rate_tier' => 'CR']);

    $product = Product::factory()->create(['name' => 'Slotted Channel 1.2 MM', 'tax_percentage' => 18]);
    app(SaveProductBranchPrice::class)->handle($product, $branch, [
        'sr_rate' => 100, 'pr_rate' => 110, 'cr_rate' => 120,
    ]);

    $this->actingAs($manager);

    $page = visit('/quotations/create');
    $page->assertNoJavaScriptErrors()->assertSee('Price Type');

    // The default tier prices the line from the user's branch.
    $page->click('Select product');
    $page->click('Slotted Channel 1.2 MM');
    $page->assertValue('[data-test="unit-price-0"]', '100.00');

    // Switching the tier re-prices the line.
    $page->click('SR — Stockist Rate');
    $page->click('PR — Project Rate');
    $page->assertValue('[data-test="unit-price-0"]', '110.00');

    // Choosing a customer applies their own price type, re-pricing again.
    $page->click('Select a customer');
    $page->click('Acme Customer');
    $page->assertSee('CR — Counter Rate');
    $page->assertValue('[data-test="unit-price-0"]', '120.00');

    // The page heading reads "Create Quotation" too, so target the button.
    $page->click('button[type="submit"]');
    $page->assertSee('Quotation created.');
    $page->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('quotations', ['rate_tier' => 'CR']);
    $this->assertDatabaseHas('quotation_items', ['product_id' => $product->id, 'unit_price' => 120]);
});
