<?php

use App\Models\Contact;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('pressing Enter in a form field does not save the form', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    $page = visit('/contacts/create');
    $page->fill('#name', 'Half Typed Contact');
    $page->keys('#name', 'Enter');
    $page->wait(1);

    $page->assertPathIs('/contacts/create')
        ->assertValue('#name', 'Half Typed Contact')
        ->assertNoJavaScriptErrors();

    expect(Contact::query()->where('name', 'Half Typed Contact')->exists())->toBeFalse();
});

test('pressing Enter on the login form does not submit it either', function () {
    User::factory()->create(['email' => 'staff@example.com']);

    $page = visit('/login');
    $page->fill('#email', 'staff@example.com');
    $page->fill('#password', 'password');
    $page->keys('#password', 'Enter');
    $page->wait(1);

    $page->assertPathIs('/login')->assertNoJavaScriptErrors();
    $this->assertGuest();
});
