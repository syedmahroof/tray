<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->branch = Branch::factory()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->assignee = User::factory()->create(['branch_id' => $this->branch->id]);

    Sanctum::actingAs($this->user);
});

test('projects can be filtered by assignee', function () {
    Project::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Assigned Project',
        'assignee_id' => $this->assignee->id,
    ]);
    Project::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Unassigned Project']);

    $names = collect($this->getJson("/api/projects?assignee_id={$this->assignee->id}")->assertOk()->json('data'))
        ->pluck('name');

    expect($names)->toContain('Assigned Project');
    expect($names)->not->toContain('Unassigned Project');
});

test('customers can be filtered by assignee', function () {
    Customer::factory()->create([
        'branch_id' => $this->branch->id,
        'name' => 'Assigned Customer',
        'assigned_to' => $this->assignee->id,
    ]);
    Customer::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Unassigned Customer']);

    $names = collect($this->getJson("/api/customers?assigned_to={$this->assignee->id}")->assertOk()->json('data'))
        ->pluck('name');

    expect($names)->toContain('Assigned Customer');
    expect($names)->not->toContain('Unassigned Customer');
});

test('enquiries can be filtered by assignee', function () {
    $assigned = Enquiry::factory()->create([
        'branch_id' => $this->branch->id,
        'assigned_to' => $this->assignee->id,
    ]);
    $unassigned = Enquiry::factory()->create(['branch_id' => $this->branch->id]);

    $ids = collect($this->getJson("/api/enquiries?assigned_to={$this->assignee->id}")->assertOk()->json('data'))
        ->pluck('id');

    expect($ids)->toContain($assigned->id);
    expect($ids)->not->toContain($unassigned->id);
});
