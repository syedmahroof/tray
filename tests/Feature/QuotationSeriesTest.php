<?php

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->branch = Branch::factory()->create();
    $this->manager = User::factory()->create(['branch_id' => $this->branch->id]);
    $this->manager->assignRole('Manager');
});

/**
 * Raise a quotation through the web form and return its number.
 */
function raiseQuotation(User $user, Branch $branch): string
{
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);

    test()->actingAs($user)
        ->post(route('quotations.store'), [
            'contact_id' => $contact->id,
            'quotation_date' => '2026-10-31',
            'status' => 'draft',
            'supply_type' => 'intra',
            'items' => [['description' => 'GI Clamp', 'quantity' => 1, 'unit_price' => 100, 'tax_percentage' => 18]],
        ])
        ->assertRedirect();

    return Quotation::query()->withoutGlobalScopes()->latest('id')->value('number');
}

test('a branch with a series numbers its quotations from it and moves the series on', function () {
    $this->branch->update(['quotation_prefix' => 'BTCLT/QT/', 'quotation_next_number' => 41]);

    expect(raiseQuotation($this->manager, $this->branch))->toBe('BTCLT/QT/041');
    expect(raiseQuotation($this->manager, $this->branch))->toBe('BTCLT/QT/042');
    expect($this->branch->refresh()->quotation_next_number)->toBe(43);
});

test('a branch series skips numbers that are already taken', function () {
    $this->branch->update(['quotation_prefix' => 'BTCLT/QT/', 'quotation_next_number' => 5]);
    Quotation::factory()->create(['branch_id' => $this->branch->id, 'number' => 'BTCLT/QT/005']);

    expect(raiseQuotation($this->manager, $this->branch))->toBe('BTCLT/QT/006');
    expect($this->branch->refresh()->quotation_next_number)->toBe(7);
});

test('a branch without a series keeps the company-wide numbering', function () {
    expect(raiseQuotation($this->manager, $this->branch))->toStartWith('QT-'.now()->format('Y').'-');
});

test('each branch keeps its own series', function () {
    $other = Branch::factory()->create(['quotation_prefix' => 'BTTVM/', 'quotation_next_number' => 900]);
    $otherManager = User::factory()->create(['branch_id' => $other->id]);
    $otherManager->assignRole('Manager');
    $this->branch->update(['quotation_prefix' => 'BTCLT/', 'quotation_next_number' => 1]);

    expect(raiseQuotation($this->manager, $this->branch))->toBe('BTCLT/001');
    expect(raiseQuotation($otherManager, $other))->toBe('BTTVM/900');
});

test('quotations raised through the api use the branch series', function () {
    $this->branch->update(['quotation_prefix' => 'BTMEP/', 'quotation_next_number' => 12]);
    $project = Project::factory()->create(['branch_id' => $this->branch->id]);

    Sanctum::actingAs($this->manager);

    $this->postJson('/api/quotations', [
        'project_id' => $project->id,
        'status' => 'draft',
        'items' => [['description' => 'Pipe', 'quantity' => 1, 'unit_price' => 100]],
    ])
        ->assertCreated()
        ->assertJsonPath('number', 'BTMEP/012');
});

test('admins set a branch quotation series from the branch settings', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $fields = ['name' => $this->branch->name, 'code' => $this->branch->code];

    $this->actingAs($admin)
        ->put(route('branches.update', $this->branch), [
            ...$fields,
            'quotation_prefix' => 'BTCLT/QT/26-27/',
            'quotation_next_number' => 150,
        ])
        ->assertRedirect(route('branches.index'));

    expect($this->branch->refresh())
        ->quotation_prefix->toBe('BTCLT/QT/26-27/')
        ->quotation_next_number->toBe(150);

    $this->actingAs($admin)
        ->put(route('branches.update', $this->branch), [...$fields, 'quotation_prefix' => 'BTCLT/QT/26-27/', 'quotation_next_number' => ''])
        ->assertRedirect(route('branches.index'));

    expect($this->branch->refresh()->quotation_next_number)->toBe(150);

    $this->actingAs($admin)
        ->put(route('branches.update', $this->branch), [...$fields, 'quotation_next_number' => 0])
        ->assertSessionHasErrors('quotation_next_number');
});
