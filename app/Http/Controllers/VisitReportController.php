<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveVisitReportRequest;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Models\VisitReport;
use App\Support\BranchAccess;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class VisitReportController extends Controller
{
    /**
     * Display a listing of visit reports.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $visitType = $request->input('visit_type');
        $userId = $request->input('user_id');
        $projectId = $request->input('project_id');
        $createdFrom = $request->input('created_from');
        $createdTo = $request->input('created_to');

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
                ->when($createdFrom, fn ($query) => $query->whereDate('created_at', '>=', $createdFrom))
                ->when($createdTo, fn ($query) => $query->whereDate('created_at', '<=', $createdTo))
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
                'created_from' => $createdFrom,
                'created_to' => $createdTo,
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
                    ->where('next_meeting_date', '>=', now()->toDateString())
                    ->count(),
            ],
            'visitReportsByType' => $this->visitReportsByType(),
            'visitReportsByMonth' => $this->visitReportsByMonth(),
        ]);
    }

    /**
     * Show the form for creating a new visit report.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('visit-reports/Create', [
            'projects' => Project::query()->orderBy('name')->get(['id', 'name']),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'contacts' => Contact::query()->orderBy('name')->get(['id', 'name']),
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

        return Inertia::render('visit-reports/Show', [
            'visitReport' => $visitReport,
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
            'contacts' => Contact::query()->orderBy('name')->get(['id', 'name']),
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
