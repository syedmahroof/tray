<?php

use App\Models\Branch;
use App\Models\Order;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->manager = User::factory()->create(['branch_id' => Branch::factory()]);
    $this->manager->assignRole('Manager');
});

test('users without permission cannot manage suppliers', function () {
    $executive = User::factory()->create();
    $executive->assignRole('Sales Executive');

    $this->actingAs($executive)->get(route('suppliers.index'))->assertForbidden();
    $this->actingAs($executive)->post(route('suppliers.store'), ['name' => 'Acme'])->assertForbidden();
});

test('suppliers are listed with their purchase order count and can be searched', function () {
    $supplier = Supplier::factory()->create(['name' => 'Acme Pipes']);
    Supplier::factory()->create(['name' => 'Zenith Steel']);
    Order::factory()->purchase()->create(['branch_id' => $this->manager->branch_id, 'supplier_id' => $supplier->id]);

    $this->actingAs($this->manager)
        ->get(route('suppliers.index', ['search' => 'Acme']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/suppliers/Index')
            ->has('suppliers.data', 1)
            ->where('suppliers.data.0.name', 'Acme Pipes')
            ->where('suppliers.data.0.purchase_orders_count', 1));
});

test('a supplier can be created and updated', function () {
    $this->actingAs($this->manager)
        ->post(route('suppliers.store'), [
            'name' => 'Acme Pipes',
            'contact_person' => 'Ravi',
            'phone' => '9876543210',
            'email' => 'sales@acme.test',
            'gst_number' => '29ABCDE1234F1Z5',
            'address' => 'Industrial Area',
            'is_active' => '1',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $supplier = Supplier::query()->sole();

    expect($supplier->gst_number)->toBe('29ABCDE1234F1Z5')
        ->and($supplier->is_active)->toBeTrue();

    $this->actingAs($this->manager)
        ->put(route('suppliers.update', $supplier), ['name' => 'Acme Pipes Ltd'])
        ->assertSessionHasNoErrors();

    expect($supplier->refresh()->name)->toBe('Acme Pipes Ltd')
        ->and($supplier->is_active)->toBeFalse();
});

test('supplier names must be unique', function () {
    Supplier::factory()->create(['name' => 'Acme Pipes']);

    $this->actingAs($this->manager)
        ->post(route('suppliers.store'), ['name' => 'Acme Pipes'])
        ->assertSessionHasErrors('name');
});

test('a supplier with purchase orders cannot be deleted', function () {
    $supplier = Supplier::factory()->create();
    Order::factory()->purchase()->create(['branch_id' => $this->manager->branch_id, 'supplier_id' => $supplier->id]);

    $this->actingAs($this->manager)->delete(route('suppliers.destroy', $supplier));

    expect($supplier->fresh())->not->toBeNull();

    $unused = Supplier::factory()->create();

    $this->actingAs($this->manager)
        ->delete(route('suppliers.destroy', $unused))
        ->assertRedirect(route('suppliers.index'));

    expect($unused->fresh())->toBeNull();
});
