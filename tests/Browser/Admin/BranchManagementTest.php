<?php

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('an admin can create, edit, and delete a branch through the UI', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $page = visit('/branches');

    $page->assertSee('Branches')
        ->assertNoJavaScriptErrors()
        ->assertSee('No branches yet.')
        ->click('New branch')
        ->assertSee('New branch')
        ->fill('name', 'Downtown Office')
        ->fill('code', 'DT-01')
        ->fill('city', 'Metropolis')
        ->click('Create branch')
        ->assertSee('Downtown Office')
        ->assertSee('Branch created.');

    $this->assertDatabaseHas('branches', ['code' => 'DT-01']);

    $branch = Branch::where('code', 'DT-01')->first();

    $page->click("@edit-branch-{$branch->id}")
        ->assertSee('Edit Downtown Office')
        ->fill('name', 'Uptown Office')
        ->click('Save')
        ->assertSee('Branch updated.')
        ->assertSee('Uptown Office');

    expect($branch->refresh()->name)->toBe('Uptown Office');

    $page->click("@delete-branch-{$branch->id}")
        ->assertSee('Delete branch')
        ->click('@confirm-delete-button')
        ->assertSee('Branch deleted.')
        ->assertDontSee('Uptown Office');

    $this->assertModelMissing($branch);
});
