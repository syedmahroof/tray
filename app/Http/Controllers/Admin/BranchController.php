<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBranchRequest;
use App\Models\Branch;
use App\Models\Brand;
use App\Support\StoredImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    /**
     * Display a listing of branches.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('admin/branches/Index', [
            'branches' => Branch::query()
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Show the form for creating a new branch.
     */
    public function create(): Response
    {
        return Inertia::render('admin/branches/Create', [
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created branch.
     */
    public function store(SaveBranchRequest $request): RedirectResponse
    {
        $branch = Branch::create([
            ...$request->safe()->except(['logo', 'remove_logo', 'brands']),
            'is_active' => $request->boolean('is_active'),
            'logo_path' => StoredImage::sync(null, $request->file('logo'), false, 'branch-logos'),
        ]);

        $branch->brands()->sync($request->validated('brands', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branch created.')]);

        return to_route('branches.index');
    }

    /**
     * Show the form for editing the given branch.
     */
    public function edit(Branch $branch): Response
    {
        return Inertia::render('admin/branches/Edit', [
            'branch' => [
                ...$branch->toArray(),
                'brand_ids' => $branch->brands()->pluck('brands.id'),
            ],
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Update the given branch.
     */
    public function update(SaveBranchRequest $request, Branch $branch): RedirectResponse
    {
        $branch->update([
            ...$request->safe()->except(['logo', 'remove_logo', 'brands']),
            'is_active' => $request->boolean('is_active'),
            'logo_path' => StoredImage::sync(
                $branch->logo_path,
                $request->file('logo'),
                $request->boolean('remove_logo'),
                'branch-logos',
            ),
        ]);

        $branch->brands()->sync($request->validated('brands', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branch updated.')]);

        return to_route('branches.index');
    }

    /**
     * Remove the given branch.
     */
    public function destroy(Branch $branch): RedirectResponse
    {
        if ($branch->users()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a branch that still has users assigned to it.')]);

            return back();
        }

        StoredImage::delete($branch->logo_path);
        $branch->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Branch deleted.')]);

        return to_route('branches.index');
    }
}
