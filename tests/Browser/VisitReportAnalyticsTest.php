<?php

use App\Models\Branch;
use App\Models\User;
use App\Models\VisitReport;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('an admin can see type counts on the visit reports list and view the analytics page', function () {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');
    VisitReport::factory()->create(['branch_id' => $branch->id, 'user_id' => $admin->id, 'visit_type' => 'Site Visit']);
    VisitReport::factory()->create(['branch_id' => $branch->id, 'user_id' => $admin->id, 'visit_type' => 'Client Meeting']);
    $this->actingAs($admin);

    $page = visit('/visit-reports');
    $page->assertSee('Visit Reports')
        ->assertSee('Site Visit')
        ->assertSee('Client Meeting')
        ->assertNoJavaScriptErrors();

    $page->click('Analytics');
    $page->assertSee('Visit Reports Analytics');
    $page->assertSee('Total visit reports');
    $page->assertSee('Visit reports by type');
    $page->assertSee('Visit reports per month');
    $page->assertNoJavaScriptErrors();
});
