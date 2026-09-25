<?php

namespace App\Http\Controllers;

use App\Support\Analytics\AnalyticsPeriod;
use App\Support\Analytics\AnalyticsSection;
use App\Support\Analytics\BuilderAnalytics;
use App\Support\Analytics\ContactAnalytics;
use App\Support\Analytics\CustomerAnalytics;
use App\Support\Analytics\EnquiryAnalytics;
use App\Support\Analytics\ProductAnalytics;
use App\Support\Analytics\ProjectAnalytics;
use App\Support\Analytics\QuotationAnalytics;
use App\Support\Analytics\RouteLocationAnalytics;
use App\Support\Analytics\TeamAnalytics;
use App\Support\Analytics\VisitReportAnalytics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Analytics menu: one page per section, each reporting on the period
 * picked from the presets or a custom range.
 */
class AnalyticsController extends Controller
{
    /**
     * Every section, in menu order, with the class that works out its report
     * and the permission needed to see it.
     *
     * @var array<string, array{0: class-string<AnalyticsSection>, 1: string}>
     */
    public const array SECTIONS = [
        'quotations' => [QuotationAnalytics::class, 'quotations.view'],
        'enquiries' => [EnquiryAnalytics::class, 'enquiries.view'],
        'visit-reports' => [VisitReportAnalytics::class, 'visit-reports.view'],
        'team' => [TeamAnalytics::class, 'reports.view'],
        'customers' => [CustomerAnalytics::class, 'customers.view'],
        'contacts' => [ContactAnalytics::class, 'contacts.view'],
        'projects' => [ProjectAnalytics::class, 'projects.view'],
        'builders' => [BuilderAnalytics::class, 'builders.view'],
        'products' => [ProductAnalytics::class, 'reports.view'],
        'routes-locations' => [RouteLocationAnalytics::class, 'reports.view'],
    ];

    /**
     * Open the first section the user is allowed to see.
     */
    public function index(Request $request): RedirectResponse
    {
        $first = self::visibleSections($request)[0] ?? abort(403);

        return to_route('analytics.show', $first['key']);
    }

    /**
     * The sections the user may open, for the switcher across the top.
     *
     * @return list<array{key: string, title: string}>
     */
    public static function visibleSections(Request $request): array
    {
        $sections = [];

        foreach (self::SECTIONS as $key => [$class, $permission]) {
            if ($request->user()?->can($permission)) {
                $sections[] = ['key' => $key, 'title' => app($class)->title()];
            }
        }

        return $sections;
    }

    /**
     * Show one section's report for the requested period.
     */
    public function show(Request $request, string $section): Response
    {
        [$class, $permission] = self::SECTIONS[$section] ?? abort(404);

        abort_unless($request->user()?->can($permission), 403);

        $period = AnalyticsPeriod::fromRequest($request);
        $analytics = app($class);

        return Inertia::render('analytics/Show', [
            'section' => [
                'key' => $section,
                'title' => $analytics->title(),
                'description' => $analytics->description(),
            ],
            'sections' => self::visibleSections($request),
            'period' => $period->toArray(),
            'previousPeriod' => $period->previous()->toArray(),
            'periods' => AnalyticsPeriod::options(),
            'report' => $analytics->report($period)->toArray(),
        ]);
    }
}
