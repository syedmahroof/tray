<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBranchPrice;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->branchA = Branch::factory()->create();
    $this->branchB = Branch::factory()->create();
    $this->branchC = Branch::factory()->create();

    // Products themselves are global; the branch scope now guards their prices.
    ProductBranchPrice::factory()->create([
        'branch_id' => $this->branchA->id,
        'product_id' => Product::factory()->create(['name' => 'Product A']),
    ]);
    ProductBranchPrice::factory()->create([
        'branch_id' => $this->branchB->id,
        'product_id' => Product::factory()->create(['name' => 'Product B']),
    ]);
    ProductBranchPrice::factory()->create([
        'branch_id' => $this->branchC->id,
        'product_id' => Product::factory()->create(['name' => 'Product C']),
    ]);
});

/**
 * Resolve the product names reachable through the scoped price rows.
 *
 * @return list<string>
 */
function visibleProductNames(): array
{
    return ProductBranchPrice::with('product')
        ->get()
        ->map(fn (ProductBranchPrice $price): string => $price->product->name)
        ->all();
}

test('a user only sees records from the single branch they belong to', function () {
    $user = User::factory()->create(['branch_id' => $this->branchA->id]);
    $user->assignRole('Sales Executive');
    $user->branches()->sync([$this->branchA->id]);

    $this->actingAs($user);

    expect(visibleProductNames())->toEqualCanonicalizing(['Product A']);
});

test('a user sees records from every branch assigned to them', function () {
    $user = User::factory()->create(['branch_id' => $this->branchA->id]);
    $user->assignRole('Sales Executive');
    $user->branches()->sync([$this->branchA->id, $this->branchB->id]);

    $this->actingAs($user);

    expect(visibleProductNames())->toEqualCanonicalizing(['Product A', 'Product B']);
});

test('a legacy user without pivot rows falls back to their primary branch', function () {
    $user = User::factory()->create(['branch_id' => $this->branchC->id]);
    $user->assignRole('Sales Executive');

    $this->actingAs($user);

    expect(visibleProductNames())->toEqualCanonicalizing(['Product C']);
});

test('admins see records across every branch', function () {
    $admin = User::factory()->create(['branch_id' => $this->branchA->id]);
    $admin->assignRole('Admin');
    $admin->branches()->sync([$this->branchA->id]);

    $this->actingAs($admin);

    expect(visibleProductNames())
        ->toEqualCanonicalizing(['Product A', 'Product B', 'Product C']);
});
