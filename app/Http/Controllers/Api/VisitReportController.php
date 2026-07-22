<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VisitReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class VisitReportController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $visitType = $request->input('visit_type');
        $userId = $request->input('user_id');
        $projectId = $request->input('project_id');
        $dateFilter = $request->input('date_filter', 'all');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $upcomingFollowUp = $request->boolean('upcoming_followup');

        $visitReports = VisitReport::query()
            ->with(['user', 'projects', 'customers', 'contacts'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('visit_type', 'like', "%{$search}%")
                        ->orWhere('objective', 'like', "%{$search}%")
                        ->orWhere('report', 'like', "%{$search}%");
                });
            })
            ->when($visitType, fn ($query) => $query->where('visit_type', $visitType))
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($projectId, fn ($query) => $query->whereHas('projects', fn ($q) => $q->where('projects.id', $projectId)))
            ->when($upcomingFollowUp, fn ($query) => $query->where(function ($q) {
                $q->whereDate('next_meeting_date', '>=', now())
                    ->orWhereDate('next_call_date', '>=', now());
            }))
            ->pipe(fn ($q) => $this->applyDateFilter($q, $dateFilter, $startDate, $endDate, 'visit_date'))
            ->orderByDesc('visit_date')
            ->paginate(15);

        return response()->json($visitReports);
    }

    private function applyDateFilter(Builder $query, string $dateFilter, ?string $startDate, ?string $endDate, string $column = 'visit_date'): Builder
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

    public function show(VisitReport $visitReport)
    {
        $visitReport->load(['user', 'branch', 'projects', 'customers', 'contacts']);

        return response()->json($visitReport);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'visit_date' => 'required|date',
            'visit_type' => 'required|string|max:100',
            'objective' => 'required|string',
            'report' => 'nullable|string',
            'next_meeting_date' => 'nullable|date',
            'next_call_date' => 'nullable|date',
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'integer|exists:projects,id',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'integer|exists:customers,id',
            'contact_ids' => 'nullable|array',
            'contact_ids.*' => 'integer|exists:contacts,id',
        ]);

        $validated['branch_id'] = $request->user()?->branch_id ?? 1;
        $validated['user_id'] = $request->user()?->id ?? 1;

        $visitReport = VisitReport::create(collect($validated)->except(['project_ids', 'customer_ids', 'contact_ids'])->all());

        $visitReport->projects()->sync($validated['project_ids'] ?? []);
        $visitReport->customers()->sync($validated['customer_ids'] ?? []);
        $visitReport->contacts()->sync($validated['contact_ids'] ?? []);

        return response()->json($visitReport->load(['user', 'projects', 'customers', 'contacts']), 201);
    }

    public function update(Request $request, VisitReport $visitReport)
    {
        $validated = $request->validate([
            'visit_date' => 'required|date',
            'visit_type' => 'required|string|max:100',
            'objective' => 'required|string',
            'report' => 'nullable|string',
            'next_meeting_date' => 'nullable|date',
            'next_call_date' => 'nullable|date',
            'project_ids' => 'nullable|array',
            'project_ids.*' => 'integer|exists:projects,id',
            'customer_ids' => 'nullable|array',
            'customer_ids.*' => 'integer|exists:customers,id',
            'contact_ids' => 'nullable|array',
            'contact_ids.*' => 'integer|exists:contacts,id',
        ]);

        $visitReport->update(collect($validated)->except(['project_ids', 'customer_ids', 'contact_ids'])->all());

        $visitReport->projects()->sync($validated['project_ids'] ?? []);
        $visitReport->customers()->sync($validated['customer_ids'] ?? []);
        $visitReport->contacts()->sync($validated['contact_ids'] ?? []);

        return response()->json($visitReport->load(['user', 'projects', 'customers', 'contacts']));
    }

    public function destroy(VisitReport $visitReport)
    {
        $visitReport->delete();
        return response()->json(['message' => 'Visit Report deleted successfully']);
    }
}
