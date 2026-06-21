<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('page content has breathing room below the header on the dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $page = visit('/dashboard');
    $page->assertNoJavaScriptErrors();
    $page->screenshot(true, 'layout-margin-dashboard');
});

test('page content has breathing room below the header on contacts index', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $page = visit('/contacts');
    $page->assertNoJavaScriptErrors();
    $page->screenshot(true, 'layout-margin-contacts');
});
