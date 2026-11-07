<?php

use App\Actions\Products\SaveProductBranchPrice;
use App\Models\Branch;
use App\Models\Brand;
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

    $product = Product::factory()->create(['name' => 'Slotted Channel 1.2 MM', 'tax_percentage' => 18, 'brand_id' => Brand::factory()]);
    $manager->brands()->sync([$product->brand_id]);
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

    // Number fields carry no spinner arrows.
    expect($page->script('getComputedStyle(document.querySelector(\'[data-test="unit-price-0"]\')).appearance'))->toBe('textfield');

    // Scrolling over a focused number field leaves its value alone.
    $page->click('[data-test="unit-price-0"]');
    $page->script('document.querySelector(\'[data-test="unit-price-0"]\').dispatchEvent(new WheelEvent("wheel", { deltaY: 100, bubbles: true }))');
    expect($page->script('document.activeElement?.dataset?.test ?? null'))->not->toBe('unit-price-0');
    $page->assertValue('[data-test="unit-price-0"]', '100.00');

    // Switching the tier re-prices the line.
    $page->click('SR — Stockist Rate');
    $page->click('PR — Project Rate');
    $page->assertValue('[data-test="unit-price-0"]', '110.00');

    // Choosing a customer applies their own price type, re-pricing again.
    $page->click('text=Select a customer (optional)');
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

test('a line can be custom priced off MRP from the customer\'s discount, with an agent commission on top', function () {
    $branch = Branch::factory()->create(['name' => 'Kochi']);
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    Customer::factory()->create(['branch_id' => $branch->id, 'name' => 'Acme Customer', 'rate_tier' => 'PR']);

    $product = Product::factory()->create(['name' => 'Slotted Channel 1.2 MM', 'tax_percentage' => 18, 'brand_id' => Brand::factory()]);
    $manager->brands()->sync([$product->brand_id]);
    app(SaveProductBranchPrice::class)->handle($product, $branch, [
        'mrp' => 200, 'sr_rate' => 120, 'pr_rate' => 130, 'cr_rate' => 150,
    ]);

    $this->actingAs($manager);

    $page = visit('/quotations/create');
    $page->click('text=Select a customer (optional)');
    $page->click('Acme Customer');
    $page->click('Select product');
    $page->click('Slotted Channel 1.2 MM');
    $page->assertValue('[data-test="unit-price-0"]', '130.00');
    $page->assertSeeIn('[data-test="price-basis-0"]', '35% off MRP 200.00');

    // A custom line starts at the customer's PR discount: MRP 200 less 35%.
    $page->click('[data-test="line-tier-0"]');
    $page->click('[data-test="line-tier-option-custom"]');
    $page->assertValue('[data-test="price-percent-0"]', '35');
    $page->assertValue('[data-test="unit-price-0"]', '130');

    // Bargaining to 36% re-prices the line.
    $page->clear('[data-test="price-percent-0"]');
    $page->type('[data-test="price-percent-0"]', '36');
    $page->assertValue('[data-test="unit-price-0"]', '128');

    // The agent's commission is added to the printed price.
    $page->clear('[data-test="agent-commission"]');
    $page->type('[data-test="agent-commission"]', '100');
    $page->assertSeeIn('[data-test="printed-price-0"]', '228.00 with agent');

    $page->click('button[type="submit"]');
    $page->assertSee('Quotation created.');
    $page->assertSee('Agent commission');
    $page->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('quotations', ['rate_tier' => 'PR', 'agent_commission' => 100]);
    $this->assertDatabaseHas('quotation_items', [
        'product_id' => $product->id, 'rate_tier' => 'custom', 'price_basis' => 'mrp', 'list_price' => 200,
        'price_percent' => 36, 'base_price' => 128, 'unit_price' => 228,
    ]);
});

test('a cost-based product shows its markup on cost, and a custom line adds its own', function () {
    $branch = Branch::factory()->create(['name' => 'Kochi']);
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    Customer::factory()->create(['branch_id' => $branch->id, 'name' => 'Acme Customer', 'rate_tier' => 'PR']);

    $product = Product::factory()->create(['name' => 'Cable Tray 100 MM', 'tax_percentage' => 18, 'brand_id' => Brand::factory()]);
    $manager->brands()->sync([$product->brand_id]);
    app(SaveProductBranchPrice::class)->handle($product, $branch, [
        'cost' => 100, 'sr_rate' => 110, 'pr_rate' => 120, 'cr_rate' => 130,
    ]);

    $this->actingAs($manager);

    $page = visit('/quotations/create');
    $page->click('text=Select a customer (optional)');
    $page->click('Acme Customer');
    $page->click('Select product');
    $page->click('Cable Tray 100 MM');
    $page->assertValue('[data-test="unit-price-0"]', '120.00');
    $page->assertSeeIn('[data-test="price-basis-0"]', '+20% on cost 100.00');

    // Custom starts at the customer's PR markup, and adds what is typed.
    $page->click('[data-test="line-tier-0"]');
    $page->click('[data-test="line-tier-option-custom"]');
    $page->assertValue('[data-test="price-percent-0"]', '20');
    $page->clear('[data-test="price-percent-0"]');
    $page->type('[data-test="price-percent-0"]', '25');
    $page->assertValue('[data-test="unit-price-0"]', '125');

    $page->click('button[type="submit"]');
    $page->assertSee('Quotation created.');
    $page->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('quotation_items', [
        'product_id' => $product->id, 'rate_tier' => 'custom', 'price_basis' => 'cost',
        'list_price' => 100, 'price_percent' => 25, 'unit_price' => 125,
    ]);
});

test('changing a line\'s product again updates its description', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $brand = Brand::factory()->create();
    Product::factory()->create(['name' => 'Cable Tray 100 MM', 'brand_id' => $brand->id]);
    Product::factory()->create(['name' => 'Cable Tray 200 MM', 'brand_id' => $brand->id]);
    Product::factory()->create(['name' => 'Cable Tray 300 MM', 'brand_id' => $brand->id]);
    $manager->brands()->sync([$brand->id]);

    $this->actingAs($manager);

    $page = visit('/quotations/create');

    $page->click('Select product');
    $page->click('Cable Tray 100 MM');
    $page->assertValue('[data-test="description-0"]', 'Cable Tray 100 MM');

    $page->click('Cable Tray 100 MM');
    $page->click('Cable Tray 200 MM');
    $page->assertValue('[data-test="description-0"]', 'Cable Tray 200 MM');

    // Wording typed on the line is kept when the product changes.
    $page->fill('[data-test="description-0"]', 'Custom tray wording');
    $page->click('Cable Tray 200 MM');
    $page->click('Cable Tray 300 MM');
    $page->assertValue('[data-test="description-0"]', 'Custom tray wording');
    $page->assertNoJavaScriptErrors();
});
