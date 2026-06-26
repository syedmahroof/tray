<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * The CRM resources that get a permission per action.
     *
     * @var list<string>
     */
    private const RESOURCES = [
        'users', 'branches', 'roles',
        'countries', 'states', 'districts',
        'project-categories', 'product-categories', 'contact-types', 'brands',
        'builders', 'projects', 'products',
        'contacts', 'customers', 'enquiries',
        'notes', 'reminders', 'visit-reports',
        'quotations', 'reports',
    ];

    /**
     * Additional one-off permissions that do not follow the resource/action grid.
     *
     * @var list<string>
     */
    private const EXTRA_PERMISSIONS = [];

    /**
     * @var list<string>
     */
    private const ACTIONS = ['view', 'create', 'update', 'delete'];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::RESOURCES as $resource) {
            foreach (self::ACTIONS as $action) {
                Permission::findOrCreate("{$resource}.{$action}");
            }
        }

        foreach (self::EXTRA_PERMISSIONS as $permission) {
            Permission::findOrCreate($permission);
        }

        Role::findOrCreate('Super Admin')->syncPermissions(Permission::all());
        Role::findOrCreate('Admin')->syncPermissions(Permission::all());

        Role::findOrCreate('Manager')->syncPermissions([
            ...$this->permissionNames(['countries', 'states', 'districts'], ['view']),
            ...$this->permissionNames([
                'project-categories', 'product-categories', 'contact-types', 'brands',
                'builders', 'projects', 'products',
                'contacts', 'customers', 'enquiries', 'notes', 'reminders', 'visit-reports', 'quotations',
            ], self::ACTIONS),
            ...$this->permissionNames(['reports'], ['view']),
        ]);

        Role::findOrCreate('Sales Executive')->syncPermissions([
            ...$this->permissionNames([
                'countries', 'states', 'districts',
                'project-categories', 'product-categories', 'contact-types', 'brands',
                'builders', 'projects', 'products',
            ], ['view']),
            ...$this->permissionNames(['contacts', 'customers', 'enquiries', 'notes', 'reminders', 'visit-reports', 'quotations'], self::ACTIONS),
        ]);

        Role::findOrCreate('Telecaller')->syncPermissions([
            ...$this->permissionNames([
                'countries', 'states', 'districts',
                'project-categories', 'product-categories', 'contact-types', 'brands',
                'builders', 'projects', 'products', 'contacts', 'customers',
            ], ['view']),
            ...$this->permissionNames(['enquiries', 'notes', 'reminders', 'visit-reports'], ['view', 'create', 'update']),
        ]);
    }

    /**
     * Build "{resource}.{action}" permission names for the given resources/actions.
     *
     * @param  list<string>  $resources
     * @param  list<string>  $actions
     * @return list<string>
     */
    private function permissionNames(array $resources, array $actions): array
    {
        $names = [];

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                $names[] = "{$resource}.{$action}";
            }
        }

        return $names;
    }
}
