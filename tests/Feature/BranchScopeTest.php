<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->branchA = Branch::factory()->create();
    $this->branchB = Branch::factory()->create();
    $this->branchC = Branch::factory()->create();

    Product::factory()->create(['branch_id' => $this->branchA->id, 'name' => 'Product A']);
    Product::factory()->create(['branch_id' => $this->branchB->id, 'name' => 'Product B']);
    Product::factory()->create(['branch_id' => $this->branchC->id, 'name' => 'Product C']);
});

test('a user only sees records from the single branch they belong to', function () {
    $user = User::factory()->create(['branch_id' => $this->branchA->id]);
    $user->assignRole('Sales Executive');
    $user->branches()->sync([$this->branchA->id]);

    $this->actingAs($user);

    expect(Product::pluck('name')->all())->toEqualCanonicalizing(['Product A']);
});

test('a user sees records from every branch assigned to them', function () {
    $user = User::factory()->create(['branch_id' => $this->branchA->id]);
    $user->assignRole('Sales Executive');
    $user->branches()->sync([$this->branchA->id, $this->branchB->id]);

    $this->actingAs($user);

    expect(Product::pluck('name')->all())->toEqualCanonicalizing(['Product A', 'Product B']);
});

test('a legacy user without pivot rows falls back to their primary branch', function () {
    $user = User::factory()->create(['branch_id' => $this->branchC->id]);
    $user->assignRole('Sales Executive');

    $this->actingAs($user);

    expect(Product::pluck('name')->all())->toEqualCanonicalizing(['Product C']);
});

test('admins see records across every branch', function () {
    $admin = User::factory()->create(['branch_id' => $this->branchA->id]);
    $admin->assignRole('Admin');
    $admin->branches()->sync([$this->branchA->id]);

    $this->actingAs($admin);

    expect(Product::pluck('name')->all())
        ->toEqualCanonicalizing(['Product A', 'Product B', 'Product C']);
});
