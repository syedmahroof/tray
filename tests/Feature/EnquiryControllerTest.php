<?php

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Enquiry;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view enquiries', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('enquiries.index'))->assertForbidden();
});

test('telecallers can create and update an enquiry but cannot delete it', function () {
    $branch = Branch::factory()->create();
    $telecaller = User::factory()->create(['branch_id' => $branch->id]);
    $telecaller->assignRole('Telecaller');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($telecaller)
        ->post(route('enquiries.store'), [
            'contact_id' => $contact->id,
            'status' => 'new',
        ])
        ->assertRedirect();

    $enquiry = Enquiry::where('contact_id', $contact->id)->first();
    expect($enquiry)->not->toBeNull();
    expect($enquiry->branch_id)->toBe($branch->id);

    $this->actingAs($telecaller)
        ->patch(route('enquiries.update', $enquiry), [
            'contact_id' => $contact->id,
            'status' => 'in_progress',
        ])
        ->assertRedirect(route('enquiries.show', $enquiry));

    expect($enquiry->refresh()->status)->toBe('in_progress');

    $this->actingAs($telecaller)
        ->delete(route('enquiries.destroy', $enquiry))
        ->assertForbidden();

    $this->assertModelExists($enquiry);
});

test('enquiry status must be one of the allowed values', function () {
    $branch = Branch::factory()->create();
    $salesExecutive = User::factory()->create(['branch_id' => $branch->id]);
    $salesExecutive->assignRole('Sales Executive');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($salesExecutive)
        ->post(route('enquiries.store'), [
            'contact_id' => $contact->id,
            'status' => 'not-a-real-status',
        ])
        ->assertInvalid(['status']);
});

test('sales executives only see enquiries belonging to their own branch', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $salesExecutive = User::factory()->create(['branch_id' => $branchA->id]);
    $salesExecutive->assignRole('Sales Executive');
    $contactA = Contact::factory()->create(['branch_id' => $branchA->id]);
    $contactB = Contact::factory()->create(['branch_id' => $branchB->id]);
    Enquiry::factory()->create(['branch_id' => $branchA->id, 'contact_id' => $contactA->id]);
    Enquiry::factory()->create(['branch_id' => $branchB->id, 'contact_id' => $contactB->id]);

    $response = $this->actingAs($salesExecutive)->get(route('enquiries.index'));

    $response->assertInertia(fn ($page) => $page->has('enquiries.data', 1));
});

test('the enquiry index can be filtered by the related contact name', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $findable = Contact::factory()->create(['name' => 'Findable Fiona']);
    $other = Contact::factory()->create(['name' => 'Hidden Harry']);
    Enquiry::factory()->create(['contact_id' => $findable->id]);
    Enquiry::factory()->create(['contact_id' => $other->id]);

    $this->actingAs($admin)
        ->get(route('enquiries.index', ['search' => 'Fiona']))
        ->assertInertia(fn ($page) => $page
            ->has('enquiries.data', 1)
            ->where('enquiries.data.0.contact.name', 'Findable Fiona')
            ->where('filters.search', 'Fiona'));
});
