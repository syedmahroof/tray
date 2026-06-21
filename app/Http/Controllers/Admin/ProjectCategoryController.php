<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProjectCategoryRequest;
use App\Models\ProjectCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectCategoryController extends Controller
{
    /**
     * Display a listing of project categories.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('admin/project-categories/Index', [
            'projectCategories' => ProjectCategory::query()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Store a newly created project category.
     */
    public function store(SaveProjectCategoryRequest $request): RedirectResponse
    {
        ProjectCategory::create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project category created.')]);

        return to_route('project-categories.index');
    }

    /**
     * Update the given project category.
     */
    public function update(SaveProjectCategoryRequest $request, ProjectCategory $projectCategory): RedirectResponse
    {
        $projectCategory->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project category updated.')]);

        return to_route('project-categories.index');
    }

    /**
     * Remove the given project category.
     */
    public function destroy(ProjectCategory $projectCategory): RedirectResponse
    {
        if ($projectCategory->projects()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a category that still has projects.')]);

            return back();
        }

        $projectCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project category deleted.')]);

        return to_route('project-categories.index');
    }
}
