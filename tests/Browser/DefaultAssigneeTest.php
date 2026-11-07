<?php

use App\Models\Branch;
use App\Models\Contact;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('a non-admin\'s new records are assigned to them by default', function (string $path) {
    $manager = User::factory()->create(['name' => 'Mia Manager', 'branch_id' => Branch::factory()]);
    $manager->assignRole('Manager');
    User::factory()->create(['name' => 'Other User']);

    $this->actingAs($manager);

    $page = visit($path);
    $page->assertValue('input[type="hidden"][name="assigned_to"]', (string) $manager->id);
    $page->assertSee('Mia Manager');
    $page->assertNoJavaScriptErrors();
})->with(['/builders/create', '/contacts/create', '/customers/create', '/enquiries/create']);

test('an admin\'s new records start unassigned', function (string $path) {
    $admin = User::factory()->create(['name' => 'Ada Admin', 'branch_id' => Branch::factory()]);
    $admin->assignRole('Admin');

    $this->actingAs($admin);

    $page = visit($path);
    $page->assertValue('input[type="hidden"][name="assigned_to"]', '');
    $page->assertNoJavaScriptErrors();
})->with(['/contacts/create', '/enquiries/create']);

test('the contact forms use the full page width', function () {
    $manager = User::factory()->create(['branch_id' => Branch::factory()]);
    $manager->assignRole('Manager');
    $contact = Contact::factory()->create(['branch_id' => $manager->branch_id]);

    $this->actingAs($manager);

    foreach (['/contacts/create', "/contacts/{$contact->id}/edit"] as $path) {
        $page = visit($path);
        expect($page->script('getComputedStyle(document.querySelector("form")).maxWidth'))->toBe('none');
        $page->assertNoJavaScriptErrors();
    }
});
