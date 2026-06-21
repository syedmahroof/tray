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
