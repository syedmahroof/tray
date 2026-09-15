<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Products\SaveProductBranchPrice;
use App\Exports\GenericSheetExport;
use App\Exports\PriceListExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Http\Requests\Admin\UpdateProductBranchPriceRequest;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductBranchPrice;
use App\Models\ProductCategory;
use App\Models\ProductPriceHistory;
use App\Models\User;
use App\Support\BranchAccess;
use App\Support\BranchFilter;
use App\Support\PriceList\BranchPriceMatrix;
use App\Support\PriceList\PriceCells;
use App\Support\PriceList\PriceRow;
use App\Support\ProductCodeGenerator;
use App\Support\StoredImage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductController extends Controller
{
    public function __construct(
        private ProductCodeGenerator $codeGenerator,
        private BranchPriceMatrix $matrix,
        private SaveProductBranchPrice $savePrice,
    ) {}

    /**
     * Display a listing of products.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $products = $this->matrix
            ->filter(Product::query()->with(['productCategory', 'brand', 'creator']), $filters)
            ->withCount('branchPrices')
            ->when($filters['created_by'], fn ($query, $value) => $query->where('created_by', $value))
            ->when($filters['created_from'], fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['created_to'], fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/products/Index', [
            'products' => $products,
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            ...$this->lookups(),
            'filters' => $filters,
        ]);
    }

    /**
     * The workbook-shaped price list: every selected branch's rates side by
     * side, grouped by category the way the source spreadsheet is.
     */
    public function priceList(Request $request): Response
    {
        $filters = $this->filters($request);
        $branches = BranchFilter::resolve($filters['branch_ids']);

        $products = $this->matrix
            ->filter(
                Product::query()->with(['productCategory', 'brand']),
                [...$filters, 'branch_ids' => $branches->pluck('id')->all()],
            )
            ->orderBy('product_category_id')
            ->orderBy('name')
            ->paginate((int) $request->integer('per_page', 50))
            ->withQueryString();

        return Inertia::render('admin/products/PriceList', [
            'products' => $this->matrix->rows($products, $branches),
            'branches' => $branches->values(),
            ...$this->lookups(),
            'filters' => $filters,
        ]);
    }

    /**
     * Save figures edited in place on the price list, logging the change
     * against the editor. The action derives the tax-inclusive rates, so a
     * cleared rate has its tax-inclusive partner cleared here.
     */
    public function updateBranchPrice(UpdateProductBranchPriceRequest $request, Product $product, Branch $branch): JsonResponse
    {
        $attributes = $request->validated();

        foreach (['sr', 'pr', 'cr'] as $tier) {
            if (array_key_exists("{$tier}_rate", $attributes) && $attributes["{$tier}_rate"] === null) {
                $attributes["{$tier}_rate_with_tax"] = null;
            }
        }

        $price = $this->savePrice->handle($product, $branch, $attributes, $request->user(), __('Edited on the price list'));

        return response()->json(PriceCells::from($price));
    }

    /**
     * Export the filtered products to an Excel spreadsheet.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $products = $this->matrix
            ->filter(Product::query()->with(['productCategory', 'brand', 'creator']), $this->filters($request))
            ->orderBy('name')
            ->get();

        $rows = $products->map(fn (Product $product): array => [
            $product->code,
            $product->name,
            $product->productCategory->name,
            $product->brand?->name,
            $product->unit,
            $product->price,
            $product->creator?->name,
            $product->created_at?->format('Y-m-d'),
        ])->all();

        return Excel::download(
            new GenericSheetExport(
                ['Code', 'Name', 'Category', 'Brand', 'Unit', 'Price', 'Created By', 'Created At'],
                $rows,
            ),
            'products.xlsx',
        );
    }

    /**
     * Export the price list in the shape of the source workbook: one sheet per
     * category, with every selected branch's rates side by side.
     */
    public function exportPriceList(Request $request): BinaryFileResponse
    {
        [$rows, $branches] = $this->priceListRows($request);

        return Excel::download(
            new PriceListExport($rows, $branches),
            'price-list.xlsx',
        );
    }

    /**
     * Export every branch: a sheet per branch plus a comparison sheet.
     */
    public function exportPriceListByBranch(Request $request): BinaryFileResponse
    {
        [$rows, $branches] = $this->priceListRows($request);

        return Excel::download(
            new PriceListExport($rows, $branches, sheetPerBranch: true),
            'price-list-all-branches.xlsx',
        );
    }

    /**
     * The filtered price list rows shared by both exports.
     *
     * @return array{Collection<int, PriceRow>, Collection<int, Branch>}
     */
    private function priceListRows(Request $request): array
    {
        $filters = $this->filters($request);
        $branches = BranchFilter::resolve($filters['branch_ids']);

        $products = $this->matrix
            ->filter(Product::query()->with(['productCategory', 'brand']), $filters)
            ->orderBy('product_category_id')
            ->orderBy('name')
            ->get();

        return [$this->matrix->map($products, $branches), $branches];
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): Response
    {
        return Inertia::render('admin/products/Create', $this->formData());
    }

    /**
     * Store a newly created product.
     */
    public function store(SaveProductRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $prices = $attributes['prices'] ?? [];
        unset($attributes['prices'], $attributes['price_reason'], $attributes['image'], $attributes['remove_image']);

        $attributes['image_path'] = StoredImage::sync(null, $request->file('image'), false, 'products');

        $attributes['code'] ??= $this->codeGenerator->generate(
            ProductCategory::query()->whereKey($attributes['product_category_id'])->firstOrFail(),
            Brand::query()->whereKey($attributes['brand_id'] ?? null)->first(),
        );

        DB::transaction(function () use ($request, $attributes, $prices): void {
            $product = Product::create([...$attributes, 'created_by' => $request->user()->id]);

            $this->syncPrices($product, $prices, $request);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created.')]);

        return to_route('products.index');
    }

    /**
     * Display the given product.
     */
    public function show(Request $request, Product $product): Response
    {
        $product->load(['productCategory', 'brand', 'projects.builder']);

        $branches = BranchAccess::options();

        return Inertia::render('admin/products/Show', [
            'product' => $product,
            'branches' => $branches,
            'branchPrices' => ProductBranchPrice::query()
                ->with('branch:id,name,code')
                ->where('product_id', $product->id)
                ->get(),
            'priceHistory' => Inertia::optional(
                fn () => $this->priceHistory($request, $product),
            ),
            'historyFilters' => ['branch_id' => $request->input('history_branch_id')],
        ]);
    }

    /**
     * Show the form for editing the given product.
     */
    public function edit(Product $product): Response
    {
        return Inertia::render('admin/products/Edit', [
            'product' => $product,
            'branchPrices' => ProductBranchPrice::query()->where('product_id', $product->id)->get(),
            ...$this->formData(),
        ]);
    }

    /**
     * Update the given product.
     */
    public function update(SaveProductRequest $request, Product $product): RedirectResponse
    {
        $attributes = $request->validated();
        $prices = $attributes['prices'] ?? [];
        unset($attributes['prices'], $attributes['price_reason'], $attributes['image'], $attributes['remove_image']);

        $attributes['image_path'] = StoredImage::sync(
            $product->image_path,
            $request->file('image'),
            $request->boolean('remove_image'),
            'products',
        );

        DB::transaction(function () use ($request, $product, $attributes, $prices): void {
            $product->update($attributes);

            $this->syncPrices($product, $prices, $request);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return to_route('products.index');
    }

    /**
     * Remove the given product.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();
        StoredImage::delete($product->image_path);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product deleted.')]);

        return to_route('products.index');
    }

    /**
     * Write the submitted branch prices, logging every change against the user.
     *
     * Only the branches present in the payload are touched — a user who can
     * see one branch must never wipe another branch's price by saving the
     * form. Removing a row is explicit, via its `remove` flag.
     *
     * @param  array<int, array<string, mixed>>  $prices
     */
    private function syncPrices(Product $product, array $prices, Request $request): void
    {
        if (! $request->user()?->can('products.price.update')) {
            return;
        }

        $reason = $request->input('price_reason') ?: null;

        foreach ($prices as $price) {
            $branchId = (int) $price['branch_id'];
            unset($price['branch_id']);

            if ($price['remove'] ?? false) {
                ProductBranchPrice::query()
                    ->where('product_id', $product->id)
                    ->where('branch_id', $branchId)
                    ->delete();

                continue;
            }

            unset($price['remove']);

            $this->savePrice->handle($product, $branchId, $price, $request->user(), $reason);
        }
    }

    /**
     * A page of the product's price change log, newest first.
     *
     * @return LengthAwarePaginator<int, ProductPriceHistory>
     */
    private function priceHistory(Request $request, Product $product)
    {
        return $product->priceHistories()
            ->with(['user:id,name', 'branch:id,name,code'])
            ->when(
                $request->input('history_branch_id'),
                fn ($query, $value) => $query->where('branch_id', $value),
            )
            ->latest('changed_at')
            ->latest('id')
            ->paginate(20, pageName: 'history_page')
            ->withQueryString();
    }

    /**
     * The filters shared by the list, the price list and the export.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'search' => trim((string) $request->input('search', '')),
            'product_category_ids' => $this->ids($request, 'product_category_ids'),
            'brand_ids' => $this->ids($request, 'brand_ids'),
            'branch_ids' => $this->ids($request, 'branch_ids'),
            'unit' => $request->input('unit') ?: null,
            'created_by' => $request->input('created_by'),
            'created_from' => $request->input('created_from'),
            'created_to' => $request->input('created_to'),
        ];
    }

    /**
     * @return list<int>|null
     */
    private function ids(Request $request, string $key): ?array
    {
        $values = $request->input($key);

        if (! is_array($values)) {
            $values = $values === null || $values === '' ? [] : explode(',', (string) $values);
        }

        $ids = array_values(array_filter(array_map('intval', $values)));

        return $ids === [] ? null : $ids;
    }

    /**
     * @return array<string, mixed>
     */
    private function lookups(): array
    {
        return [
            'productCategories' => ProductCategory::query()->orderBy('name')->get(['id', 'name']),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'units' => Product::UNITS,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            ...$this->lookups(),
            'branches' => BranchAccess::options(),
            'gstSlabs' => Product::GST_SLABS,
        ];
    }
}
