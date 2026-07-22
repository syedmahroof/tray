<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\VisitReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $projects = Project::with(['builder', 'projectCategory', 'country', 'state', 'district', 'assignee'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
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
            })
            ->latest()
            ->paginate(15);

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'project_category_id'=> 'required|exists:project_categories,id',
            'builder_id'         => 'nullable|exists:builders,id',
            'status'             => 'required|in:planning,ongoing,completed',
            'address'            => 'nullable|string',
            'country_id'         => 'nullable|exists:countries,id',
            'state_id'           => 'nullable|exists:states,id',
            'district_id'        => 'nullable|exists:districts,id',
            'description'        => 'nullable|string',
            'owner_name'         => 'nullable|string|max:255',
            'owner_phone'        => 'nullable|string|max:30',
            'owner_email'        => 'nullable|email|max:255',
            'location'           => 'nullable|string|max:255',
            'pincode'            => 'nullable|string|max:20',
            'expected_maturity'  => 'nullable|date',
            'preferred_material' => 'nullable|string|max:255',
            'assignee_id'        => 'nullable|exists:users,id',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date|after_or_equal:start_date',
        ]);

        $validated['branch_id'] = $request->user()->branch_id ?? 1;
        $validated['created_by'] = $request->user()->id;

        $project = Project::create($validated);
        $project->load(['builder', 'projectCategory', 'country', 'state', 'district', 'assignee']);

        return response()->json($project, 201);
    }

    public function show(Project $project): JsonResponse
    {
        $project->load(['builder', 'projectCategory', 'country', 'state', 'district', 'assignee', 'contacts', 'quotations']);
        return response()->json($project);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name'               => 'sometimes|required|string|max:255',
            'project_category_id'=> 'sometimes|required|exists:project_categories,id',
            'builder_id'         => 'nullable|exists:builders,id',
            'status'             => 'sometimes|required|in:planning,ongoing,completed',
            'address'            => 'nullable|string',
            'country_id'         => 'nullable|exists:countries,id',
            'state_id'           => 'nullable|exists:states,id',
            'district_id'        => 'nullable|exists:districts,id',
            'description'        => 'nullable|string',
            'owner_name'         => 'nullable|string|max:255',
            'owner_phone'        => 'nullable|string|max:30',
            'owner_email'        => 'nullable|email|max:255',
            'location'           => 'nullable|string|max:255',
            'pincode'            => 'nullable|string|max:20',
            'expected_maturity'  => 'nullable|date',
            'preferred_material' => 'nullable|string|max:255',
            'assignee_id'        => 'nullable|exists:users,id',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date|after_or_equal:start_date',
        ]);

        $project->update($validated);
        $project->load(['builder', 'projectCategory', 'country', 'state', 'district', 'assignee']);

        return response()->json($project);
    }

    public function destroy(Project $project): JsonResponse
    {
        $project->delete();
        return response()->json(null, 204);
    }
}
