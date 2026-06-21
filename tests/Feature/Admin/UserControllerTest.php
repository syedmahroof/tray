<?php

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('guests cannot view users', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

test('users without permission cannot view users', function () {
    $user = User::factory()->create();
    $user->assignRole('Telecaller');

    $this->actingAs($user)->get(route('users.index'))->assertForbidden();
});

test('admins can view the users index', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)->get(route('users.index'))->assertSuccessful();
});

test('admins can create a user with a branch and role', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $branch = Branch::factory()->create();

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'branch_id' => $branch->id,
            'role' => 'Sales Executive',
        ])
        ->assertRedirect(route('users.index'));

    $user = User::where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->branch_id)->toBe($branch->id);
    expect($user->hasRole('Sales Executive'))->toBeTrue();
});

test('user emails must be unique', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    User::factory()->create(['email' => 'jane@example.com']);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'role' => 'Sales Executive',
        ])
        ->assertInvalid(['email']);
});

test('admins can update a user without changing the password', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $user = User::factory()->create();
    $user->assignRole('Telecaller');
    $originalPassword = $user->password;

    $this->actingAs($admin)
        ->patch(route('users.update', $user), [
            'name' => 'Renamed User',
            'email' => $user->email,
            'role' => 'Sales Executive',
        ])
        ->assertRedirect(route('users.index'));

    $user->refresh();

    expect($user->name)->toBe('Renamed User');
    expect($user->password)->toBe($originalPassword);
    expect($user->hasRole('Sales Executive'))->toBeTrue();
    expect($user->hasRole('Telecaller'))->toBeFalse();
});

test('admins can change a users password', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $user = User::factory()->create();
    $user->assignRole('Telecaller');

    $this->actingAs($admin)
        ->patch(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
            'role' => 'Telecaller',
        ])
        ->assertRedirect(route('users.index'));

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('admins cannot delete their own account from the users screen', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->delete(route('users.destroy', $admin))
        ->assertRedirect();

    $this->assertModelExists($admin);
});

test('admins can delete another user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('users.destroy', $user))
        ->assertRedirect(route('users.index'));

    $this->assertModelMissing($user);
});

test('the user index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    User::factory()->create(['name' => 'Searchable Sue']);
    User::factory()->create(['name' => 'Hidden Hugo']);

    $this->actingAs($admin)
        ->get(route('users.index', ['search' => 'Searchable Sue']))
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Searchable Sue')
            ->where('filters.search', 'Searchable Sue'));
});
