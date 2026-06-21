<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProjectRequest;
use App\Models\Builder;
use App\Models\Contact;
use App\Models\Country;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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
        $createdBy = $request->input('created_by');
        $createdFrom = $request->input('created_from');
        $createdTo = $request->input('created_to');

        return Inertia::render('admin/projects/Index', [
            'projects' => Project::query()
                ->with(['builder', 'projectCategory', 'creator'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('owner_name', 'like', "%{$search}%")
                            ->orWhere('location', 'like', "%{$search}%")
                            ->orWhereHas('builder', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
                    });
                })
                ->when($builderId, fn ($query) => $query->where('builder_id', $builderId))
                ->when($categoryId, fn ($query) => $query->where('project_category_id', $categoryId))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when($createdBy, fn ($query) => $query->where('created_by', $createdBy))
                ->when($createdFrom, fn ($query) => $query->whereDate('created_at', '>=', $createdFrom))
                ->when($createdTo, fn ($query) => $query->whereDate('created_at', '<=', $createdTo))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'builders' => Builder::query()->orderBy('name')->get(['id', 'name']),
            'projectCategories' => ProjectCategory::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => Project::STATUSES,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'builder_id' => $builderId,
                'project_category_id' => $categoryId,
                'status' => $status,
                'created_by' => $createdBy,
                'created_from' => $createdFrom,
                'created_to' => $createdTo,
            ],
        ]);
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
            'visitReports.user',
            'branch',
        ]);

        return Inertia::render('admin/projects/Show', [
            'project' => $project,
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

        $project->projectContacts()->createMany($request->validated('project_contacts', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project created.')]);

        return to_route('projects.index');
    }

    /**
     * Show the form for editing the given project.
     */
    public function edit(Project $project): Response
    {
        $project->load(['contacts', 'projectContacts', 'assignee']);

        return Inertia::render('admin/projects/Edit', [
            'project' => $project,
            'builders' => Builder::query()->orderBy('name')->get(['id', 'name']),
            'projectCategories' => ProjectCategory::query()->orderBy('name')->get(['id', 'name']),
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => Project::STATUSES,
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'contacts' => Contact::query()->with('contactType')->orderBy('name')->get(['id', 'name', 'contact_type_id']),
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
