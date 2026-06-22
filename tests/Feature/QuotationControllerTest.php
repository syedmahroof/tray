<?php

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view quotations', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('quotations.index'))->assertForbidden();
});

test('a quotation is created with computed totals, generated number, and items', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->post(route('quotations.store'), [
            'contact_id' => $contact->id,
            'quotation_date' => '2026-06-22',
            'status' => 'draft',
            'discount' => 100,
            'tax_percent' => 10,
            'items' => [
                ['product_id' => $product->id, 'description' => 'Item A', 'quantity' => 2, 'unit_price' => 500],
                ['product_id' => null, 'description' => 'Item B', 'quantity' => 1, 'unit_price' => 200],
            ],
        ])
        ->assertRedirect();

    $quotation = Quotation::first();
    expect($quotation)->not->toBeNull();
    expect($quotation->number)->toStartWith('QT-');
    expect($quotation->created_by)->toBe($manager->id);
    // subtotal = 2*500 + 1*200 = 1200
    expect((float) $quotation->subtotal)->toBe(1200.0);
    // taxable = 1200 - 100 = 1100; tax = 10% = 110
    expect((float) $quotation->tax_amount)->toBe(110.0);
    // total = 1100 + 110 = 1210
    expect((float) $quotation->total)->toBe(1210.0);
    expect($quotation->items)->toHaveCount(2);
});

test('a quotation requires at least one item', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($manager)
        ->post(route('quotations.store'), [
            'contact_id' => $contact->id,
            'quotation_date' => '2026-06-22',
            'status' => 'draft',
            'items' => [],
        ])
        ->assertSessionHasErrors('items');
});

test('updating a quotation re-syncs items and recomputes totals', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);
    $quotation = Quotation::factory()->create(['branch_id' => $branch->id, 'contact_id' => $contact->id]);
    $quotation->items()->create(['description' => 'Old', 'quantity' => 1, 'unit_price' => 50]);

    $this->actingAs($manager)
        ->put(route('quotations.update', $quotation), [
            'contact_id' => $contact->id,
            'quotation_date' => '2026-06-22',
            'status' => 'sent',
            'items' => [
                ['product_id' => null, 'description' => 'New', 'quantity' => 3, 'unit_price' => 100],
            ],
        ])
        ->assertRedirect(route('quotations.show', $quotation));

    $quotation->refresh();
    expect($quotation->status)->toBe('sent');
    expect($quotation->items)->toHaveCount(1);
    expect($quotation->items->first()->description)->toBe('New');
    expect((float) $quotation->total)->toBe(300.0);
});

test('a quotation can be downloaded as a PDF', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    $quotation = Quotation::factory()->create(['branch_id' => $branch->id]);
    $quotation->items()->create(['description' => 'Line', 'quantity' => 1, 'unit_price' => 100]);

    $response = $this->actingAs($manager)->get(route('quotations.pdf', $quotation));

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('the quotation index can be filtered by status', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    Quotation::factory()->create(['status' => 'accepted']);
    Quotation::factory()->create(['status' => 'draft']);

    $this->actingAs($admin)
        ->get(route('quotations.index', ['status' => 'accepted']))
        ->assertInertia(fn ($page) => $page
            ->has('quotations.data', 1)
            ->where('quotations.data.0.status', 'accepted')
            ->where('filters.status', 'accepted'));
});
