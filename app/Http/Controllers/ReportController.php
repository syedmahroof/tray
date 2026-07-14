<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Models\VisitReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Display the reports dashboard.
     */
    public function index(Request $request): Response
    {
        $dateFilter = $request->input('date_filter', 'all');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        return Inertia::render('reports/Index', [
            'stats' => $this->headlineStats($dateFilter, $startDate, $endDate),
            'mostEnquiredProducts' => $this->mostEnquiredProducts($dateFilter, $startDate, $endDate),
            'enquiriesByStatus' => $this->enquiriesByStatus($dateFilter, $startDate, $endDate),
            'quotationsByStatus' => $this->quotationsByStatus($dateFilter, $startDate, $endDate),
            'topBuilders' => $this->topBuilders($dateFilter, $startDate, $endDate),
            'staffPerformance' => $this->staffPerformance($dateFilter, $startDate, $endDate),
            'filters' => [
                'date_filter' => $dateFilter,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Apply date filter to query.
     */
    private function applyDateFilter(Builder $query, string $dateFilter, ?string $startDate, ?string $endDate, string $column = 'created_at'): Builder
    {
        return match ($dateFilter) {
            'last_7_days' => $query->where($column, '>=', now()->subDays(7)->startOfDay()),
            'last_30_days' => $query->where($column, '>=', now()->subDays(30)->startOfDay()),
            'this_month' => $query->where($column, '>=', now()->startOfMonth()),
            'last_3_months' => $query->where($column, '>=', now()->subMonths(3)->startOfDay()),
            'last_6_months' => $query->where($column, '>=', now()->subMonths(6)->startOfDay()),
            'last_year' => $query->where($column, '>=', now()->subYear()->startOfDay()),
            'custom' => $query->when($startDate, fn ($q) => $q->where($column, '>=', $startDate))
                ->when($endDate, fn ($q) => $q->where($column, '<=', $endDate)),
            default => $query,
        };
    }

    /**
     * @return array{enquiries: int, converted: int, quotations: int, quotedValue: float}
     */
    private function headlineStats(string $dateFilter, ?string $startDate, ?string $endDate): array
    {
        return [
            'enquiries' => (int) $this->applyDateFilter(Enquiry::query(), $dateFilter, $startDate, $endDate)->count(),
            'converted' => (int) $this->applyDateFilter(Enquiry::query(), $dateFilter, $startDate, $endDate)->where('status', 'converted')->count(),
            'quotations' => (int) $this->applyDateFilter(Quotation::query(), $dateFilter, $startDate, $endDate)->count(),
            'quotedValue' => (float) $this->applyDateFilter(Quotation::query(), $dateFilter, $startDate, $endDate)->sum('total'),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, count: int}>
     */
    private function mostEnquiredProducts(string $dateFilter, ?string $startDate, ?string $endDate): array
    {
        return $this->applyDateFilter(Enquiry::query(), $dateFilter, $startDate, $endDate, 'enquiries.created_at')
            ->whereNotNull('product_id')
            ->join('products', 'enquiries.product_id', '=', 'products.id')
            ->selectRaw('products.id as id, products.name as name, count(*) as count')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{status: string, count: int}>
     */
    private function enquiriesByStatus(string $dateFilter, ?string $startDate, ?string $endDate): array
    {
        $counts = $this->applyDateFilter(Enquiry::query(), $dateFilter, $startDate, $endDate)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return array_map(
            fn (string $status): array => ['status' => $status, 'count' => (int) $counts->get($status, 0)],
            Enquiry::STATUSES,
        );
    }

    /**
     * @return array<int, array{status: string, count: int, value: float}>
     */
    private function quotationsByStatus(string $dateFilter, ?string $startDate, ?string $endDate): array
    {
        $rows = $this->applyDateFilter(Quotation::query(), $dateFilter, $startDate, $endDate)
            ->selectRaw('status, count(*) as aggregate, sum(total) as value')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return array_map(
            fn (string $status): array => [
                'status' => $status,
                'count' => (int) ($rows->get($status)->aggregate ?? 0),
                'value' => (float) ($rows->get($status)->value ?? 0),
            ],
            Quotation::STATUSES,
        );
    }

    /**
     * @return array<int, array{builder: string, count: int}>
     */
    private function topBuilders(string $dateFilter, ?string $startDate, ?string $endDate): array
    {
        return $this->applyDateFilter(Project::query(), $dateFilter, $startDate, $endDate, 'projects.created_at')
            ->whereNotNull('builder_id')
            ->join('builders', 'projects.builder_id', '=', 'builders.id')
            ->selectRaw('builders.name as builder, count(*) as count')
            ->groupBy('builders.name')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => ['builder' => (string) $row->builder, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, enquiries: int, visits: int, projects: int, quotations: int, quotedValue: float}>
     */
    private function staffPerformance(string $dateFilter, ?string $startDate, ?string $endDate): array
    {
        $enquiries = $this->applyDateFilter(Enquiry::query(), $dateFilter, $startDate, $endDate)
            ->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, count(*) as aggregate')
            ->groupBy('assigned_to')
            ->pluck('aggregate', 'assigned_to');

        $visits = $this->applyDateFilter(VisitReport::query(), $dateFilter, $startDate, $endDate, 'visit_date')
            ->selectRaw('user_id, count(*) as aggregate')
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id');

        $projects = $this->applyDateFilter(Project::query(), $dateFilter, $startDate, $endDate)
            ->whereNotNull('created_by')
            ->selectRaw('created_by, count(*) as aggregate')
            ->groupBy('created_by')
            ->pluck('aggregate', 'created_by');

        $quotations = $this->applyDateFilter(Quotation::query(), $dateFilter, $startDate, $endDate)
            ->whereNotNull('created_by')
            ->selectRaw('created_by, count(*) as aggregate, sum(total) as value')
            ->groupBy('created_by')
            ->get()
            ->keyBy('created_by');

        return User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'enquiries' => (int) $enquiries->get($user->id, 0),
                'visits' => (int) $visits->get($user->id, 0),
                'projects' => (int) $projects->get($user->id, 0),
                'quotations' => (int) ($quotations->get($user->id)->aggregate ?? 0),
                'quotedValue' => (float) ($quotations->get($user->id)->value ?? 0),
            ])
            ->filter(fn (array $row): bool => $row['enquiries'] + $row['visits'] + $row['projects'] + $row['quotations'] > 0)
            ->sortByDesc(fn (array $row): int => $row['enquiries'] + $row['visits'] + $row['projects'] + $row['quotations'])
            ->values()
            ->all();
    }
}
