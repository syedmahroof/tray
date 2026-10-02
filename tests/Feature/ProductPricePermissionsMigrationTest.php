<?php

use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

test('the migration adds product price permissions to a database seeded before they existed', function () {
    $this->seed(RolePermissionSeeder::class);

    // Mimic a server seeded before branch pricing had permissions.
    Permission::where('name', 'like', 'products.price.%')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $migration = require database_path('migrations/2026_10_02_034741_add_product_price_permissions.php');
    $migration->up();

    expect(Permission::where('name', 'like', 'products.price.%')->count())->toBe(3);

    expect(Role::findByName('Super Admin')->hasPermissionTo('products.price.update'))->toBeTrue()
        ->and(Role::findByName('Admin')->hasPermissionTo('products.price.update'))->toBeTrue()
        ->and(Role::findByName('Manager')->hasPermissionTo('products.price.history'))->toBeTrue()
        ->and(Role::findByName('Sales Executive')->hasPermissionTo('products.price.view'))->toBeTrue()
        ->and(Role::findByName('Sales Executive')->hasPermissionTo('products.price.update'))->toBeFalse()
        ->and(Role::findByName('Telecaller')->hasPermissionTo('products.price.update'))->toBeFalse();

    // Running it again is harmless.
    $migration->up();

    expect(Permission::where('name', 'like', 'products.price.%')->count())->toBe(3);
});
