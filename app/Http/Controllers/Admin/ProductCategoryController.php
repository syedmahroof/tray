<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductCategoryRequest;
use App\Models\Product;
use App\Models\ProductBranchPrice;
use App\Models\ProductCategory;
use App\Support\BranchFilter;
use App\Support\PriceList\BranchPriceMatrix;
use App\Support\StoredImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductCategoryController extends Controller
{
    public function __construct(private BranchPriceMatrix $matrix) {}

    /**
     * Display a listing of product categories.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        return Inertia::render('admin/product-categories/Index', [
            'productCategories' => ProductCategory::query()
                ->withCount('products')
                ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * Display a category with the products filed under it.
     */
    public function show(Request $request, ProductCategory $productCategory): Response
    {
        $search = trim((string) $request->input('search', ''));
        $branches = BranchFilter::resolve($this->branchIds($request));

        $products = $this->matrix
            ->filter(
                Product::query()
                    ->where('product_category_id', $productCategory->id)
                    ->with(['productCategory', 'brand']),
                ['search' => $search],
            )
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/product-categories/Show', [
            'productCategory' => $productCategory,
            'products' => $this->matrix->rows($products, $branches),
            'branches' => $branches->values(),
            'stats' => [
                'products' => $productCategory->products()->count(),
                'brands' => $productCategory->products()->distinct()->count('brand_id'),
                'priced' => ProductBranchPrice::query()
                    ->whereIn('product_id', $productCategory->products()->select('id'))
                    ->distinct()
                    ->count('product_id'),
            ],
            'filters' => ['search' => $search, 'branch_ids' => $this->branchIds($request)],
        ]);
    }

    /**
     * @return list<int>|null
     */
    private function branchIds(Request $request): ?array
    {
        $values = $request->input('branch_ids');

        if (! is_array($values)) {
            $values = $values === null || $values === '' ? [] : explode(',', (string) $values);
        }

        $ids = array_values(array_filter(array_map('intval', $values)));

        return $ids === [] ? null : $ids;
    }

    /**
     * Store a newly created product category.
     */
    public function store(SaveProductCategoryRequest $request): RedirectResponse
    {
        ProductCategory::create([
            ...$request->safe()->except(['image', 'remove_image']),
            'is_active' => $request->boolean('is_active'),
            'image_path' => StoredImage::sync(null, $request->file('image'), false, 'product-categories'),
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
            ...$request->safe()->except(['image', 'remove_image']),
            'is_active' => $request->boolean('is_active'),
            'image_path' => StoredImage::sync(
                $productCategory->image_path,
                $request->file('image'),
                $request->boolean('remove_image'),
                'product-categories',
            ),
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
        StoredImage::delete($productCategory->image_path);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product category deleted.')]);

        return to_route('product-categories.index');
    }
}
