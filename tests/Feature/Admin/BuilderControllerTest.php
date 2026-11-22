<?php

use App\Models\Branch;
use App\Models\Builder;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view builders', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('builders.index'))->assertForbidden();
});

test('managers can create a builder which is auto-assigned to their own branch', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->post(route('builders.store'), [
            'name' => 'Acme Developers',
            'gst_number' => '32AAACA1234B1Z9',
            'is_active' => true,
        ])
        ->assertRedirect(route('builders.index'));

    $builder = Builder::where('name', 'Acme Developers')->first();
    expect($builder)->not->toBeNull();
    expect($builder->gst_number)->toBe('32AAACA1234B1Z9');
    expect($builder->branch_id)->toBe($branch->id);
    expect($builder->created_by)->toBe($manager->id);
});

test('admins must choose a branch when creating a builder', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->post(route('builders.store'), ['name' => 'Acme Developers'])
        ->assertInvalid(['branch_id']);

    $branch = Branch::factory()->create();

    $this->actingAs($admin)
        ->post(route('builders.store'), [
            'name' => 'Acme Developers',
            'branch_id' => $branch->id,
        ])
        ->assertRedirect(route('builders.index'));

    $builder = Builder::where('name', 'Acme Developers')->first();
    expect($builder->branch_id)->toBe($branch->id);
});

test('managers only see builders belonging to their own branch', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branchA->id]);
    $manager->assignRole('Manager');
    Builder::factory()->create(['branch_id' => $branchA->id, 'name' => 'Branch A Builder']);
    Builder::factory()->create(['branch_id' => $branchB->id, 'name' => 'Branch B Builder']);

    $response = $this->actingAs($manager)->get(route('builders.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('builders.data', 1)
        ->where('builders.data.0.name', 'Branch A Builder'));
});

test('admins see builders across every branch', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branchA->id]);
    $admin->assignRole('Admin');
    Builder::factory()->create(['branch_id' => $branchA->id]);
    Builder::factory()->create(['branch_id' => $branchB->id]);

    $response = $this->actingAs($admin)->get(route('builders.index'));

    $response->assertInertia(fn ($page) => $page->has('builders.data', 2));
});

test('a builder cannot be deleted while it still has projects', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $builder = Builder::factory()->create(['branch_id' => $manager->branch_id]);
    Project::factory()->create(['builder_id' => $builder->id, 'branch_id' => $manager->branch_id]);

    $this->actingAs($manager)
        ->delete(route('builders.destroy', $builder))
        ->assertRedirect();

    $this->assertModelExists($builder);
});

test('the builder index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Builder::factory()->create(['name' => 'Skyline Developers']);
    Builder::factory()->create(['name' => 'Groundworks Ltd']);

    $this->actingAs($admin)
        ->get(route('builders.index', ['search' => 'Skyline']))
        ->assertInertia(fn ($page) => $page
            ->has('builders.data', 1)
            ->where('builders.data.0.name', 'Skyline Developers')
            ->where('filters.search', 'Skyline'));
});

test('the builder index can be filtered by creator and created date range', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $creator = User::factory()->create();
    Builder::factory()->create([
        'name' => 'Creator Builds',
        'created_by' => $creator->id,
        'created_at' => '2026-06-15 12:00:00',
    ]);
    Builder::factory()->create([
        'name' => 'Older Builds',
        'created_at' => '2026-01-10 12:00:00',
    ]);

    $this->actingAs($admin)
        ->get(route('builders.index', ['created_by' => $creator->id]))
        ->assertInertia(fn ($page) => $page
            ->has('builders.data', 1)
            ->where('builders.data.0.name', 'Creator Builds'));

    $this->actingAs($admin)
        ->get(route('builders.index', ['created_from' => '2026-06-01', 'created_to' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->has('builders.data', 1)
            ->where('builders.data.0.name', 'Creator Builds'));
});

test('a builder can be assigned to a user on create and update', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $assignee = User::factory()->create(['branch_id' => $branch->id]);
    $newAssignee = User::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->post(route('builders.store'), [
            'name' => 'Assigned Builds',
            'assigned_to' => $assignee->id,
        ])
        ->assertRedirect(route('builders.index'));

    $builder = Builder::where('name', 'Assigned Builds')->first();
    expect($builder->assigned_to)->toBe($assignee->id);
    expect($builder->assignee->is($assignee))->toBeTrue();

    $this->actingAs($manager)
        ->put(route('builders.update', $builder), [
            'name' => 'Assigned Builds',
            'assigned_to' => $newAssignee->id,
        ])
        ->assertRedirect(route('builders.index'));

    expect($builder->refresh()->assigned_to)->toBe($newAssignee->id);
});

test('assigning a builder to a non-existent user fails validation', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->post(route('builders.store'), [
            'name' => 'Bad Assignee',
            'assigned_to' => 99999,
        ])
        ->assertInvalid(['assigned_to']);
});

test('the builder index can be filtered by assignee', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $assignee = User::factory()->create();
    Builder::factory()->create(['name' => 'Assigned Builds', 'assigned_to' => $assignee->id]);
    Builder::factory()->create(['name' => 'Unassigned Builds']);

    $this->actingAs($admin)
        ->get(route('builders.index', ['assigned_to' => $assignee->id]))
        ->assertInertia(fn ($page) => $page
            ->has('builders.data', 1)
            ->where('builders.data.0.name', 'Assigned Builds')
            ->where('filters.assigned_to', (string) $assignee->id));
});

test('a builder keeps a multi-line address and any number of additional contacts, replaced on update', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $address = "Skyline Towers, 3rd Floor\nMG Road, Near Metro Station\n".trim(str_repeat('Kochi ', 60));

    $this->actingAs($manager)
        ->post(route('builders.store'), [
            'name' => 'Acme Developers',
            'address' => $address,
            'is_active' => true,
            'builder_contacts' => [
                ['name' => 'Ravi', 'role' => 'Purchase Manager', 'phone' => '9847000001', 'email' => 'ravi@example.com'],
                ['name' => 'Anu', 'role' => null, 'phone' => '9847000002', 'email' => null],
            ],
        ])
        ->assertRedirect(route('builders.index'));

    $builder = Builder::where('name', 'Acme Developers')->first();
    expect($builder->address)->toBe($address);
    expect($builder->builderContacts()->orderBy('id')->pluck('name')->all())->toBe(['Ravi', 'Anu']);

    $this->actingAs($manager)
        ->get(route('builders.show', $builder))
        ->assertInertia(fn ($page) => $page
            ->has('builder.builder_contacts', 2)
            ->where('builder.builder_contacts.0.role', 'Purchase Manager'));

    $this->actingAs($manager)
        ->get(route('builders.edit', $builder))
        ->assertInertia(fn ($page) => $page->has('builder.builder_contacts', 2));

    $this->actingAs($manager)
        ->put(route('builders.update', $builder), [
            'name' => 'Acme Developers',
            'is_active' => true,
            'builder_contacts' => [
                ['name' => 'Meera', 'phone' => '9847000003'],
            ],
        ])
        ->assertRedirect(route('builders.index'));

    expect($builder->builderContacts()->pluck('name')->all())->toBe(['Meera']);
});

test('an additional builder contact needs a name', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->post(route('builders.store'), [
            'name' => 'Acme Developers',
            'builder_contacts' => [['phone' => '9847000001']],
        ])
        ->assertSessionHasErrors('builder_contacts.0.name');
});
