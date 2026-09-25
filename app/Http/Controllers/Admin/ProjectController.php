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
use App\Models\Route;
use App\Models\User;
use App\Models\VisitReport;
use App\Support\BranchAccess;
use App\Support\RouteLocationFilter;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $assigneeId = $request->input('assignee_id');
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
                ->with(['builder', 'projectCategory', 'assignee', 'creator'])
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'builders' => Builder::query()->orderBy('name')->get(['id', 'name']),
            'projectCategories' => ProjectCategory::query()->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => Project::STATUSES,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            ...RouteLocationFilter::options('projects'),
            'filters' => [
                'search' => $search,
                'builder_id' => $builderId,
                ...RouteLocationFilter::filters($request),
                'project_category_id' => $categoryId,
                'status' => $status,
                'product_id' => $productId,
                'assignee_id' => $assigneeId,
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
            ->with(['builder', 'projectCategory', 'assignee', 'creator'])
            ->orderBy('name')
            ->get();

        $rows = $projects->map(fn (Project $project): array => [
            $project->name,
            $project->builder?->name,
            $project->projectCategory->name,
            ucfirst($project->status),
            $project->owner_name,
            $project->location,
            $project->assignee?->name,
            $project->creator?->name,
            $project->created_at?->format('Y-m-d'),
        ])->all();

        return Excel::download(
            new GenericSheetExport(
                ['Name', 'Builder', 'Category', 'Status', 'Owner', 'Location', 'Assigned To', 'Created By', 'Created At'],
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
            ->pipe(fn ($query) => RouteLocationFilter::apply($query, $request))
            ->when($request->input('project_category_id'), fn ($query, $value) => $query->where('project_category_id', $value))
            ->when($request->input('status'), fn ($query, $value) => $query->where('status', $value))
            ->when($request->input('product_id'), fn ($query, $value) => $query->whereHas('products', fn ($q) => $q->where('products.id', $value)))
            ->when($request->input('assignee_id'), fn ($query, $value) => $query->where('assignee_id', $value))
            ->when($request->input('created_by'), fn ($query, $value) => $query->where('created_by', $value))
            ->when($request->input('created_from'), fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($request->input('created_to'), fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->when(VisitReport::NO_VISIT_PERIODS[$request->input('no_visit_within')] ?? null, function ($query, $days) {
                $query->whereDoesntHave('visitReports', fn ($sub) => $sub->where('visit_date', '>=', now()->subDays($days)->toDateString()));
            });
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
            'countries' => Country::query()->orderBy('name')->get(['id', 'name', 'code']),
            'routes' => Route::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
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
            'countries' => Country::query()->orderBy('name')->get(['id', 'name', 'code']),
            'routes' => Route::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
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
