<?php

use App\Models\Branch;
use App\Models\Brand;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    // Share one branch so the branch scope never hides products while we
    // exercise the brand scope in isolation.
    $this->branch = Branch::factory()->create();
    $this->brandA = Brand::factory()->create(['name' => 'Brand A']);
    $this->brandB = Brand::factory()->create(['name' => 'Brand B']);

    Product::factory()->create(['brand_id' => $this->brandA->id, 'name' => 'A Product']);
    Product::factory()->create(['brand_id' => $this->brandB->id, 'name' => 'B Product']);
    Product::factory()->create(['brand_id' => null, 'name' => 'Unbranded Product']);
});

test('a member only sees products of their assigned brands', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id]);
    $user->assignRole('Sales Executive');
    $user->branches()->sync([$this->branch->id]);
    $user->brands()->sync([$this->brandA->id]);

    $this->actingAs($user);

    expect(Product::pluck('name')->all())->toBe(['A Product']);
});

test('a member also sees the products they created, whatever their brand', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id]);
    $user->assignRole('Sales Executive');
    $user->branches()->sync([$this->branch->id]);
    $user->brands()->sync([$this->brandA->id]);

    Product::factory()->create(['brand_id' => $this->brandB->id, 'name' => 'Own B Product', 'created_by' => $user->id]);
    Product::factory()->create(['brand_id' => null, 'name' => 'Own Unbranded Product', 'created_by' => $user->id]);

    $this->actingAs($user);

    expect(Product::pluck('name')->all())
        ->toEqualCanonicalizing(['A Product', 'Own B Product', 'Own Unbranded Product']);
});

test('a member with no assigned brands only sees the products they created', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id]);
    $user->assignRole('Sales Executive');
    $user->branches()->sync([$this->branch->id]);

    Product::factory()->create(['brand_id' => $this->brandA->id, 'name' => 'Own Product', 'created_by' => $user->id]);

    $this->actingAs($user);

    expect(Product::pluck('name')->all())->toBe(['Own Product']);
});

test('admins are never restricted by brand', function () {
    $admin = User::factory()->create(['branch_id' => $this->branch->id]);
    $admin->assignRole('Admin');
    $admin->brands()->sync([$this->brandA->id]);

    $this->actingAs($admin);

    expect(Product::pluck('name')->all())
        ->toEqualCanonicalizing(['A Product', 'B Product', 'Unbranded Product']);
});
