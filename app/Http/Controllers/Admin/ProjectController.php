<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GenericSheetExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProjectRequest;
use App\Models\Builder;
use App\Models\Contact;
use App\Models\Country;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use App\Models\VisitReport;
use App\Support\BranchAccess;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectController extends Controller
{
    /**
     * Display a listing of projects.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $builderId = $request->input('builder_id');
        $categoryId = $request->input('project_category_id');
        $status = $request->input('status');
        $productId = $request->input('product_id');
        $createdBy = $request->input('created_by');
        $createdFrom = $request->input('created_from');
        $createdTo = $request->input('created_to');

        $statusCounts = Project::query()
            ->groupBy('status')
            ->selectRaw('status, count(*) as total')
            ->pluck('total', 'status');

        return Inertia::render('admin/projects/Index', [
            'stats' => [
                'total' => (int) $statusCounts->sum(),
                'planning' => (int) $statusCounts->get('planning', 0),
                'ongoing' => (int) $statusCounts->get('ongoing', 0),
                'completed' => (int) $statusCounts->get('completed', 0),
            ],
            'projects' => $this->filteredQuery($request)
                ->with(['builder', 'projectCategory', 'creator'])
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'builders' => Builder::query()->orderBy('name')->get(['id', 'name']),
            'projectCategories' => ProjectCategory::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => Project::STATUSES,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'builder_id' => $builderId,
                'project_category_id' => $categoryId,
                'status' => $status,
                'product_id' => $productId,
                'created_by' => $createdBy,
                'created_from' => $createdFrom,
                'created_to' => $createdTo,
                'no_visit_within' => $request->input('no_visit_within'),
            ],
        ]);
    }

    /**
     * Export the filtered projects to an Excel spreadsheet.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $projects = $this->filteredQuery($request)
            ->with(['builder', 'projectCategory', 'creator'])
            ->orderBy('name')
            ->get();

        $rows = $projects->map(fn (Project $project): array => [
            $project->name,
            $project->builder?->name,
            $project->projectCategory->name,
            ucfirst($project->status),
            $project->owner_name,
            $project->location,
            $project->creator?->name,
            $project->created_at?->format('Y-m-d'),
        ])->all();

        return Excel::download(
            new GenericSheetExport(
                ['Name', 'Builder', 'Category', 'Status', 'Owner', 'Location', 'Created By', 'Created At'],
                $rows,
            ),
            'projects.xlsx',
        );
    }

    /**
     * Build the filtered project query shared by the index and export.
     *
     * @return EloquentBuilder<Project>
     */
    private function filteredQuery(Request $request): EloquentBuilder
    {
        $search = trim((string) $request->input('search', ''));

        return Project::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('owner_name', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhereHas('builder', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->input('builder_id'), fn ($query, $value) => $query->where('builder_id', $value))
            ->when($request->input('project_category_id'), fn ($query, $value) => $query->where('project_category_id', $value))
            ->when($request->input('status'), fn ($query, $value) => $query->where('status', $value))
            ->when($request->input('product_id'), fn ($query, $value) => $query->whereHas('products', fn ($q) => $q->where('products.id', $value)))
            ->when($request->input('created_by'), fn ($query, $value) => $query->where('created_by', $value))
            ->when($request->input('created_from'), fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($request->input('created_to'), fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->when(VisitReport::NO_VISIT_PERIODS[$request->input('no_visit_within')] ?? null, function ($query, $days) {
                $query->whereDoesntHave('visitReports', fn ($sub) => $sub->where('visit_date', '>=', now()->subDays($days)->toDateString()));
            });
    }

    /**
     * Display project analytics.
     */
    public function analytics(Request $request): Response
    {
        $range = $request->input('range', '30d');
        $from = $request->input('from');
        $to = $request->input('to');

        [$startDate, $endDate] = $this->resolveDateRange($range, $from, $to);

        $statusCounts = Project::query()
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->copy()->endOfDay()])
            ->groupBy('status')
            ->selectRaw('status, count(*) as total')
            ->pluck('total', 'status');

        return Inertia::render('admin/projects/Analytics', [
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'stats' => [
                'total' => (int) Project::query()->count(),
                'inRange' => (int) Project::query()->whereBetween('created_at', [$startDate->copy()->startOfDay(), $endDate->copy()->endOfDay()])->count(),
                'planning' => (int) $statusCounts->get('planning', 0),
                'ongoing' => (int) $statusCounts->get('ongoing', 0),
                'completed' => (int) $statusCounts->get('completed', 0),
            ],
            'projectsByStatus' => $this->projectsByStatus(),
            'projectsByCategory' => $this->projectsByCategory(),
            'projectsByBuilder' => $this->projectsByBuilder(),
            'projectsByStaff' => $this->projectsByStaff(),
            'projectTrends' => $this->projectTrends($startDate, $endDate),
        ]);
    }

    /**
     * @return array{Carbon, Carbon}
     */
    private function resolveDateRange(string $range, ?string $from, ?string $to): array
    {
        return match ($range) {
            '7d' => [Carbon::now()->subDays(6)->startOfDay(), Carbon::now()->endOfDay()],
            'this_month' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
            '3m' => [Carbon::now()->subMonths(3)->startOfDay(), Carbon::now()->endOfDay()],
            '6m' => [Carbon::now()->subMonths(6)->startOfDay(), Carbon::now()->endOfDay()],
            '1y' => [Carbon::now()->subYear()->startOfDay(), Carbon::now()->endOfDay()],
            'custom' => [
                $from ? Carbon::parse($from)->startOfDay() : Carbon::now()->subMonth()->startOfDay(),
                $to ? Carbon::parse($to)->endOfDay() : Carbon::now()->endOfDay(),
            ],
            default => [Carbon::now()->subDays(29)->startOfDay(), Carbon::now()->endOfDay()], // 30d
        };
    }

    /**
     * @return array<int, array{status: string, count: int}>
     */
    private function projectsByStatus(): array
    {
        $counts = Project::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return array_map(
            fn (string $status): array => ['status' => $status, 'count' => (int) $counts->get($status, 0)],
            Project::STATUSES,
        );
    }

    /**
     * @return array<int, array{category: string, count: int}>
     */
    private function projectsByCategory(): array
    {
        return Project::query()
            ->join('project_categories', 'projects.project_category_id', '=', 'project_categories.id')
            ->selectRaw('project_categories.name as category, count(*) as count')
            ->groupBy('project_categories.name')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => ['category' => (string) $row->category, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * @return array<int, array{builder: string, count: int}>
     */
    private function projectsByBuilder(): array
    {
        return Project::query()
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
     * @return array<int, array{staff: string, count: int}>
     */
    private function projectsByStaff(): array
    {
        return Project::query()
            ->join('users', 'projects.created_by', '=', 'users.id')
            ->selectRaw('users.name as staff, count(*) as count')
            ->groupBy('users.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => ['staff' => (string) $row->staff, 'count' => (int) $row->count])
            ->all();
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function projectTrends(Carbon $start, Carbon $end): array
    {
        $diffDays = (int) $start->diffInDays($end);

        // Choose appropriate grouping granularity
        if ($diffDays <= 31) {
            $format = 'd M';
            $dbFormat = '%d %b';
            $periods = collect(range(0, $diffDays))->map(fn (int $i) => $start->copy()->addDays($i)->format($format));
        } elseif ($diffDays <= 92) {
            $format = 'W/Y';
            $dbFormat = '%u/%Y';
            $weeks = (int) ceil($diffDays / 7);
            $periods = collect(range(0, $weeks))->map(fn (int $i) => $start->copy()->addWeeks($i)->format($format));
        } else {
            $format = 'M Y';
            $dbFormat = '%b %Y';
            $months = (int) ceil($diffDays / 30);
            $periods = collect(range(0, $months))->map(fn (int $i) => $start->copy()->addMonths($i)->format($format))->unique()->values();
        }

        /** @var array<string, int> $counts */
        $counts = Project::query()
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->pluck('created_at')
            ->map(fn (CarbonInterface $date): string => $date->format($format))
            ->countBy()
            ->all();

        return $periods->map(fn (string $label): array => [
            'label' => $label,
            'count' => $counts[$label] ?? 0,
        ])->values()->all();
    }

    /**
     * Display the given project.
     */
    public function show(Project $project): Response
    {
        $project->load([
            'builder',
            'projectCategory',
            'country',
            'state',
            'district',
            'assignee',
            'creator',
            'contacts.contactType',
            'projectContacts',
            'products.productCategory',
            'visitReports.user',
            'branch',
        ]);

        return Inertia::render('admin/projects/Show', [
            'project' => $project,
            'quotations' => $project->quotations()->latest()
                ->get(['id', 'number', 'version', 'status', 'total', 'quotation_date', 'created_at']),
        ]);
    }

    /**
     * Show the form for creating a new project.
     */
    public function create(): Response
    {
        return Inertia::render('admin/projects/Create', [
            'builders' => Builder::query()->orderBy('name')->get(['id', 'name']),
            'projectCategories' => ProjectCategory::query()->orderBy('name')->get(['id', 'name']),
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => Project::STATUSES,
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'contacts' => Contact::query()->with('contactType')->orderBy('name')->get(['id', 'name', 'contact_type_id']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created project.
     */
    public function store(SaveProjectRequest $request): RedirectResponse
    {
        $project = Project::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        if ($request->has('contacts')) {
            $project->contacts()->sync($request->input('contacts', []));
        }

        $project->products()->sync($request->validated('product_ids', []));

        $project->projectContacts()->createMany($request->validated('project_contacts', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.index');
    }

    /**
     * Show the form for editing the given project.
     */
    public function edit(Project $project): Response
    {
        $project->load(['contacts', 'projectContacts', 'assignee', 'products']);

        return Inertia::render('admin/projects/Edit', [
            'project' => $project,
            'builders' => Builder::query()->orderBy('name')->get(['id', 'name']),
            'projectCategories' => ProjectCategory::query()->orderBy('name')->get(['id', 'name']),
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => Project::STATUSES,
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'contacts' => Contact::query()->with('contactType')->orderBy('name')->get(['id', 'name', 'contact_type_id']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update the given project.
     */
    public function update(SaveProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        if ($request->has('contacts')) {
            $project->contacts()->sync($request->input('contacts', []));
        }

        $project->products()->sync($request->validated('product_ids', []));

        $project->projectContacts()->delete();
        $project->projectContacts()->createMany($request->validated('project_contacts', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return to_route('projects.index');
    }

    /**
     * Remove the given project.
     */
    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return to_route('projects.index');
    }
}
