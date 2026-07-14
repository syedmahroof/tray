<?php

namespace App\Http\Controllers;

use App\Exports\GenericSheetExport;
use App\Http\Requests\SaveVisitReportRequest;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Models\VisitReport;
use App\Support\BranchAccess;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VisitReportController extends Controller
{
    /**
     * Apply date filter to query.
     */
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

    /**
     * Display a listing of visit reports.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $visitType = $request->input('visit_type');
        $userId = $request->input('user_id');
        $projectId = $request->input('project_id');
        $dateFilter = $request->input('date_filter', 'all');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $upcomingFollowUp = $request->boolean('upcoming_followup');

        return Inertia::render('visit-reports/Index', [
            'visitReports' => VisitReport::query()
                ->with(['user', 'projects', 'customers', 'contacts'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('visit_type', 'like', "%{$search}%")
                            ->orWhere('objective', 'like', "%{$search}%")
                            ->orWhere('report', 'like', "%{$search}%")
                            ->orWhereHas('projects', fn ($sub) => $sub->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('customers', fn ($sub) => $sub->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('contacts', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
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
                ->paginate(15)
                ->withQueryString(),
            'visitReportsByType' => $this->visitReportsByType(),
            'visitTypes' => VisitReport::VISIT_TYPES,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'visit_type' => $visitType,
                'user_id' => $userId,
                'project_id' => $projectId,
                'date_filter' => $dateFilter,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'upcoming_followup' => $upcomingFollowUp,
            ],
        ]);
    }

    /**
     * Display analytics for visit reports.
     */
    public function analytics(): Response
    {
        return Inertia::render('visit-reports/Analytics', [
            'stats' => [
                'total' => VisitReport::query()->count(),
                'thisMonth' => VisitReport::query()
                    ->whereMonth('visit_date', now()->month)
                    ->whereYear('visit_date', now()->year)
                    ->count(),
                'upcomingFollowUps' => VisitReport::query()
                    ->where(function ($q) {
                        $q->where('next_meeting_date', '>=', now()->toDateString())
                            ->orWhere('next_call_date', '>=', now()->toDateString());
                    })
                    ->count(),
            ],
            'visitReportsByType' => $this->visitReportsByType(),
            'visitReportsByMonth' => $this->visitReportsByMonth(),
            'mostVisitedCustomers' => $this->mostVisitedCustomers(),
            'leastVisitedCustomers' => $this->leastVisitedCustomers(),
            'mostVisitedProjects' => $this->mostVisitedProjects(),
            'leastVisitedProjects' => $this->leastVisitedProjects(),
        ]);
    }

    private function mostVisitedCustomers(): array
    {
        return DB::table('visit_report_customer')
            ->join('customers', 'visit_report_customer.customer_id', '=', 'customers.id')
            ->selectRaw('customers.id, customers.name, count(*) as count')
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->all();
    }

    private function leastVisitedCustomers(): array
    {
        return DB::table('visit_report_customer')
            ->join('customers', 'visit_report_customer.customer_id', '=', 'customers.id')
            ->selectRaw('customers.id, customers.name, count(*) as count')
            ->groupBy('customers.id', 'customers.name')
            ->orderBy('count')
            ->limit(5)
            ->get()
            ->all();
    }

    private function mostVisitedProjects(): array
    {
        return DB::table('visit_report_project')
            ->join('projects', 'visit_report_project.project_id', '=', 'projects.id')
            ->selectRaw('projects.id, projects.name, count(*) as count')
            ->groupBy('projects.id', 'projects.name')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->all();
    }

    private function leastVisitedProjects(): array
    {
        return DB::table('visit_report_project')
            ->join('projects', 'visit_report_project.project_id', '=', 'projects.id')
            ->selectRaw('projects.id, projects.name, count(*) as count')
            ->groupBy('projects.id', 'projects.name')
            ->orderBy('count')
            ->limit(5)
            ->get()
            ->all();
    }

    /**
     * Export the filtered visit reports to an Excel spreadsheet.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $search = trim((string) $request->input('search', ''));
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
            ->when($request->input('visit_type'), fn ($query, $value) => $query->where('visit_type', $value))
            ->when($request->input('user_id'), fn ($query, $value) => $query->where('user_id', $value))
            ->when($request->input('project_id'), fn ($query, $value) => $query->whereHas('projects', fn ($q) => $q->where('projects.id', $value)))
            ->when($upcomingFollowUp, fn ($query) => $query->where(function ($q) {
                $q->whereDate('next_meeting_date', '>=', now())
                    ->orWhereDate('next_call_date', '>=', now());
            }))
            ->pipe(fn ($q) => $this->applyDateFilter($q, $dateFilter, $startDate, $endDate, 'visit_date'))
            ->orderByDesc('visit_date')
            ->get();

        $rows = $visitReports->map(fn (VisitReport $report): array => [
            $report->visit_date?->format('Y-m-d'),
            $report->visit_type,
            $report->objective,
            $report->projects->merge($report->customers)->merge($report->contacts)->pluck('name')->join(', '),
            $report->user->name,
            $report->next_meeting_date?->format('Y-m-d'),
            $report->created_at?->format('Y-m-d'),
        ])->all();

        return Excel::download(
            new GenericSheetExport(
                ['Visit Date', 'Type', 'Objective', 'Linked To', 'Reported By', 'Next Meeting', 'Created At'],
                $rows,
            ),
            'visit-reports.xlsx',
        );
    }

    /**
     * Show the form for creating a new visit report.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('visit-reports/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'contacts' => Contact::query()->with('contactType:id,name')->orderBy('name')->get(['id', 'name', 'contact_type_id']),
            'visitTypes' => VisitReport::VISIT_TYPES,
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
            'preselectedProjectId' => $request->integer('project_id') ?: null,
            'preselectedCustomerId' => $request->integer('customer_id') ?: null,
            'preselectedContactId' => $request->integer('contact_id') ?: null,
        ]);
    }

    /**
     * Store a newly created visit report.
     */
    public function store(SaveVisitReportRequest $request): RedirectResponse
    {
        $visitReport = VisitReport::create([
            ...$request->safe()->except(['project_ids', 'customer_ids', 'contact_ids']),
            'user_id' => $request->user()->id,
        ]);

        $visitReport->projects()->attach($request->validated('project_ids', []));
        $visitReport->customers()->attach($request->validated('customer_ids', []));
        $visitReport->contacts()->attach($request->validated('contact_ids', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Visit report created.')]);

        return to_route('visit-reports.show', $visitReport);
    }

    /**
     * Display the given visit report.
     */
    public function show(VisitReport $visitReport): Response
    {
        $visitReport->load(['user', 'branch', 'projects', 'customers', 'contacts']);

        $contactIds = $visitReport->contacts->pluck('id');
        $customerIds = $visitReport->customers->pluck('id');
        $projectIds = $visitReport->projects->pluck('id');

        $history = VisitReport::query()
            ->where('id', '!=', $visitReport->id)
            ->where(function ($query) use ($contactIds, $customerIds, $projectIds) {
                $query->whereHas('contacts', fn ($sub) => $sub->whereIn('contacts.id', $contactIds))
                    ->orWhereHas('customers', fn ($sub) => $sub->whereIn('customers.id', $customerIds))
                    ->orWhereHas('projects', fn ($sub) => $sub->whereIn('projects.id', $projectIds));
            })
            ->with(['user:id,name', 'contacts:id,name', 'customers:id,name', 'projects:id,name'])
            ->orderByDesc('visit_date')
            ->limit(50)
            ->get(['id', 'visit_date', 'visit_type', 'objective', 'user_id']);

        return Inertia::render('visit-reports/Show', [
            'visitReport' => $visitReport,
            'history' => $history,
            'auditLogs' => $visitReport->auditLogs()->with('user:id,name')->latest()->get(),
        ]);
    }

    /**
     * Show the form for editing the given visit report.
     */
    public function edit(VisitReport $visitReport): Response
    {
        $visitReport->load(['projects', 'customers', 'contacts']);

        return Inertia::render('visit-reports/Edit', [
            'visitReport' => $visitReport,
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'contacts' => Contact::query()->with('contactType:id,name')->orderBy('name')->get(['id', 'name', 'contact_type_id']),
            'visitTypes' => VisitReport::VISIT_TYPES,
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
        ]);
    }

    /**
     * Update the given visit report.
     */
    public function update(SaveVisitReportRequest $request, VisitReport $visitReport): RedirectResponse
    {
        $visitReport->update($request->safe()->except(['project_ids', 'customer_ids', 'contact_ids']));

        $visitReport->projects()->sync($request->validated('project_ids', []));
        $visitReport->customers()->sync($request->validated('customer_ids', []));
        $visitReport->contacts()->sync($request->validated('contact_ids', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Visit report updated.')]);

        return to_route('visit-reports.show', $visitReport);
    }

    /**
     * Remove the given visit report.
     */
    public function destroy(VisitReport $visitReport): RedirectResponse
    {
        $visitReport->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Visit report deleted.')]);

        return to_route('visit-reports.index');
    }

    /**
     * @return array<int, array{type: string, count: int}>
     */
    private function visitReportsByType(): array
    {
        $counts = VisitReport::query()
            ->selectRaw('visit_type, count(*) as aggregate')
            ->groupBy('visit_type')
            ->pluck('aggregate', 'visit_type');

        return array_map(
            fn (string $type): array => ['type' => $type, 'count' => $counts->get($type, 0)],
            VisitReport::VISIT_TYPES,
        );
    }

    /**
     * @return array<int, array{month: string, count: int}>
     */
    private function visitReportsByMonth(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(5);

        /** @var array<string, int> $counts */
        $counts = VisitReport::query()
            ->where('visit_date', '>=', $start)
            ->pluck('visit_date')
            ->map(fn (CarbonInterface $visitDate): string => $visitDate->format('Y-m'))
            ->countBy()
            ->all();

        return collect(range(0, 5))
            ->map(function (int $offset) use ($start, $counts): array {
                $month = $start->copy()->addMonths($offset);

                return [
                    'month' => $month->format('M'),
                    'count' => $counts[$month->format('Y-m')] ?? 0,
                ];
            })
            ->all();
    }
}
