<?php

use App\Http\Controllers\AnalyticsController;
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

});

test('the old visit reports analytics address opens the new page', function () {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');
    VisitReport::factory()->create(['branch_id' => $branch->id, 'user_id' => $admin->id, 'visit_type' => 'Site Visit']);
    $this->actingAs($admin);

    visit('/visit-reports/analytics')
        ->assertSee('Visit Reports Analytics')
        ->assertSee('Visits over time')
        ->assertSee('Visits by type')
        ->assertSee('Staff activity')
        ->assertNoJavaScriptErrors();
});

test('every analytics section loads in the browser and takes a custom range', function () {
    $branch = Branch::factory()->create();
    $admin = User::factory()->create(['branch_id' => $branch->id]);
    $admin->assignRole('Admin');
    VisitReport::factory()->create(['branch_id' => $branch->id, 'user_id' => $admin->id, 'visit_date' => now()->toDateString()]);
    $this->actingAs($admin);

    $page = visit('/analytics');

    foreach (AnalyticsController::SECTIONS as $section => [$class]) {
        $page->click('[data-test="section-'.$section.'"]')
            ->assertSee(app($class)->title().' Analytics')
            ->assertPresent('[data-test="analytics-stats"]')
            ->assertNoJavaScriptErrors();
    }
});

test('a custom period shows its dates and pickers', function () {
    $admin = User::factory()->create(['branch_id' => Branch::factory()->create()->id]);
    $admin->assignRole('Admin');
    $this->actingAs($admin);

    visit('/analytics/visit-reports?period=custom&from=2026-01-01&to=2026-01-31')
        ->assertSeeIn('[data-test="period-label"]', '01 Jan 2026')
        ->assertPresent('[data-test="period-from"]')
        ->assertPresent('[data-test="period-apply"]')
        ->assertNoJavaScriptErrors();
});
