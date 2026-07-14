<?php

use App\Models\Branch;
use App\Models\Contact;
use App\Models\ContactType;
use App\Models\Enquiry;
use App\Models\User;
use App\Models\VisitReport;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view contacts', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('contacts.index'))->assertForbidden();
});

test('sales executives can create, update, and delete a contact within their own branch', function () {
    $branch = Branch::factory()->create();
    $salesExecutive = User::factory()->create(['branch_id' => $branch->id]);
    $salesExecutive->assignRole('Sales Executive');
    $contactType = ContactType::factory()->create();

    $this->actingAs($salesExecutive)
        ->post(route('contacts.store'), [
            'contact_type_id' => $contactType->id,
            'name' => 'Jane Prospect',
            'phone' => '555-1234',
        ])
        ->assertRedirect();

    $contact = Contact::where('name', 'Jane Prospect')->first();
    expect($contact)->not->toBeNull();
    expect($contact->branch_id)->toBe($branch->id);
    expect($contact->created_by)->toBe($salesExecutive->id);

    $this->actingAs($salesExecutive)
        ->get(route('contacts.show', $contact))
        ->assertSuccessful();

    $this->actingAs($salesExecutive)
        ->patch(route('contacts.update', $contact), [
            'contact_type_id' => $contactType->id,
            'name' => 'Jane Customer',
        ])
        ->assertRedirect(route('contacts.show', $contact));

    expect($contact->refresh()->name)->toBe('Jane Customer');

    $this->actingAs($salesExecutive)
        ->delete(route('contacts.destroy', $contact))
        ->assertRedirect(route('contacts.index'));

    $this->assertModelMissing($contact);
});

test('a contact cannot be deleted while it still has enquiries', function () {
    $branch = Branch::factory()->create();
    $salesExecutive = User::factory()->create(['branch_id' => $branch->id]);
    $salesExecutive->assignRole('Sales Executive');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    Enquiry::factory()->create(['contact_id' => $contact->id, 'branch_id' => $branch->id]);

    $this->actingAs($salesExecutive)
        ->delete(route('contacts.destroy', $contact))
        ->assertRedirect();

    $this->assertModelExists($contact);
});

test('sales executives only see contacts belonging to their own branch', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $salesExecutive = User::factory()->create(['branch_id' => $branchA->id]);
    $salesExecutive->assignRole('Sales Executive');
    Contact::factory()->create(['branch_id' => $branchA->id, 'name' => 'Branch A Contact']);
    Contact::factory()->create(['branch_id' => $branchB->id, 'name' => 'Branch B Contact']);

    $response = $this->actingAs($salesExecutive)->get(route('contacts.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('contacts.data', 1)
        ->where('contacts.data.0.name', 'Branch A Contact'));
});

test('the contact index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Contact::factory()->create(['name' => 'Findable Fred', 'email' => 'fred@example.com']);
    Contact::factory()->create(['name' => 'Other Olivia', 'email' => 'olivia@example.com']);

    $this->actingAs($admin)
        ->get(route('contacts.index', ['search' => 'fred@example']))
        ->assertInertia(fn ($page) => $page
            ->has('contacts.data', 1)
            ->where('contacts.data.0.name', 'Findable Fred')
            ->where('filters.search', 'fred@example'));
});

test('the contact index can be filtered to those without a recent visit report', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $recentlyVisited = Contact::factory()->create(['name' => 'Recently Visited']);
    $recentReport = VisitReport::factory()->create(['visit_date' => now()->subDays(2)->toDateString()]);
    $recentReport->contacts()->attach($recentlyVisited);

    $staleContact = Contact::factory()->create(['name' => 'Not Visited Lately']);
    $oldReport = VisitReport::factory()->create(['visit_date' => now()->subDays(40)->toDateString()]);
    $oldReport->contacts()->attach($staleContact);

    $neverVisited = Contact::factory()->create(['name' => 'Never Visited']);

    $this->actingAs($admin)
        ->get(route('contacts.index', ['no_visit_within' => '7d']))
        ->assertInertia(fn ($page) => $page
            ->where('filters.no_visit_within', '7d')
            ->where('contacts.data', function ($contacts) use ($recentlyVisited, $staleContact, $neverVisited) {
                $ids = collect($contacts)->pluck('id');

                return ! $ids->contains($recentlyVisited->id)
                    && $ids->contains($staleContact->id)
                    && $ids->contains($neverVisited->id);
            }));
});

test('the contact index can be filtered by creator (super admin only) and created date range', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('Super Admin');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $creator = User::factory()->create();
    Contact::factory()->create([
        'name' => 'Created By Creator',
        'created_by' => $creator->id,
        'created_at' => '2026-06-15 12:00:00',
    ]);
    Contact::factory()->create([
        'name' => 'Older Contact',
        'created_at' => '2026-01-10 12:00:00',
    ]);

    // Super Admin can filter by creator
    $this->actingAs($superAdmin)
        ->get(route('contacts.index', ['created_by' => $creator->id]))
        ->assertInertia(fn ($page) => $page
            ->has('contacts.data', 1)
            ->where('contacts.data.0.name', 'Created By Creator'));

    // Non-super admin (Admin) cannot filter by creator (the filter is ignored)
    $this->actingAs($admin)
        ->get(route('contacts.index', ['created_by' => $creator->id]))
        ->assertInertia(fn ($page) => $page
            ->has('contacts.data', 2));

    // Both can filter by date range
    $this->actingAs($admin)
        ->get(route('contacts.index', ['created_from' => '2026-06-01', 'created_to' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->has('contacts.data', 1)
            ->where('contacts.data.0.name', 'Created By Creator'));
});
