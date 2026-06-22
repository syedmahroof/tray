<?php

use App\Models\Branch;
use App\Models\Contact;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\User;
use App\Models\VisitReport;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('users without permission cannot view reports', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
});

test('the reports page surfaces the most enquired products', function () {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $popular = Product::factory()->create(['branch_id' => $branch->id, 'name' => 'Popular Unit']);
    $other = Product::factory()->create(['branch_id' => $branch->id, 'name' => 'Quiet Unit']);
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);

    Enquiry::factory()->count(3)->create([
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'product_id' => $popular->id,
    ]);
    Enquiry::factory()->create([
        'branch_id' => $branch->id,
        'contact_id' => $contact->id,
        'product_id' => $other->id,
    ]);

    $this->actingAs($admin)
        ->get(route('reports.index'))
        ->assertInertia(fn ($page) => $page
            ->where('mostEnquiredProducts.0.name', 'Popular Unit')
            ->where('mostEnquiredProducts.0.count', 3)
            ->where('stats.enquiries', 4));
});

test('the reports page aggregates staff activity', function () {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');

    $staff = User::factory()->create(['branch_id' => $branch->id, 'name' => 'Active Staff']);
    VisitReport::factory()->count(2)->create([
        'branch_id' => $branch->id,
        'user_id' => $staff->id,
    ]);

    $this->actingAs($admin)
        ->get(route('reports.index'))
        ->assertInertia(fn ($page) => $page
            ->where(
                'staffPerformance',
                fn ($rows) => collect($rows)->firstWhere('name', 'Active Staff')['visits'] === 2,
            ));
});
