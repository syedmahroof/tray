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
        'Super Admin' => [
            'locations.view', 'locations.create', 'locations.update', 'locations.delete',
            'routes.view', 'routes.create', 'routes.update', 'routes.delete',
        ],
        'Admin' => [
            'locations.view', 'locations.create', 'locations.update', 'locations.delete',
            'routes.view', 'routes.create', 'routes.update', 'routes.delete',
        ],
        'Manager' => [
            'locations.view', 'locations.create', 'locations.update', 'locations.delete',
            'routes.view', 'routes.create', 'routes.update', 'routes.delete',
        ],
        'Sales Executive' => ['locations.view', 'locations.create', 'routes.view'],
        'Telecaller' => ['locations.view', 'routes.view'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['locations', 'routes'] as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                Permission::findOrCreate("{$resource}.{$action}");
            }
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

        Permission::where('name', 'like', 'locations.%')
            ->orWhere('name', 'like', 'routes.%')
            ->delete();
    }
};
