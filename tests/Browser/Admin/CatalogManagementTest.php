<?php

use App\Actions\Products\SaveProductBranchPrice;
use App\Models\Branch;
use App\Models\Builder;
use App\Models\Country;
use App\Models\District;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\State;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('an admin can create a builder with a cascading location select', function () {
    $branch = Branch::factory()->create(['name' => 'Head Office']);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $country = Country::factory()->create(['name' => 'India']);
    $state = State::factory()->create(['country_id' => $country->id, 'name' => 'Karnataka']);
    District::factory()->create(['state_id' => $state->id, 'name' => 'Bengaluru Urban']);
    $this->actingAs($admin);

    $page = visit('/builders');
    $page->assertSee('Builders')->assertNoJavaScriptErrors();

    $page->click('New builder')->assertSee('New builder');
    $page->fill('name', 'Acme Developers');

    $page->click('Select a country');
    $page->click('India');

    $page->click('Select a state');
    $page->click('Karnataka');

    $page->click('Select a district');
    $page->click('Bengaluru Urban');

    $page->select('branch_id', 'Head Office');

    $page->click('Create builder');
    $page->assertSee('Builder created.');
    $page->assertSee('Acme Developers');
    $page->assertSee('Bengaluru Urban');

    $builder = Builder::where('name', 'Acme Developers')->first();
    expect($builder->district_id)->not->toBeNull();
    expect($builder->branch_id)->toBe($branch->id);
});

test('an admin can create a project through the UI', function () {
    $branch = Branch::factory()->create(['name' => 'Head Office']);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Builder::factory()->create(['branch_id' => $branch->id, 'name' => 'Acme Developers']);
    ProjectCategory::factory()->create(['name' => 'Residential']);
    $this->actingAs($admin);

    $page = visit('/projects');
    $page->assertSee('Projects')->assertNoJavaScriptErrors();

    $page->click('New project')->assertSee('New project');
    $page->fill('name', 'Skyline Residency');

    $page->click('Select a builder');
    $page->click('Acme Developers');

    $page->click('Select a category');
    $page->click('Residential');

    $page->select('branch_id', 'Head Office');

    $page->click('Create project');
    $page->wait(1.5);
    $page->assertNoJavaScriptErrors();
    $page->assertSee('Skyline Residency');

    $project = Project::where('name', 'Skyline Residency')->first();
    expect($project)->not->toBeNull();
    expect($project->branch_id)->toBe($branch->id);
});

test('an admin can create a product through the UI', function () {
    $branch = Branch::factory()->create(['name' => 'Head Office']);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    ProductCategory::factory()->create(['name' => 'Apartment']);
    $this->actingAs($admin);

    $page = visit('/products');
    $page->assertSee('Products')->assertNoJavaScriptErrors();

    $page->click('New product')->assertSee('New product');
    $page->fill('name', 'Unit 101');

    $page->click('Select a category');
    $page->click('Apartment');

    // Price the product for a branch through the repeater. A discount fills
    // the rate from MRP, and a typed rate works the discount back out.
    $page->click('Add branch');
    $page->fill('#mrp-0', '1000');
    $page->fill('#sr-discount-0', '25');
    $page->assertValue('#sr-rate-0', '750');
    $page->fill('#sr-rate-0', '800');
    $page->assertValue('#sr-discount-0', '20');

    $page->click('Create product');
    $page->wait(1.5);
    $page->assertNoJavaScriptErrors();
    $page->assertSee('Unit 101');

    $this->assertDatabaseHas('products', ['name' => 'Unit 101']);

    $this->assertDatabaseHas('product_branch_prices', [
        'branch_id' => $branch->id,
        'mrp' => 1000,
        'sr_discount' => 20,
        'sr_rate' => 800,
        'sr_rate_with_tax' => 944,
    ]);
});

test('the branch price editor marks up cost plus freight and copies a branch to every branch', function () {
    $kochi = Branch::factory()->create(['name' => 'Kochi', 'code' => 'KOC']);
    $calicut = Branch::factory()->create(['name' => 'Calicut', 'code' => 'CAL']);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $category = ProductCategory::factory()->create(['name' => 'Cable Tray']);
    $product = Product::factory()->create([
        'name' => 'Double Bend Raceway 100x50',
        'product_category_id' => $category->id,
        'tax_percentage' => 18,
    ]);

    // Priced the way the cable tray sheet is: 226 cost plus 15 freight,
    // marked up 10 / 15 / 20 per tier, with no MRP.
    app(SaveProductBranchPrice::class)->handle($product, $kochi, [
        'cost' => 226,
        'sr_discount' => 10, 'sr_rate' => 265.10,
        'pr_discount' => 15, 'pr_rate' => 277.15,
        'cr_discount' => 20, 'cr_rate' => 289.20,
    ]);

    $this->actingAs($admin);

    $page = visit("/products/{$product->id}/edit");
    $page->assertNoJavaScriptErrors()
        ->assertSee('Markup %')
        ->assertSee('cost + 15.00 freight');

    // Changing a % re-prices its tier on the same cost + freight basis.
    $page->fill('#sr-discount-0', '20');
    $page->assertValue('#sr-rate-0', '289.2');

    // Changing cost re-prices every tier, keeping the freight.
    $page->fill('#cost-0', '230');
    $page->assertValue('#sr-rate-0', '294');
    $page->assertValue('#pr-rate-0', '281.75');

    $page->click('Apply to all branches');
    $page->assertValue('#sr-rate-1', '294');

    $page->click('Save');
    $page->wait(1.5);
    $page->assertNoJavaScriptErrors();

    $this->assertDatabaseHas('product_branch_prices', [
        'product_id' => $product->id,
        'branch_id' => $calicut->id,
        'cost' => 230,
        'sr_discount' => 20,
        'sr_rate' => 294,
        'pr_rate' => 281.75,
    ]);
});

test('an admin can compare branch prices on the price list page', function () {
    $kochi = Branch::factory()->create(['name' => 'Kochi', 'code' => 'KOC']);
    $calicut = Branch::factory()->create(['name' => 'Calicut', 'code' => 'CAL']);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $category = ProductCategory::factory()->create(['name' => 'Pipe Supports']);
    $product = Product::factory()->create([
        'name' => 'Slotted Channel 1.2 MM',
        'product_category_id' => $category->id,
        'unit' => 'Mtr',
        'tax_percentage' => 18,
    ]);

    $save = app(SaveProductBranchPrice::class);
    $save->handle($product, $kochi, ['cost' => 58, 'sr_discount' => 12.5, 'sr_rate' => 79.75]);
    $save->handle($product, $calicut, ['cost' => 58, 'sr_rate' => 85]);

    $this->actingAs($admin);

    $page = visit('/products/price-list');

    $page->assertSee('Price List')
        ->assertNoJavaScriptErrors()
        ->assertSee('Pipe Supports')
        ->assertSee('Slotted Channel 1.2 MM')
        // Both branches and the derived tax-inclusive figures.
        ->assertSee('Kochi')
        ->assertSee('Calicut')
        ->assertSee('94.11')
        ->assertSee('100.30')
        ->assertSee('SR %')
        // Both export routes are reachable from the page.
        ->assertSee('Export by category')
        ->assertSee('Export all branches');

    // Admins can edit prices, so the rates and each tier's % sit in cells.
    $cell = fn (Branch $branch, string $key): string => "input[data-grid-row=\"0\"][data-grid-col=\"{$branch->id}-{$key}\"]";
    $page->assertValue($cell($kochi, 'sr_rate'), '79.75')
        ->assertValue($cell($calicut, 'sr_rate'), '85.00')
        ->assertValue($cell($kochi, 'sr_discount'), '12.5%');
});

test('price list figures can be edited in place, spreadsheet style', function () {
    $kochi = Branch::factory()->create(['name' => 'Kochi', 'code' => 'KOC']);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $category = ProductCategory::factory()->create(['name' => 'Pipe Supports']);
    $product = Product::factory()->create([
        'name' => 'Slotted Channel 1.2 MM',
        'product_category_id' => $category->id,
        'tax_percentage' => 18,
    ]);
    app(SaveProductBranchPrice::class)->handle($product, $kochi, ['cost' => 58, 'sr_rate' => 79.75]);

    $this->actingAs($admin);

    $page = visit('/products/price-list');
    $cell = fn (string $key): string => "input[data-grid-row=\"0\"][data-grid-col=\"{$kochi->id}-{$key}\"]";

    // A % typed without an MRP marks the cost up: 58 × 1.50 = 87.00.
    $page->click($cell('sr_discount'));
    $page->fill($cell('sr_discount'), '50');
    $page->keys($cell('sr_discount'), 'Enter');
    $page->wait(1);
    $page->assertValue($cell('sr_rate'), '87.00')
        ->assertSee('102.66')
        ->assertNoJavaScriptErrors();

    // Esc abandons an edit without saving it.
    $page->click($cell('cost'));
    $page->fill($cell('cost'), '999');
    $page->keys($cell('cost'), 'Escape');
    $page->wait(0.5);
    $page->assertValue($cell('cost'), '58.00');

    $this->assertDatabaseHas('product_branch_prices', [
        'product_id' => $product->id,
        'branch_id' => $kochi->id,
        'cost' => 58,
        'sr_discount' => 50,
        'sr_rate' => 87,
        'sr_rate_with_tax' => 102.66,
    ]);
});

test('each category in the list links to its own page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    ProductCategory::factory()->create(['name' => 'Pipe Supports']);

    $this->actingAs($admin);

    $page = visit('/product-categories');
    $page->assertNoJavaScriptErrors();
    $page->click('[aria-label="View Pipe Supports"]');
    $page->assertSee('Products filed under this category and their current rates')
        ->assertNoJavaScriptErrors();
});

test('the category page shows its products and their rates', function () {
    $branch = Branch::factory()->create(['name' => 'Head Office']);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $category = ProductCategory::factory()->create(['name' => 'Cable Tray']);
    $product = Product::factory()->create([
        'name' => 'Perforated Tray 50x50',
        'product_category_id' => $category->id,
    ]);

    app(SaveProductBranchPrice::class)->handle($product, $branch, ['sr_rate' => 138.6]);

    $this->actingAs($admin);

    $page = visit('/product-categories/'.$category->id);

    $page->assertSee('Cable Tray')
        ->assertNoJavaScriptErrors()
        ->assertSee('Perforated Tray 50x50')
        ->assertSee('138.60');
});
