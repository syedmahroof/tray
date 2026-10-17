<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private const GRANTS = [
        'Super Admin' => ['sales-orders.view', 'sales-orders.create', 'sales-orders.update', 'sales-orders.delete', 'purchase-orders.view', 'purchase-orders.create', 'purchase-orders.update', 'purchase-orders.delete'],
        'Admin' => ['sales-orders.view', 'sales-orders.create', 'sales-orders.update', 'sales-orders.delete', 'purchase-orders.view', 'purchase-orders.create', 'purchase-orders.update', 'purchase-orders.delete'],
        'Manager' => ['sales-orders.view', 'sales-orders.create', 'sales-orders.update', 'sales-orders.delete', 'purchase-orders.view', 'purchase-orders.create', 'purchase-orders.update', 'purchase-orders.delete'],
        'Sales Executive' => ['sales-orders.view', 'sales-orders.create', 'sales-orders.update', 'sales-orders.delete'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['sales-orders', 'purchase-orders'] as $resource) {
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

        Permission::where('name', 'like', 'sales-orders.%')
            ->orWhere('name', 'like', 'purchase-orders.%')
            ->delete();
    }
};
