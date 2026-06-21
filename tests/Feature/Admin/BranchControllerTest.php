<?php

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('guests cannot view branches', function () {
    $this->get(route('branches.index'))->assertRedirect(route('login'));
});

test('users without permission cannot view branches', function () {
    $user = User::factory()->create();
    $user->assignRole('Telecaller');

    $this->actingAs($user)->get(route('branches.index'))->assertForbidden();
});

test('admins can view the branches index', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Branch::factory()->create(['name' => 'Head Office']);

    $this->actingAs($admin)
        ->get(route('branches.index'))
        ->assertSuccessful();
});

test('admins can create a branch', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->post(route('branches.store'), [
            'name' => 'Downtown',
            'code' => 'DT-01',
            'city' => 'Metropolis',
            'is_active' => true,
        ])
        ->assertRedirect(route('branches.index'));

    $this->assertDatabaseHas('branches', [
        'name' => 'Downtown',
        'code' => 'DT-01',
        'is_active' => true,
    ]);
});

test('branch codes must be unique', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Branch::factory()->create(['code' => 'DT-01']);

    $this->actingAs($admin)
        ->post(route('branches.store'), [
            'name' => 'Another Branch',
            'code' => 'DT-01',
        ])
        ->assertInvalid(['code']);
});

test('admins can update a branch', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $branch = Branch::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->patch(route('branches.update', $branch), [
            'name' => 'Renamed Branch',
            'code' => $branch->code,
            'is_active' => false,
        ])
        ->assertRedirect(route('branches.index'));

    expect($branch->refresh())
        ->name->toBe('Renamed Branch')
        ->is_active->toBeFalse();
});

test('admins can delete a branch with no users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $branch = Branch::factory()->create();

    $this->actingAs($admin)
        ->delete(route('branches.destroy', $branch))
        ->assertRedirect(route('branches.index'));

    $this->assertModelMissing($branch);
});

test('a branch cannot be deleted while it still has users', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $branch = Branch::factory()->create();
    User::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($admin)
        ->delete(route('branches.destroy', $branch))
        ->assertRedirect();

    $this->assertModelExists($branch);
});

test('the branch index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Branch::factory()->create(['name' => 'Northgate Branch']);
    Branch::factory()->create(['name' => 'Southside Branch']);

    $this->actingAs($admin)
        ->get(route('branches.index', ['search' => 'Northgate']))
        ->assertInertia(fn ($page) => $page
            ->has('branches.data', 1)
            ->where('branches.data.0.name', 'Northgate Branch')
            ->where('filters.search', 'Northgate'));
});
