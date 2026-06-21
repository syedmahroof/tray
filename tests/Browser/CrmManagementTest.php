<?php

use App\Models\Branch;
use App\Models\Builder;
use App\Models\Contact;
use App\Models\ContactType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('an admin can create a contact and add a note, reminder, and visit report', function () {
    $branch = Branch::factory()->create(['name' => 'Head Office']);
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    ContactType::factory()->create(['name' => 'Lead']);
    $this->actingAs($admin);

    $page = visit('/contacts');
    $page->assertSee('Contacts')->assertNoJavaScriptErrors();

    $page->click('New contact')->assertSee('New contact');
    $page->fill('name', 'Jane Prospect');
    $page->fill('phone', '555-0100');

    $page->select('contact_type_id', 'Lead');
    $page->select('branch_id', 'Head Office');

    $page->click('Create contact');
    $page->wait(1.5);
    $page->assertNoJavaScriptErrors();
    $page->assertSee('Jane Prospect');

    $contact = Contact::where('name', 'Jane Prospect')->first();
    expect($contact)->not->toBeNull();

    $page->click('Notes');
    $page->fill('body', 'Called and left a voicemail.');
    $page->click('Add note');
    $page->wait(1);
    $page->assertNoJavaScriptErrors();
    $page->assertSee('Called and left a voicemail.');

    $page->click('Reminders');
    $page->fill('title', 'Follow up call');
    $page->fill('remind_at', '2026-12-31T10:00');
    $page->click('Add reminder');
    $page->wait(1);
    $page->assertNoJavaScriptErrors();
    $page->assertSee('Follow up call');

    $page->click('@visit-reports-tab');
    $page->click('New visit report');
    $page->wait(1);
    $page->assertSee('Create Visit Report');
    $page->assertSee('Jane Prospect');

    $page->fill('visit_date', '2026-06-21');
    $page->select('visit_type', 'Site Visit');
    $page->fill('objective', 'Initial meeting');
    $page->select('branch_id', 'Head Office');
    $page->click('Create visit report');
    $page->wait(1.5);
    $page->assertNoJavaScriptErrors();
    $page->assertSee('Initial meeting');
    $page->assertSee('Jane Prospect');
});

test('a sales executive can create an enquiry against a contact with a project and product', function () {
    $branch = Branch::factory()->create();
    $salesExecutive = User::factory()->create(['branch_id' => $branch->id]);
    $salesExecutive->assignRole('Sales Executive');
    $contact = Contact::factory()->create(['branch_id' => $branch->id, 'name' => 'John Buyer']);
    $builder = Builder::factory()->create(['branch_id' => $branch->id]);
    $projectCategory = ProjectCategory::factory()->create();
    $productCategory = ProductCategory::factory()->create();
    $project = Project::factory()->create([
        'branch_id' => $branch->id,
        'builder_id' => $builder->id,
        'project_category_id' => $projectCategory->id,
        'name' => 'Skyline Residency',
    ]);
    Product::factory()->create([
        'branch_id' => $branch->id,
        'product_category_id' => $productCategory->id,
        'name' => '2BHK Tower A',
    ]);
    $this->actingAs($salesExecutive);

    $page = visit(route('contacts.show', $contact));
    $page->assertSee('John Buyer')->assertNoJavaScriptErrors();

    $page->click('New enquiry');
    $page->assertSee('New enquiry');

    $page->click('Select a project');
    $page->click('Skyline Residency');

    $page->click('Select a product');
    $page->click('2BHK Tower A');

    $page->select('status', 'in progress');
    $page->fill('source', 'Walk-in');

    $page->click('Create enquiry');
    $page->wait(1.5);
    $page->assertNoJavaScriptErrors();
    $page->assertSee('Skyline Residency');
    $page->assertSee('in progress');

    $enquiry = $contact->enquiries()->first();
    expect($enquiry)->not->toBeNull();
    expect($enquiry->project_id)->toBe($project->id);
});
