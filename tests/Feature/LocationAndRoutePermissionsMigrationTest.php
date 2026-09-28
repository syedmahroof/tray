<?php

use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

test('the migration adds location and route permissions to a database seeded before they existed', function () {
    $this->seed(RolePermissionSeeder::class);

    // Mimic a server seeded before locations and routes had permissions.
    Permission::where('name', 'like', 'locations.%')->orWhere('name', 'like', 'routes.%')->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $migration = require database_path('migrations/2026_09_28_172025_add_location_and_route_permissions.php');
    $migration->up();

    expect(Permission::where('name', 'like', 'locations.%')->count())->toBe(4)
        ->and(Permission::where('name', 'like', 'routes.%')->count())->toBe(4);

    expect(Role::findByName('Admin')->hasPermissionTo('locations.create'))->toBeTrue()
        ->and(Role::findByName('Admin')->hasPermissionTo('routes.delete'))->toBeTrue()
        ->and(Role::findByName('Manager')->hasPermissionTo('routes.create'))->toBeTrue()
        ->and(Role::findByName('Sales Executive')->hasPermissionTo('locations.create'))->toBeTrue()
        ->and(Role::findByName('Sales Executive')->hasPermissionTo('routes.create'))->toBeFalse()
        ->and(Role::findByName('Telecaller')->hasPermissionTo('locations.create'))->toBeFalse();

    // Running it again is harmless.
    $migration->up();

    expect(Permission::where('name', 'like', 'locations.%')->count())->toBe(4);
});
