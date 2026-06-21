<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('guests cannot view roles', function () {
    $this->get(route('roles.index'))->assertRedirect(route('login'));
});

test('users without permission cannot view roles', function () {
    $user = User::factory()->create();
    $user->assignRole('Telecaller');

    $this->actingAs($user)->get(route('roles.index'))->assertForbidden();
});

test('admins can view the roles index', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)->get(route('roles.index'))->assertSuccessful();
});

test('admins can create a role with permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->post(route('roles.store'), [
            'name' => 'Coordinator',
            'permissions' => ['contacts.view', 'contacts.create'],
        ])
        ->assertRedirect(route('roles.index'));

    $role = Role::where('name', 'Coordinator')->first();

    expect($role)->not->toBeNull();
    expect($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['contacts.view', 'contacts.create']);
});

test('admins can update a role and resync its permissions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $role = Role::create(['name' => 'Coordinator']);
    $role->syncPermissions(['contacts.view', 'contacts.create']);

    $this->actingAs($admin)
        ->patch(route('roles.update', $role), [
            'name' => 'Coordinator',
            'permissions' => ['contacts.view'],
        ])
        ->assertRedirect(route('roles.index'));

    expect($role->refresh()->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['contacts.view']);
});

test('a role cannot be deleted while it still has users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $role = Role::create(['name' => 'Coordinator']);
    User::factory()->create()->assignRole($role);

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect();

    $this->assertModelExists($role);
});

test('admins can delete a role with no users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $role = Role::create(['name' => 'Coordinator']);

    $this->actingAs($admin)
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'));

    $this->assertModelMissing($role);
});

test('the role index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Role::findOrCreate('Auditor');

    $this->actingAs($admin)
        ->get(route('roles.index', ['search' => 'Auditor']))
        ->assertInertia(fn ($page) => $page
            ->has('roles.data', 1)
            ->where('roles.data.0.name', 'Auditor')
            ->where('filters.search', 'Auditor'));
});
