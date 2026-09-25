<?php

use App\Http\Controllers\AnalyticsController;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Route;
use App\Models\User;
use App\Models\VisitReport;
use App\Support\Analytics\AnalyticsPeriod;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->branch = Branch::factory()->create();
    $this->admin = User::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Asha']);
    $this->admin->assignRole('Admin');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('the analytics menu opens the first section the user may see', function () {
    $this->actingAs($this->admin)
        ->get(route('analytics.index'))
        ->assertRedirect(route('analytics.show', 'quotations'));
});

test('every section renders its report', function (string $section) {
    $this->actingAs($this->admin)
        ->get(route('analytics.show', $section))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('analytics/Show')
            ->where('section.key', $section)
            ->where('period.key', '30d')
            ->has('periods', count(AnalyticsPeriod::PRESETS))
            ->has('sections', count(AnalyticsController::SECTIONS))
            ->has('report.stats')
            ->has('report.charts')
            ->has('report.tables'));
})->with(array_keys(AnalyticsController::SECTIONS));

test('an unknown section is not found', function () {
    $this->actingAs($this->admin)->get('/analytics/unknown')->assertNotFound();
});

test('a section needs the permission of the module it reports on', function () {
    $telecaller = User::factory()->create(['branch_id' => $this->branch->id]);
    $telecaller->assignRole('Telecaller');

    $sections = collect(AnalyticsController::visibleSections(
        Request::create('/')->setUserResolver(fn () => $telecaller),
    ))->pluck('key');

    foreach (AnalyticsController::SECTIONS as $key => [, $permission]) {
        $response = $this->actingAs($telecaller)->get(route('analytics.show', $key));

        $telecaller->can($permission)
            ? $response->assertOk()
            : $response->assertForbidden();

        expect($sections->contains($key))->toBe($telecaller->can($permission));
    }
});

test('the quotation report compares the period with the one before it', function () {
    CarbonImmutable::setTestNow('2026-09-24');

    $quote = fn (string $date, string $status, float $total) => Quotation::factory()->create([
        'branch_id' => $this->branch->id,
        'created_by' => $this->admin->id,
        'quotation_date' => $date,
        'status' => $status,
        'total' => $total,
    ]);

    // This month, to date.
    $quote('2026-09-05', 'accepted', 1000);
    $quote('2026-09-10', 'rejected', 500);
    $quote('2026-09-12', 'sent', 250);
    // A superseded revision is never counted.
    $quote('2026-09-12', 'revised', 9999);
    // The 24 days before it.
    $quote('2026-08-20', 'accepted', 400);
    // Outside both.
    $quote('2026-06-01', 'accepted', 7000);

    $this->actingAs($this->admin)
        ->get(route('analytics.show', ['section' => 'quotations', 'period' => 'this_month']))
        ->assertInertia(fn ($page) => $page
            ->where('period.from', '2026-09-01')
            ->where('period.to', '2026-09-24')
            ->where('previousPeriod.from', '2026-08-08')
            ->where('previousPeriod.to', '2026-08-31')
            ->where('report.stats.0.label', 'Quotations')
            ->where('report.stats.0.value', 3)
            ->where('report.stats.0.previous', 1)
            ->where('report.stats.1.value', fn ($value) => (float) $value === 1750.0)
            ->where('report.stats.2.value', fn ($value) => (float) $value === 1000.0)
            ->where('report.stats.3.label', 'Win rate')
            ->where('report.stats.3.value', fn ($value) => (float) $value === 50.0)
            ->where('report.charts.0.type', 'trend')
            ->has('report.charts.0.data', 24)
            ->where('report.tables.1.rows.0.total', fn ($value) => (float) $value === 1000.0));
});

test('a custom range is honoured and a back to front one falls back to the default', function () {
    CarbonImmutable::setTestNow('2026-09-24');

    VisitReport::factory()->create(['branch_id' => $this->branch->id, 'user_id' => $this->admin->id, 'visit_date' => '2026-03-15']);
    VisitReport::factory()->create(['branch_id' => $this->branch->id, 'user_id' => $this->admin->id, 'visit_date' => '2026-09-20']);

    $this->actingAs($this->admin)
        ->get(route('analytics.show', ['section' => 'visit-reports', 'period' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-31']))
        ->assertInertia(fn ($page) => $page
            ->where('period.key', 'custom')
            ->where('period.label', '01 Mar 2026 – 31 Mar 2026')
            ->where('report.stats.0.value', 1));

    $this->actingAs($this->admin)
        ->get(route('analytics.show', ['section' => 'visit-reports', 'period' => 'custom', 'from' => '2026-03-31', 'to' => '2026-03-01']))
        ->assertInertia(fn ($page) => $page
            ->where('period.key', '30d')
            ->where('report.stats.0.value', 1));
});

test('long periods are drawn by week and by month', function () {
    CarbonImmutable::setTestNow('2026-09-24');

    expect(AnalyticsPeriod::preset('30d')->granularity())->toBe('day');
    expect(AnalyticsPeriod::preset('90d')->granularity())->toBe('week');
    expect(AnalyticsPeriod::preset('this_year')->granularity())->toBe('month');
    expect(AnalyticsPeriod::preset('this_year')->buckets())->toHaveCount(9);
    expect(AnalyticsPeriod::preset('last_month')->toArray())
        ->toMatchArray(['from' => '2026-08-01', 'to' => '2026-08-31']);
});

test('the team leaderboard credits each person with their own work', function () {
    $ravi = User::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Ravi']);

    VisitReport::factory()->count(3)->create(['branch_id' => $this->branch->id, 'user_id' => $ravi->id, 'visit_date' => now()->toDateString()]);
    Enquiry::factory()->create(['branch_id' => $this->branch->id, 'assigned_to' => $ravi->id, 'status' => 'converted']);
    Quotation::factory()->create(['branch_id' => $this->branch->id, 'created_by' => $ravi->id, 'status' => 'accepted', 'total' => 5000, 'quotation_date' => now()->toDateString()]);
    Quotation::factory()->create(['branch_id' => $this->branch->id, 'created_by' => $this->admin->id, 'status' => 'rejected', 'total' => 800, 'quotation_date' => now()->toDateString()]);

    $this->actingAs($this->admin)
        ->get(route('analytics.show', 'team'))
        ->assertInertia(fn ($page) => $page
            ->has('report.tables.0.rows', 2)
            ->where('report.tables.0.rows.0.name', 'Ravi')
            ->where('report.tables.0.rows.0.visits', 3)
            ->where('report.tables.0.rows.0.converted', 1)
            ->where('report.tables.0.rows.0.won', fn ($value) => (float) $value === 5000.0)
            ->where('report.tables.0.rows.0.win_rate', fn ($value) => (float) $value === 100.0)
            ->where('report.tables.0.rows.1.name', 'Asha')
            ->where('report.tables.0.rows.1.win_rate', fn ($value) => (float) $value === 0.0));
});

test('products and brands are ranked by the value quoted for them', function () {
    $brand = Brand::factory()->create(['name' => 'Supreme']);
    $product = Product::factory()->create(['name' => 'Ball Valve 25mm', 'brand_id' => $brand->id]);
    $quotation = Quotation::factory()->create(['branch_id' => $this->branch->id, 'status' => 'accepted', 'quotation_date' => now()->toDateString()]);
    QuotationItem::factory()->create(['quotation_id' => $quotation->id, 'product_id' => $product->id, 'quantity' => 4, 'unit_price' => 250]);

    $this->actingAs($this->admin)
        ->get(route('analytics.show', 'products'))
        ->assertInertia(fn ($page) => $page
            ->where('report.charts.0.data.0.label', 'Supreme')
            ->where('report.charts.0.data.0.value', fn ($value) => (float) $value === 1000.0)
            ->where('report.tables.0.rows.0.name', 'Ball Valve 25mm')
            ->where('report.tables.0.rows.0.won', fn ($value) => (float) $value === 1000.0));
});

test('routes and locations show the visits made on them', function () {
    $route = Route::factory()->create(['name' => 'Kochi North']);
    VisitReport::factory()->count(2)->create(['branch_id' => $this->branch->id, 'route_id' => $route->id, 'visit_date' => now()->toDateString()]);
    VisitReport::factory()->create(['branch_id' => $this->branch->id, 'route_id' => null, 'visit_date' => now()->toDateString()]);

    $this->actingAs($this->admin)
        ->get(route('analytics.show', 'routes-locations'))
        ->assertInertia(fn ($page) => $page
            ->where('report.stats.0.value', 1)
            ->where('report.charts.0.data.0.label', 'Kochi North')
            ->where('report.charts.0.data.0.value', 2)
            ->where('report.tables.0.rows.0.name', 'Kochi North'));
});

test('the old analytics addresses lead to the new pages', function (string $old, string $section) {
    $this->actingAs($this->admin)
        ->get($old)
        ->assertRedirect('/analytics/'.$section);
})->with([
    ['/contacts/analytics', 'contacts'],
    ['/quotations/analytics', 'quotations'],
    ['/visit-reports/analytics', 'visit-reports'],
    ['/projects/analytics', 'projects'],
]);
