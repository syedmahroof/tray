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

    Product::factory()->create(['branch_id' => $this->branch->id, 'brand_id' => $this->brandA->id, 'name' => 'A Product']);
    Product::factory()->create(['branch_id' => $this->branch->id, 'brand_id' => $this->brandB->id, 'name' => 'B Product']);
    Product::factory()->create(['branch_id' => $this->branch->id, 'brand_id' => null, 'name' => 'Unbranded Product']);
});

test('a user restricted to a brand only sees that brand and unbranded records', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id]);
    $user->assignRole('Sales Executive');
    $user->branches()->sync([$this->branch->id]);
    $user->brands()->sync([$this->brandA->id]);

    $this->actingAs($user);

    expect(Product::pluck('name')->all())
        ->toEqualCanonicalizing(['A Product', 'Unbranded Product']);
});

test('a user with no assigned brands sees every product', function () {
    $user = User::factory()->create(['branch_id' => $this->branch->id]);
    $user->assignRole('Sales Executive');
    $user->branches()->sync([$this->branch->id]);

    $this->actingAs($user);

    expect(Product::pluck('name')->all())
        ->toEqualCanonicalizing(['A Product', 'B Product', 'Unbranded Product']);
});

test('admins are never restricted by brand', function () {
    $admin = User::factory()->create(['branch_id' => $this->branch->id]);
    $admin->assignRole('Admin');
    $admin->brands()->sync([$this->brandA->id]);

    $this->actingAs($admin);

    expect(Product::pluck('name')->all())
        ->toEqualCanonicalizing(['A Product', 'B Product', 'Unbranded Product']);
});
