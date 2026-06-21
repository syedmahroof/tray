<?php

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Enquiry;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('an admin can see status counts on the enquiries list and drag a card between kanban columns', function () {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');
    $contact = Contact::factory()->create(['branch_id' => $branch->id, 'name' => 'Kanban Lead']);
    $enquiry = Enquiry::factory()->create([
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'status' => 'new',
    ]);
    $this->actingAs($admin);

    $page = visit('/enquiries');
    $page->assertSee('Enquiries')
        ->assertSee('New')
        ->assertSee('In progress')
        ->assertSee('Converted')
        ->assertSee('Lost')
        ->assertNoJavaScriptErrors();

    $page->click('Kanban view');
    $page->assertSee('Drag a card to a different column to update its status');
    $page->assertSee('Kanban Lead');

    $page->drag("@kanban-card-{$enquiry->id}", '@kanban-column-converted');
    $page->wait(1);
    $page->assertNoJavaScriptErrors();

    expect($enquiry->refresh()->status)->toBe('converted');
});
