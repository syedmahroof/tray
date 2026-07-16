<?php

use App\Models\Branch;
use App\Models\Builder;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
});

test('lists builders from the users own branch only', function () {
    Builder::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Alpha Constructions']);
    Builder::factory()->create(['branch_id' => Branch::factory()->create()->id, 'name' => 'Other Branch Builders']);

    Sanctum::actingAs($this->user);

    $names = collect($this->getJson('/api/builders')->assertOk()->json('data'))->pluck('name');

    expect($names)->toContain('Alpha Constructions');
    expect($names)->not->toContain('Other Branch Builders');
});

test('creates a builder', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/builders', [
        'name' => 'Skyline Developers',
        'contact_person' => 'Ravi Kumar',
        'phone' => '9876543210',
        'email' => 'ravi@skyline.test',
    ])->assertCreated();

    $builder = Builder::where('name', 'Skyline Developers')->first();

    expect($builder)->not->toBeNull();
    expect($builder->branch_id)->toBe($this->branch->id);
    expect($builder->created_by)->toBe($this->user->id);
    expect($builder->is_active)->toBeTrue();
});

test('a builder requires a name', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/builders', ['contact_person' => 'No Name'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

test('updates a builder', function () {
    $builder = Builder::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Old Name']);

    Sanctum::actingAs($this->user);

    $this->putJson("/api/builders/{$builder->id}", [
        'name' => 'New Name',
        'contact_person' => 'Priya',
    ])->assertOk();

    expect($builder->fresh()->name)->toBe('New Name');
});

test('a builder that still has projects cannot be deleted', function () {
    $builder = Builder::factory()->create(['branch_id' => $this->branch->id]);
    Project::factory()->create(['branch_id' => $this->branch->id, 'builder_id' => $builder->id]);

    Sanctum::actingAs($this->user);

    $this->deleteJson("/api/builders/{$builder->id}")
        ->assertStatus(422)
        ->assertJson(['message' => 'Cannot delete a builder that still has projects.']);

    expect(Builder::find($builder->id))->not->toBeNull();
});

test('a builder without projects is deleted', function () {
    $builder = Builder::factory()->create(['branch_id' => $this->branch->id]);

    Sanctum::actingAs($this->user);

    $this->deleteJson("/api/builders/{$builder->id}")->assertNoContent();

    expect(Builder::find($builder->id))->toBeNull();
});

test('shows a builder with its projects', function () {
    $builder = Builder::factory()->create(['branch_id' => $this->branch->id]);
    Project::factory()->create([
        'branch_id' => $this->branch->id,
        'builder_id' => $builder->id,
        'name' => 'Harbour View Project',
    ]);

    Sanctum::actingAs($this->user);

    $response = $this->getJson("/api/builders/{$builder->id}")->assertOk();

    expect($response->json('projects_count'))->toBe(1);
    expect($response->json('projects.0.name'))->toBe('Harbour View Project');
});

test('guests cannot touch builders', function () {
    $this->getJson('/api/builders')->assertUnauthorized();
});

test('builders analytics reports totals split by active status', function () {
    Builder::factory()->count(2)->create(['branch_id' => $this->branch->id, 'is_active' => true]);
    Builder::factory()->create(['branch_id' => $this->branch->id, 'is_active' => false]);

    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/analytics/builders')->assertOk();

    expect($response->json('total'))->toBe(3);
    expect($response->json('by_status'))->toBe([
        ['label' => 'active', 'value' => 2],
        ['label' => 'inactive', 'value' => 1],
    ]);
    expect($response->json('recent'))->toHaveCount(3);
});
