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
            'is_active' => true,
        ])
        ->assertRedirect(route('builders.index'));

    $builder = Builder::where('name', 'Acme Developers')->first();
    expect($builder)->not->toBeNull();
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
