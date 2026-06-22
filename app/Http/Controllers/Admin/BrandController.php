<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBrandRequest;
use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    /**
     * Display a listing of brands.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('admin/brands/Index', [
            'brands' => Brand::query()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Store a newly created brand.
     */
    public function store(SaveBrandRequest $request): RedirectResponse
    {
        Brand::create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand created.')]);

        return to_route('brands.index');
    }

    /**
     * Update the given brand.
     */
    public function update(SaveBrandRequest $request, Brand $brand): RedirectResponse
    {
        $brand->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand updated.')]);

        return to_route('brands.index');
    }

    /**
     * Remove the given brand.
     */
    public function destroy(Brand $brand): RedirectResponse
    {
        if ($brand->products()->withoutGlobalScopes()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a brand that still has products.')]);

            return back();
        }

        $brand->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand deleted.')]);

        return to_route('brands.index');
    }
}
