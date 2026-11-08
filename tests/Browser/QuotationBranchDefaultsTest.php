<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('a new quotation starts with the branch terms and details', function () {
    $branch = Branch::factory()->create([
        'name' => 'Calicut',
        'quotation_terms' => "Payment: 30 days\nDelivery: 2 weeks",
        'quotation_details' => 'Sales Desk: Calicut',
    ]);
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');

    $this->actingAs($manager);

    visit('/quotations/create')
        ->assertNoJavaScriptErrors()
        ->assertValue('#terms', "Payment: 30 days\nDelivery: 2 weeks")
        ->assertValue('#details', 'Sales Desk: Calicut');
});

test('choosing another branch brings in its terms', function () {
    $headOffice = Branch::factory()->create(['name' => 'Head Office', 'quotation_terms' => null]);
    Branch::factory()->create(['name' => 'Calicut', 'quotation_terms' => 'Payment: 30 days']);
    $admin = User::factory()->create(['branch_id' => $headOffice->id]);
    $admin->assignRole('Admin');

    $this->actingAs($admin);

    $page = visit('/quotations/create');
    $page->assertNoJavaScriptErrors()->assertValue('#terms', '');

    $page->click('Select a branch');
    $page->click('[role="option"]:has-text("Calicut")');
    $page->assertValue('#terms', 'Payment: 30 days');
});

test('linking a customer and a project fills in the buyer GSTIN and site address', function () {
    $branch = Branch::factory()->create();
    $manager = User::factory()->create(['branch_id' => $branch->id]);
    $manager->assignRole('Manager');
    Customer::factory()->create(['branch_id' => $branch->id, 'name' => 'Acme Traders', 'gst_number' => '32AAAAA0000A1Z5']);
    Project::factory()->create(['branch_id' => $branch->id, 'name' => 'Infopark Tower', 'address' => 'Infopark Phase 2, Kakkanad']);

    $this->actingAs($manager);

    $page = visit('/quotations/create');
    $page->assertNoJavaScriptErrors()->assertValue('#gstin', '')->assertValue('#site_address', '');

    $page->click('text=Select a customer (optional)');
    $page->click('Acme Traders');
    $page->assertValue('#gstin', '32AAAAA0000A1Z5');

    $page->click('text=Select a project (optional)');
    $page->click('Infopark Tower');
    $page->assertValue('#site_address', 'Infopark Phase 2, Kakkanad');

    // Anything typed by hand stays put.
    $page->fill('#site_address', 'Godown, Edappally');
    $page->assertValue('#site_address', 'Godown, Edappally');
});
