<?php

use App\Models\Branch;
use App\Models\Builder;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view projects', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('projects.index'))->assertForbidden();
});

test('managers can create, update, and delete a project within their own branch', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $builder = Builder::factory()->create(['branch_id' => $manager->branch_id]);
    $category = ProjectCategory::factory()->create();

    $assignee = User::factory()->create(['branch_id' => $branch->id]);
    $contactA = Contact::factory()->create(['branch_id' => $branch->id]);
    $contactB = Contact::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->post(route('projects.store'), [
            'builder_id' => $builder->id,
            'project_category_id' => $category->id,
            'name' => 'Skyline Residency',
            'status' => 'planning',
            'owner_name' => 'John Doe',
            'owner_phone' => '1234567890',
            'owner_email' => 'john@example.com',
            'location' => 'Downtown',
            'pincode' => '123456',
            'expected_maturity' => '2026-12-31',
            'preferred_material' => 'Steel & Concrete',
            'assignee_id' => $assignee->id,
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'contacts' => [$contactA->id, $contactB->id],
            'project_contacts' => [
                ['name' => 'Site Supervisor', 'role' => 'Supervisor', 'phone' => '1112223333', 'email' => 'supervisor@example.com'],
                ['name' => 'Site Engineer', 'role' => 'Engineer', 'phone' => '4445556666', 'email' => 'engineer@example.com'],
            ],
        ])
        ->assertRedirect(route('projects.index'));

    $project = Project::where('name', 'Skyline Residency')->first();
    expect($project)->not->toBeNull();
    expect($project->branch_id)->toBe($manager->branch_id);
    expect($project->owner_name)->toBe('John Doe');
    expect($project->owner_phone)->toBe('1234567890');
    expect($project->owner_email)->toBe('john@example.com');
    expect($project->location)->toBe('Downtown');
    expect($project->pincode)->toBe('123456');
    expect($project->expected_maturity)->toBe('2026-12-31');
    expect($project->preferred_material)->toBe('Steel & Concrete');
    expect($project->assignee_id)->toBe($assignee->id);
    expect($project->created_by)->toBe($manager->id);
    expect($project->start_date)->toBe('2026-07-01');
    expect($project->end_date)->toBe('2026-12-31');
    expect($project->contacts->pluck('id')->toArray())->toEqualCanonicalizing([$contactA->id, $contactB->id]);
    expect($project->projectContacts->pluck('name')->toArray())->toEqualCanonicalizing(['Site Supervisor', 'Site Engineer']);

    $this->actingAs($manager)
        ->patch(route('projects.update', $project), [
            'builder_id' => $builder->id,
            'project_category_id' => $category->id,
            'name' => 'Skyline Residency',
            'status' => 'ongoing',
            'contacts' => [$contactA->id],
            'project_contacts' => [
                ['name' => 'Site Engineer', 'role' => 'Engineer', 'phone' => '4445556666', 'email' => 'engineer@example.com'],
            ],
        ])
        ->assertRedirect(route('projects.index'));

    expect($project->refresh()->status)->toBe('ongoing');
    expect($project->contacts->pluck('id')->toArray())->toEqualCanonicalizing([$contactA->id]);
    expect($project->projectContacts->pluck('name')->toArray())->toEqualCanonicalizing(['Site Engineer']);

    $this->actingAs($manager)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'));

    $this->assertModelMissing($project);
});

test('a project can be created with linked products', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $category = ProjectCategory::factory()->create();
    $productA = Product::factory()->create(['branch_id' => $branch->id]);
    $productB = Product::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->post(route('projects.store'), [
            'project_category_id' => $category->id,
            'name' => 'Product Linked Project',
            'status' => 'planning',
            'product_ids' => [$productA->id, $productB->id],
        ])
        ->assertRedirect(route('projects.index'));

    $project = Project::where('name', 'Product Linked Project')->first();
    expect($project->products->pluck('id')->toArray())
        ->toEqualCanonicalizing([$productA->id, $productB->id]);
});

test('project products can be synced on update', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $category = ProjectCategory::factory()->create();
    $project = Project::factory()->create(['branch_id' => $branch->id]);
    $oldProduct = Product::factory()->create(['branch_id' => $branch->id]);
    $newProduct = Product::factory()->create(['branch_id' => $branch->id]);
    $project->products()->attach($oldProduct);

    $this->actingAs($manager)
        ->patch(route('projects.update', $project), [
            'builder_id' => $project->builder_id,
            'project_category_id' => $category->id,
            'name' => $project->name,
            'status' => 'planning',
            'product_ids' => [$newProduct->id],
        ])
        ->assertRedirect(route('projects.index'));

    expect($project->refresh()->products->pluck('id')->toArray())
        ->toEqualCanonicalizing([$newProduct->id]);
});

test('the project index can be filtered by product', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $product = Product::factory()->create();
    $withProduct = Project::factory()->create(['name' => 'Has The Product']);
    $withProduct->products()->attach($product);
    Project::factory()->create(['name' => 'No Product']);

    $this->actingAs($admin)
        ->get(route('projects.index', ['product_id' => $product->id]))
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.name', 'Has The Product')
            ->where('filters.product_id', (string) $product->id));
});

test('a project can be created without a builder', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $category = ProjectCategory::factory()->create();

    $this->actingAs($manager)
        ->post(route('projects.store'), [
            'project_category_id' => $category->id,
            'name' => 'Builderless Project',
            'status' => 'planning',
        ])
        ->assertRedirect(route('projects.index'));

    $project = Project::where('name', 'Builderless Project')->first();
    expect($project)->not->toBeNull();
    expect($project->builder_id)->toBeNull();
});

test('project contacts require a name', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $builder = Builder::factory()->create(['branch_id' => $manager->branch_id]);
    $category = ProjectCategory::factory()->create();

    $this->actingAs($manager)
        ->post(route('projects.store'), [
            'builder_id' => $builder->id,
            'project_category_id' => $category->id,
            'name' => 'Skyline Residency',
            'status' => 'planning',
            'project_contacts' => [
                ['role' => 'Supervisor', 'phone' => '1112223333', 'email' => 'supervisor@example.com'],
            ],
        ])
        ->assertInvalid(['project_contacts.0.name']);
});

test('omitting project contacts on update clears existing ones', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $builder = Builder::factory()->create(['branch_id' => $manager->branch_id]);
    $category = ProjectCategory::factory()->create();
    $project = Project::factory()->create(['branch_id' => $branch->id, 'builder_id' => $builder->id]);
    $project->projectContacts()->create(['name' => 'Site Supervisor']);

    $this->actingAs($manager)
        ->patch(route('projects.update', $project), [
            'builder_id' => $builder->id,
            'project_category_id' => $category->id,
            'name' => $project->name,
            'status' => 'ongoing',
        ])
        ->assertRedirect(route('projects.index'));

    expect($project->projectContacts()->count())->toBe(0);
});

test('project status must be one of the allowed values', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $builder = Builder::factory()->create(['branch_id' => $manager->branch_id]);
    $category = ProjectCategory::factory()->create();

    $this->actingAs($manager)
        ->post(route('projects.store'), [
            'builder_id' => $builder->id,
            'project_category_id' => $category->id,
            'name' => 'Skyline Residency',
            'status' => 'not-a-real-status',
        ])
        ->assertInvalid(['status']);
});

test('managers only see projects belonging to their own branch', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branchA->id]);
    $manager->assignRole('Manager');
    Project::factory()->create(['branch_id' => $branchA->id, 'name' => 'Branch A Project']);
    Project::factory()->create(['branch_id' => $branchB->id, 'name' => 'Branch B Project']);

    $response = $this->actingAs($manager)->get(route('projects.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('projects.data', 1)
        ->where('projects.data.0.name', 'Branch A Project'));
});

test('the project index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Project::factory()->create(['name' => 'Riverside Heights']);
    Project::factory()->create(['name' => 'Hilltop Gardens']);

    $this->actingAs($admin)
        ->get(route('projects.index', ['search' => 'Riverside']))
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.name', 'Riverside Heights')
            ->where('filters.search', 'Riverside'));
});

test('the project index exposes status count stats', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Project::factory()->count(2)->create(['status' => 'planning']);
    Project::factory()->create(['status' => 'ongoing']);
    Project::factory()->count(3)->create(['status' => 'completed']);

    $this->actingAs($admin)
        ->get(route('projects.index'))
        ->assertInertia(fn ($page) => $page
            ->where('stats.total', 6)
            ->where('stats.planning', 2)
            ->where('stats.ongoing', 1)
            ->where('stats.completed', 3));
});

test('the project index can be filtered by creator', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $creator = User::factory()->create();
    Project::factory()->create(['name' => 'Made By Creator', 'created_by' => $creator->id]);
    Project::factory()->create(['name' => 'Made By Someone Else']);

    $this->actingAs($admin)
        ->get(route('projects.index', ['created_by' => $creator->id]))
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.name', 'Made By Creator')
            ->where('filters.created_by', (string) $creator->id));
});

test('the project index can be filtered by a created date range', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Project::factory()->create(['name' => 'Old Project', 'created_at' => '2026-01-10 12:00:00']);
    Project::factory()->create(['name' => 'Recent Project', 'created_at' => '2026-06-15 12:00:00']);

    $this->actingAs($admin)
        ->get(route('projects.index', ['created_from' => '2026-06-01', 'created_to' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->has('projects.data', 1)
            ->where('projects.data.0.name', 'Recent Project'));
});
