<?php

use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('created on shows india time when the user has no timezone set', function () {
    $admin = User::factory()->create(['timezone' => null]);
    $admin->assignRole('Admin');
    $quotation = Quotation::factory()->create();
    $quotation->forceFill(['created_at' => '2026-11-13 20:00:00'])->saveQuietly();

    $this->actingAs($admin);

    visit('/quotations')
        ->assertNoJavaScriptErrors()
        ->assertSee('14/11/26 01:30');
});

test('created on follows the timezone the user picked', function () {
    $admin = User::factory()->create(['timezone' => 'UTC']);
    $admin->assignRole('Admin');
    $quotation = Quotation::factory()->create();
    $quotation->forceFill(['created_at' => '2026-11-13 20:00:00'])->saveQuietly();

    $this->actingAs($admin);

    visit('/quotations')
        ->assertNoJavaScriptErrors()
        ->assertSee('13/11/26 20:00');
});
