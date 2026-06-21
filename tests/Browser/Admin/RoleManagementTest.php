<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('an admin can create and edit a role through the UI', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $page = visit('/roles');

    $page->assertSee('Roles')
        ->assertNoJavaScriptErrors()
        ->click('New role')
        ->assertSee('New role')
        ->fill('name', 'Coordinator')
        ->check('@permission-contacts-view')
        ->check('@permission-contacts-create')
        ->click('Create role')
        ->assertSee('Role created.')
        ->assertSee('Coordinator');

    $role = Role::where('name', 'Coordinator')->first();
    expect($role->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['contacts.view', 'contacts.create']);

    $page->click("@edit-role-{$role->id}")
        ->assertSee('Edit Coordinator')
        ->assertChecked('@permission-contacts-view')
        ->uncheck('@permission-contacts-create')
        ->click('Save')
        ->assertSee('Role updated.');

    expect($role->refresh()->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(['contacts.view']);
});
