<?php

namespace App\Support\PriceList;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBranchPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Builds the workbook-shaped view of the catalogue: one row per product with a
 * COST then SR % / SR / SR+TAX (and the same for PR and CR) block per selected branch, so
 * branches can be compared side by side.
 */
class BranchPriceMatrix
{
    /**
     * Apply the shared catalogue filters to a product query.
     *
     * @param  Builder<Product>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<Product>
     */
    public function filter(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return $query
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('hsn_code', 'like', "%{$search}%")
                        ->orWhereHas('productCategory', fn (Builder $sub) => $sub->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('brand', fn (Builder $sub) => $sub->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(
                $filters['product_category_ids'] ?? null,
                fn (Builder $query, array $ids) => $query->whereIn('product_category_id', $ids),
            )
            ->when(
                $filters['brand_ids'] ?? null,
                fn (Builder $query, array $ids) => $query->whereIn('brand_id', $ids),
            )
            ->when(
                $filters['unit'] ?? null,
                fn (Builder $query, string $unit) => $query->where('unit', $unit),
            )
            ->when(
                // Only products that carry a price in every selected branch,
                // so a comparison never shows half-empty rows by accident.
                $filters['priced_only'] ?? false,
                fn (Builder $query) => $query->whereHas(
                    'branchPrices',
                    fn (Builder $sub) => $sub->withoutGlobalScopes()->whereIn('branch_id', $filters['branch_ids'] ?? []),
                ),
            );
    }

    /**
     * Shape a page of products into matrix rows keyed by branch.
     *
     * @param  LengthAwarePaginator<int, Product>  $products
     * @param  Collection<int, Branch>  $branches
     * @return LengthAwarePaginator<int, PriceRow>
     */
    public function rows(LengthAwarePaginator $products, Collection $branches): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $this->map($products->getCollection(), $branches)->all(),
            $products->total(),
            $products->perPage(),
            $products->currentPage(),
            ['path' => $products->path(), 'pageName' => $products->getPageName()],
        );
    }

    /**
     * Shape a plain collection of products into matrix rows. Used by the
     * exports, which are not paginated.
     *
     * @param  Collection<int, Product>|EloquentCollection<int, Product>  $products
     * @param  Collection<int, Branch>  $branches
     * @return Collection<int, PriceRow>
     */
    public function map(Collection|EloquentCollection $products, Collection $branches): Collection
    {
        $branchIds = $branches->map(fn (Branch $branch): int => $branch->id)->all();

        $prices = ProductBranchPrice::query()
            ->withoutGlobalScopes()
            ->whereIn('product_id', $products->map(fn (Product $product): int => $product->id))
            ->whereIn('branch_id', $branchIds)
            ->get()
            ->keyBy(fn (ProductBranchPrice $price): string => $price->product_id.'-'.$price->branch_id);

        return collect($products->all())
            ->map(fn (Product $product): PriceRow => $this->row($product, $branchIds, $prices));
    }

    /**
     * Shape one product into a matrix row.
     *
     * @param  array<int, int>  $branchIds
     * @param  EloquentCollection<string, ProductBranchPrice>  $prices  keyed "productId-branchId"
     */
    private function row(Product $product, array $branchIds, EloquentCollection $prices): PriceRow
    {
        $cells = [];

        foreach ($branchIds as $branchId) {
            $cells[$branchId] = PriceCells::from($prices->get("{$product->id}-{$branchId}"));
        }

        return new PriceRow(
            id: $product->id,
            code: $product->code,
            name: $product->name,
            unit: $product->unit,
            hsn_code: $product->hsn_code,
            tax_percentage: $product->tax_percentage,
            category: $product->productCategory->name,
            category_id: $product->product_category_id,
            brand: $product->brand?->name,
            prices: $cells,
        );
    }
}
