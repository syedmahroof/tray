<?php

use App\Models\Branch;
use App\Models\Builder;
use App\Models\Contact;
use App\Models\User;
use App\Models\VisitReport;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('the show page lists previous visit reports that share a linked entity', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $branch = Branch::factory()->create();
    $contact = Contact::factory()->create(['branch_id' => $branch->id]);

    $older = VisitReport::factory()->create(['branch_id' => $branch->id, 'visit_date' => '2026-01-01']);
    $older->contacts()->sync([$contact->id]);

    $unrelated = VisitReport::factory()->create(['branch_id' => $branch->id]);

    $current = VisitReport::factory()->create(['branch_id' => $branch->id, 'visit_date' => '2026-06-01']);
    $current->contacts()->sync([$contact->id]);

    $this->actingAs($admin)
        ->get(route('visit-reports.show', $current))
        ->assertInertia(fn ($page) => $page
            ->component('visit-reports/Show')
            ->where('history', fn ($history) => collect($history)->pluck('id')->contains($older->id)
                && ! collect($history)->pluck('id')->contains($unrelated->id)
                && ! collect($history)->pluck('id')->contains($current->id)));
});

test('the create and edit forms expose the builder options and linked builders', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $branch = Branch::factory()->create();
    $builder = Builder::factory()->create(['branch_id' => $branch->id, 'name' => 'Skyline Developers']);

    $report = VisitReport::factory()->create(['branch_id' => $branch->id]);
    $report->builders()->sync([$builder->id]);

    $this->actingAs($admin)
        ->get(route('visit-reports.create'))
        ->assertInertia(fn ($page) => $page
            ->component('visit-reports/Create')
            ->has('builders', 1)
            ->where('builders.0.name', 'Skyline Developers'));

    $this->actingAs($admin)
        ->get(route('visit-reports.edit', $report))
        ->assertInertia(fn ($page) => $page
            ->component('visit-reports/Edit')
            ->has('builders', 1)
            ->has('visitReport.builders', 1)
            ->where('visitReport.builders.0.id', $builder->id));
});

test('the show page exposes the linked builders and builder-matched history', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $branch = Branch::factory()->create();
    $builder = Builder::factory()->create(['branch_id' => $branch->id]);

    $older = VisitReport::factory()->create(['branch_id' => $branch->id, 'visit_date' => '2026-01-01']);
    $older->builders()->sync([$builder->id]);

    $current = VisitReport::factory()->create(['branch_id' => $branch->id, 'visit_date' => '2026-06-01']);
    $current->builders()->sync([$builder->id]);

    $this->actingAs($admin)
        ->get(route('visit-reports.show', $current))
        ->assertInertia(fn ($page) => $page
            ->has('visitReport.builders', 1)
            ->where('visitReport.builders.0.id', $builder->id)
            ->where('history', fn ($history) => collect($history)->pluck('id')->contains($older->id)));
});

test('storing and updating a visit report syncs the linked builders', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $branch = Branch::factory()->create();
    $builder = Builder::factory()->create(['branch_id' => $branch->id]);
    $otherBuilder = Builder::factory()->create(['branch_id' => $branch->id]);

    $this->actingAs($admin)
        ->post(route('visit-reports.store'), [
            'visit_date' => '2026-06-01',
            'visit_type' => 'Site Visit',
            'objective' => 'Discuss the tower handover',
            'branch_id' => $branch->id,
            'builder_ids' => [$builder->id],
        ])
        ->assertRedirect();

    $report = VisitReport::query()->latest('id')->firstOrFail();
    expect($report->builders->pluck('id')->all())->toBe([$builder->id]);

    $this->actingAs($admin)
        ->put(route('visit-reports.update', $report), [
            'visit_date' => '2026-06-02',
            'visit_type' => 'Site Visit',
            'objective' => 'Discuss the tower handover',
            'branch_id' => $branch->id,
            'builder_ids' => [$otherBuilder->id],
        ])
        ->assertRedirect();

    expect($report->refresh()->builders->pluck('id')->all())->toBe([$otherBuilder->id]);
});

test('the show page exposes the visit report audit log', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $report = VisitReport::factory()->create();

    $this->actingAs($admin)
        ->get(route('visit-reports.show', $report))
        ->assertInertia(fn ($page) => $page
            ->has('auditLogs', 1)
            ->where('auditLogs.0.action', 'created'));
});

test('updating a visit report records an audit log entry', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $report = VisitReport::factory()->create(['objective' => 'Initial objective']);

    $report->update(['objective' => 'Revised objective']);

    expect($report->auditLogs()->where('action', 'updated')->exists())->toBeTrue();
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
        'visit_date' => '2026-06-15',
    ]);
    VisitReport::factory()->create([
        'objective' => 'Old visit',
        'visit_date' => '2026-01-10',
    ]);

    $this->actingAs($admin)
        ->get(route('visit-reports.index', [
            'date_filter' => 'custom',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
        ]))
        ->assertInertia(fn ($page) => $page
            ->has('visitReports.data', 1)
            ->where('visitReports.data.0.objective', 'Recent visit'));
});
