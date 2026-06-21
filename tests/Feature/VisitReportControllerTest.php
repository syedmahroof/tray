<?php

use App\Models\User;
use App\Models\VisitReport;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('the visit report index can be filtered by a search term', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    VisitReport::factory()->create(['objective' => 'Discuss Penthouse pricing']);
    VisitReport::factory()->create(['objective' => 'Routine follow-up call']);

    $this->actingAs($admin)
        ->get(route('visit-reports.index', ['search' => 'Penthouse']))
        ->assertInertia(fn ($page) => $page
            ->has('visitReports.data', 1)
            ->where('visitReports.data.0.objective', 'Discuss Penthouse pricing')
            ->where('filters.search', 'Penthouse'));
});

test('the visit report index can be filtered by a created date range', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    VisitReport::factory()->create([
        'objective' => 'Recent visit',
        'created_at' => '2026-06-15 12:00:00',
    ]);
    VisitReport::factory()->create([
        'objective' => 'Old visit',
        'created_at' => '2026-01-10 12:00:00',
    ]);

    $this->actingAs($admin)
        ->get(route('visit-reports.index', ['created_from' => '2026-06-01', 'created_to' => '2026-06-30']))
        ->assertInertia(fn ($page) => $page
            ->has('visitReports.data', 1)
            ->where('visitReports.data.0.objective', 'Recent visit'));
});
