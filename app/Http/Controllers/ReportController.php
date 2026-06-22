<?php

namespace App\Http\Controllers;

use App\Models\Enquiry;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\User;
use App\Models\VisitReport;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Display the reports dashboard.
     */
    public function index(): Response
    {
        return Inertia::render('reports/Index', [
            'stats' => $this->headlineStats(),
            'mostEnquiredProducts' => $this->mostEnquiredProducts(),
            'enquiriesByStatus' => $this->enquiriesByStatus(),
            'quotationsByStatus' => $this->quotationsByStatus(),
            'topBuilders' => $this->topBuilders(),
            'staffPerformance' => $this->staffPerformance(),
        ]);
    }

    /**
     * @return array{enquiries: int, converted: int, quotations: int, quotedValue: float}
     */
    private function headlineStats(): array
    {
        return [
            'enquiries' => (int) Enquiry::query()->count(),
            'converted' => (int) Enquiry::query()->where('status', 'converted')->count(),
            'quotations' => (int) Quotation::query()->count(),
            'quotedValue' => (float) Quotation::query()->sum('total'),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, count: int}>
     */
    private function mostEnquiredProducts(): array
    {
        return Enquiry::query()
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
    private function enquiriesByStatus(): array
    {
        $counts = Enquiry::query()
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
    private function quotationsByStatus(): array
    {
        $rows = Quotation::query()
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
    private function topBuilders(): array
    {
        return Project::query()
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
    private function staffPerformance(): array
    {
        $enquiries = Enquiry::query()
            ->whereNotNull('assigned_to')
            ->selectRaw('assigned_to, count(*) as aggregate')
            ->groupBy('assigned_to')
            ->pluck('aggregate', 'assigned_to');

        $visits = VisitReport::query()
            ->selectRaw('user_id, count(*) as aggregate')
            ->groupBy('user_id')
            ->pluck('aggregate', 'user_id');

        $projects = Project::query()
            ->whereNotNull('created_by')
            ->selectRaw('created_by, count(*) as aggregate')
            ->groupBy('created_by')
            ->pluck('aggregate', 'created_by');

        $quotations = Quotation::query()
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
