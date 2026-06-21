<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBuilderRequest;
use App\Models\Builder;
use App\Models\Country;
use App\Support\BranchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuilderController extends Controller
{
    /**
     * Display a listing of builders.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('admin/builders/Index', [
            'builders' => Builder::query()
                ->with(['country', 'state', 'district'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('contact_person', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the form for creating a new builder.
     */
    public function create(): Response
    {
        return Inertia::render('admin/builders/Create', [
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
        ]);
    }

    /**
     * Store a newly created builder.
     */
    public function store(SaveBuilderRequest $request): RedirectResponse
    {
        Builder::create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Builder created.')]);

        return to_route('builders.index');
    }

    /**
     * Display the given builder.
     */
    public function show(Builder $builder): Response
    {
        $builder->load(['country', 'state', 'district', 'projects']);

        return Inertia::render('admin/builders/Show', [
            'builder' => $builder,
        ]);
    }

    /**
     * Show the form for editing the given builder.
     */
    public function edit(Builder $builder): Response
    {
        return Inertia::render('admin/builders/Edit', [
            'builder' => $builder,
            'countries' => Country::query()->orderBy('name')->get(['id', 'name']),
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
        ]);
    }

    /**
     * Update the given builder.
     */
    public function update(SaveBuilderRequest $request, Builder $builder): RedirectResponse
    {
        $builder->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Builder updated.')]);

        return to_route('builders.index');
    }

    /**
     * Remove the given builder.
     */
    public function destroy(Builder $builder): RedirectResponse
    {
        if ($builder->projects()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a builder that still has projects.')]);

            return back();
        }

        $builder->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Builder deleted.')]);

        return to_route('builders.index');
    }
}
