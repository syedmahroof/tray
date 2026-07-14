<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view customers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('customers.index'))->assertForbidden();
});

test('sales executives can create, update, and delete a customer within their own branch', function () {
    $branch = Branch::factory()->create();
    $salesExecutive = User::factory()->create(['branch_id' => $branch->id]);
    $salesExecutive->assignRole('Sales Executive');

    $this->actingAs($salesExecutive)
        ->post(route('customers.store'), [
            'name' => 'Jane Customer',
            'phone' => '555-1234',
        ])
        ->assertRedirect();

    $customer = Customer::where('name', 'Jane Customer')->first();
    expect($customer)->not->toBeNull();
    expect($customer->branch_id)->toBe($branch->id);

    $this->actingAs($salesExecutive)
        ->get(route('customers.show', $customer))
        ->assertSuccessful();

    $this->actingAs($salesExecutive)
        ->patch(route('customers.update', $customer), [
            'name' => 'Jane Updated',
        ])
        ->assertRedirect(route('customers.show', $customer));

    expect($customer->refresh()->name)->toBe('Jane Updated');

    $this->actingAs($salesExecutive)
        ->delete(route('customers.destroy', $customer))
        ->assertRedirect(route('customers.index'));

    $this->assertModelMissing($customer);
});

test('a customer can be created with a GST number', function () {
    $branch = Branch::factory()->create();
    $salesExecutive = User::factory()->create(['branch_id' => $branch->id]);
    $salesExecutive->assignRole('Sales Executive');

    $this->actingAs($salesExecutive)
        ->post(route('customers.store'), [
            'name' => 'GST Customer',
            'gst_number' => '29ABCDE1234F1Z5',
        ])
        ->assertRedirect();

    expect(Customer::where('name', 'GST Customer')->first()->gst_number)->toBe('29ABCDE1234F1Z5');
});

test('sales executives only see customers belonging to their own branch', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $salesExecutive = User::factory()->create(['branch_id' => $branchA->id]);
    $salesExecutive->assignRole('Sales Executive');
    Customer::factory()->create(['branch_id' => $branchA->id, 'name' => 'Branch A Customer']);
    Customer::factory()->create(['branch_id' => $branchB->id, 'name' => 'Branch B Customer']);

    $response = $this->actingAs($salesExecutive)->get(route('customers.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('customers.data', 1)
        ->where('customers.data.0.name', 'Branch A Customer'));
});

test('the customer index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Customer::factory()->create(['name' => 'Searchable Sam']);
    Customer::factory()->create(['name' => 'Hidden Hank']);

    $this->actingAs($admin)
        ->get(route('customers.index', ['search' => 'Searchable']))
        ->assertInertia(fn ($page) => $page
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'Searchable Sam')
            ->where('filters.search', 'Searchable'));
});
