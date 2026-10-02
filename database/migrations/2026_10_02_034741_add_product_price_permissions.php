<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * The permissions each role is granted, mirroring RolePermissionSeeder.
     *
     * @var array<string, list<string>>
     */
    private const GRANTS = [
        'Super Admin' => ['products.price.view', 'products.price.update', 'products.price.history'],
        'Admin' => ['products.price.view', 'products.price.update', 'products.price.history'],
        'Manager' => ['products.price.view', 'products.price.update', 'products.price.history'],
        'Sales Executive' => ['products.price.view'],
        'Telecaller' => ['products.price.view'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['view', 'update', 'history'] as $action) {
            Permission::findOrCreate("products.price.{$action}");
        }

        foreach (self::GRANTS as $roleName => $permissions) {
            Role::where('name', $roleName)->first()?->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', 'like', 'products.price.%')->delete();
    }
};
