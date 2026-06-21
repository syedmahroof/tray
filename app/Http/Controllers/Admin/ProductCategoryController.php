<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductCategoryRequest;
use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductCategoryController extends Controller
{
    /**
     * Display a listing of product categories.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('admin/product-categories/Index', [
            'productCategories' => ProductCategory::query()
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Store a newly created product category.
     */
    public function store(SaveProductCategoryRequest $request): RedirectResponse
    {
        ProductCategory::create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product category created.')]);

        return to_route('product-categories.index');
    }

    /**
     * Update the given product category.
     */
    public function update(SaveProductCategoryRequest $request, ProductCategory $productCategory): RedirectResponse
    {
        $productCategory->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product category updated.')]);

        return to_route('product-categories.index');
    }

    /**
     * Remove the given product category.
     */
    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        if ($productCategory->products()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Cannot delete a category that still has products.')]);

            return back();
        }

        $productCategory->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product category deleted.')]);

        return to_route('product-categories.index');
    }
}
