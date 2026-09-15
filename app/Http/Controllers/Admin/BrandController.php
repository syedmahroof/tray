<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBrandRequest;
use App\Models\Brand;
use App\Support\StoredImage;
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
            ...$request->safe()->except(['logo', 'remove_logo']),
            'is_active' => $request->boolean('is_active'),
            'logo_path' => StoredImage::sync(null, $request->file('logo'), false, 'brands'),
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
            ...$request->safe()->except(['logo', 'remove_logo']),
            'is_active' => $request->boolean('is_active'),
            'logo_path' => StoredImage::sync(
                $brand->logo_path,
                $request->file('logo'),
                $request->boolean('remove_logo'),
                'brands',
            ),
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
        StoredImage::delete($brand->logo_path);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Brand deleted.')]);

        return to_route('brands.index');
    }
}
