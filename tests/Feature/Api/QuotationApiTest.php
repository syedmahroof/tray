<?php

use App\Models\Branch;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->user->assignRole('Manager');
});

test('a quotation can be created through the api without a customer', function () {
    $project = Project::factory()->create(['branch_id' => $this->branch->id]);

    Sanctum::actingAs($this->user);

    $this->postJson('/api/quotations', [
        'project_id' => $project->id,
        'status' => 'draft',
        'items' => [['description' => 'Pipe', 'quantity' => 1, 'unit_price' => 100]],
    ])
        ->assertCreated()
        ->assertJsonPath('customer_id', null)
        ->assertJsonPath('project_id', $project->id);
});

test('the api rejects a quotation linked to no entity', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/quotations', [
        'status' => 'draft',
        'items' => [['description' => 'Pipe', 'quantity' => 1, 'unit_price' => 100]],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('customer_id');
});
