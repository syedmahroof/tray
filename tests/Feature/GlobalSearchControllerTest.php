<?php

use App\Models\Branch;
use App\Models\Builder;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use App\Models\VisitReport;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('guests cannot use global search', function () {
    $this->get(route('global-search', ['q' => 'Skyline']))
        ->assertRedirect(route('login'));
});

test('authenticated users can search projects, builders, contacts, products, customers, enquiries, and visit reports', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);
    $user->assignRole('Sales Executive');

    $projectCategory = ProjectCategory::factory()->create();
    $productCategory = ProductCategory::factory()->create();

    $project = Project::factory()->create(['branch_id' => $branch->id, 'name' => 'Skyline Residency', 'project_category_id' => $projectCategory->id]);
    $builder = Builder::factory()->create(['branch_id' => $branch->id, 'name' => 'Skyline Developers']);
    $contact = Contact::factory()->create(['branch_id' => $branch->id, 'name' => 'Skyline Buyer']);
    $product = Product::factory()->create(['branch_id' => $branch->id, 'name' => 'Skyline Product', 'product_category_id' => $productCategory->id]);
    $customer = Customer::factory()->create(['branch_id' => $branch->id, 'name' => 'Skyline Customer']);
    $enquiry = Enquiry::factory()->create([
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'project_id' => $project->id,
        'remarks' => 'Skyline Enquiry',
    ]);
    $visitReport = VisitReport::factory()->create([
        'branch_id' => $branch->id,
        'user_id' => $user->id,
        'objective' => 'Skyline Meeting',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('global-search', ['q' => 'Skyline']))
        ->assertOk();

    $response->assertJsonStructure([
        'projects' => [
            '*' => ['id', 'name', 'url'],
        ],
        'builders' => [
            '*' => ['id', 'name', 'url'],
        ],
        'contacts' => [
            '*' => ['id', 'name', 'phone', 'email', 'url'],
        ],
        'products' => [
            '*' => ['id', 'name', 'url'],
        ],
        'customers' => [
            '*' => ['id', 'name', 'phone', 'email', 'url'],
        ],
        'enquiries' => [
            '*' => ['id', 'contact_name', 'project_name', 'product_name', 'status', 'url'],
        ],
        'visit_reports' => [
            '*' => ['id', 'visit_type', 'objective', 'visit_date', 'url'],
        ],
    ]);

    $data = $response->json();
    expect($data['projects'])->toHaveCount(1);
    expect($data['projects'][0]['name'])->toBe('Skyline Residency');

    expect($data['builders'])->toHaveCount(1);
    expect($data['builders'][0]['name'])->toBe('Skyline Developers');

    expect($data['contacts'])->toHaveCount(1);
    expect($data['contacts'][0]['name'])->toBe('Skyline Buyer');

    expect($data['products'])->toHaveCount(1);
    expect($data['products'][0]['name'])->toBe('Skyline Product');

    expect($data['customers'])->toHaveCount(1);
    expect($data['customers'][0]['name'])->toBe('Skyline Customer');

    expect($data['enquiries'])->toHaveCount(1);
    expect($data['enquiries'][0]['remarks'] ?? '')->toBe('');

    expect($data['visit_reports'])->toHaveCount(1);
    expect($data['visit_reports'][0]['objective'])->toBe('Skyline Meeting');
});

test('branch scoping is automatically applied for search results based on user role', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();

    $userA = User::factory()->create(['branch_id' => $branchA->id]);
    $userA->assignRole('Sales Executive');

    $projectCategory = ProjectCategory::factory()->create();

    Project::factory()->create(['branch_id' => $branchA->id, 'name' => 'Skyline Branch A', 'project_category_id' => $projectCategory->id]);
    Project::factory()->create(['branch_id' => $branchB->id, 'name' => 'Skyline Branch B', 'project_category_id' => $projectCategory->id]);

    // Sales Executive A should only see Skyline Branch A
    $response = $this->actingAs($userA)
        ->getJson(route('global-search', ['q' => 'Skyline']))
        ->assertOk();

    $data = $response->json();
    expect($data['projects'])->toHaveCount(1);
    expect($data['projects'][0]['name'])->toBe('Skyline Branch A');

    // Admin should see both Skyline Branch A and B
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $adminResponse = $this->actingAs($admin)
        ->getJson(route('global-search', ['q' => 'Skyline']))
        ->assertOk();

    $adminData = $adminResponse->json();
    expect($adminData['projects'])->toHaveCount(2);
});
