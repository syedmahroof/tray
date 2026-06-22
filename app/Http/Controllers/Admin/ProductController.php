<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\BranchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $createdBy = $request->input('created_by');
        $createdFrom = $request->input('created_from');
        $createdTo = $request->input('created_to');

        return Inertia::render('admin/products/Index', [
            'products' => Product::query()
                ->with(['productCategory', 'creator'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhereHas('productCategory', fn ($sub) => $sub->where('name', 'like', "%{$search}%"));
                    });
                })
                ->when($createdBy, fn ($query) => $query->where('created_by', $createdBy))
                ->when($createdFrom, fn ($query) => $query->whereDate('created_at', '>=', $createdFrom))
                ->when($createdTo, fn ($query) => $query->whereDate('created_at', '<=', $createdTo))
                ->orderBy('name')
                ->paginate(15)
                ->withQueryString(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $search,
                'created_by' => $createdBy,
                'created_from' => $createdFrom,
                'created_to' => $createdTo,
            ],
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): Response
    {
        return Inertia::render('admin/products/Create', [
            'productCategories' => ProductCategory::query()->orderBy('name')->get(['id', 'name']),
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(SaveProductRequest $request): RedirectResponse
    {
        Product::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created.')]);

        return to_route('products.index');
    }

    /**
     * Display the given product.
     */
    public function show(Product $product): Response
    {
        $product->load(['productCategory', 'branch', 'projects.builder']);

        return Inertia::render('admin/products/Show', [
            'product' => $product,
        ]);
    }

    /**
     * Show the form for editing the given product.
     */
    public function edit(Product $product): Response
    {
        return Inertia::render('admin/products/Edit', [
            'product' => $product,
            'productCategories' => ProductCategory::query()->orderBy('name')->get(['id', 'name']),
            'branches' => BranchAccess::canChooseBranch() ? BranchAccess::options() : [],
        ]);
    }

    /**
     * Update the given product.
     */
    public function update(SaveProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return to_route('products.index');
    }

    /**
     * Remove the given product.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product deleted.')]);

        return to_route('products.index');
    }
}
