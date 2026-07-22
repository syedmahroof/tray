<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Builder;
use App\Models\VisitReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuilderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $builders = Builder::query()
            ->with(['country', 'state', 'district'])
            ->withCount('projects')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('contact_person', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->input('created_by'), fn ($query, $value) => $query->where('created_by', $value))
            ->when($request->input('created_from'), fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($request->input('created_to'), fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->when(VisitReport::NO_VISIT_PERIODS[$request->input('no_visit_within')] ?? null, function ($query, $days) {
                $query->whereDoesntHave('projects', fn ($project) => $project->whereHas('visitReports', fn ($sub) => $sub->where('visit_date', '>=', now()->subDays($days)->toDateString())));
            })
            ->orderBy('name')
            ->paginate(15);

        return response()->json($builders);
    }

    public function show(Builder $builder): JsonResponse
    {
        $builder->load(['country', 'state', 'district', 'creator']);
        $builder->loadCount('projects');

        return response()->json([
            ...$builder->toArray(),
            'projects' => $builder->projects()->latest()->get(['id', 'name', 'status', 'location']),
            'quotations' => $builder->quotations()->latest()->get(['id', 'number', 'status', 'total']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);

        $validated['branch_id'] = $request->user()->branch_id ?? 1;
        $validated['created_by'] = $request->user()->id;
        $validated['is_active'] = $request->boolean('is_active', true);

        $builder = Builder::create($validated);

        return response()->json($builder->load(['country', 'state', 'district']), 201);
    }

    public function update(Request $request, Builder $builder): JsonResponse
    {
        $validated = $this->validated($request);

        if ($request->has('is_active')) {
            $validated['is_active'] = $request->boolean('is_active');
        }

        $builder->update($validated);

        return response()->json($builder->load(['country', 'state', 'district']));
    }

    public function destroy(Builder $builder): JsonResponse
    {
        // Mirrors the web admin rule: projects reference the builder, so removing
        // one that still has them would orphan those records.
        if ($builder->projects()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a builder that still has projects.',
            ], 422);
        }

        $builder->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name'           => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:30',
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string|max:255',
            'country_id'     => 'nullable|exists:countries,id',
            'state_id'       => 'nullable|exists:states,id',
            'district_id'    => 'nullable|exists:districts,id',
        ]);
    }
}
