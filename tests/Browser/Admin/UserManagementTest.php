<?php

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('an admin can create and edit a user through the UI', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $branch = Branch::factory()->create(['name' => 'Head Office']);
    $this->actingAs($admin);

    $page = visit('/users');

    $page->assertSee('Users')
        ->assertNoJavaScriptErrors()
        ->click('New user')
        ->assertSee('New user')
        ->fill('name', 'Jane Doe')
        ->fill('email', 'jane@example.com')
        ->fill('password', 'password')
        ->fill('password_confirmation', 'password')
        ->check("@branch-{$branch->id}")
        ->select('role', 'Sales Executive')
        ->click('Create user')
        ->assertSee('User created.')
        ->assertSee('Jane Doe')
        ->assertSee('Sales Executive');

    $user = User::where('email', 'jane@example.com')->first();
    expect($user->hasRole('Sales Executive'))->toBeTrue();
    expect($user->branch_id)->toBe($branch->id);

    $page->click("@edit-user-{$user->id}")
        ->assertSee('Edit Jane Doe')
        ->select('role', 'Telecaller')
        ->click('Save')
        ->assertSee('User updated.');

    expect($user->refresh()->hasRole('Telecaller'))->toBeTrue();
});
