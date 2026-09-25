<?php

namespace App\Support\Analytics;

use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Collection;

/**
 * Which products, brands and categories are being quoted, and for how much.
 */
class ProductAnalytics extends AnalyticsSection
{
    public function title(): string
    {
        return 'Products & Brands';
    }

    public function description(): string
    {
        return 'The products, brands and categories quoted, by quantity and value.';
    }

    public function report(AnalyticsPeriod $period): AnalyticsReport
    {
        $current = $this->lines($period);
        $previous = $this->lines($period->previous());

        $value = fn ($line): float => (float) $line->quantity * (float) $line->unit_price;
        $totalValue = fn (Collection $lines): float => round((float) $lines->sum($value), 2);

        return (new AnalyticsReport)
            ->stat('Products quoted', $current->whereNotNull('product_id')->unique('product_id')->count(), $previous->whereNotNull('product_id')->unique('product_id')->count(), icon: 'package', color: '#4f46e5')
            ->stat('Brands quoted', $current->map(fn (QuotationItem $l) => $l->product?->brand_id)->filter()->unique()->count(), $previous->map(fn (QuotationItem $l) => $l->product?->brand_id)->filter()->unique()->count(), icon: 'tag', color: '#db2777')
            ->stat('Line value quoted', $totalValue($current), $totalValue($previous), 'money', 'wallet', '#0ea5e9')
            ->stat('Won line value', $totalValue($current->filter(fn (QuotationItem $l): bool => $l->quotation->status === 'accepted')), $totalValue($previous->filter(fn (QuotationItem $l): bool => $l->quotation->status === 'accepted')), 'money', 'trophy', '#16a34a')
            ->chart('Quoted value by brand', 'donut', $this->sumBy($current, fn (QuotationItem $l) => $l->product?->brand?->name ?? 'No brand', $value, 8), 'money')
            ->chart('Quoted value by category', 'bar', $this->sumBy($current, fn (QuotationItem $l) => $l->product?->productCategory?->name ?? 'Uncategorised', $value, 10), 'money')
            ->chart('Top products by quoted value', 'bar', $this->sumBy($current, fn (QuotationItem $l) => $l->product?->name ?? $l->description, $value, 10), 'money')
            ->chart('Won value by brand', 'bar', $this->sumBy($current->filter(fn (QuotationItem $l): bool => $l->quotation->status === 'accepted'), fn (QuotationItem $l) => $l->product?->brand?->name ?? 'No brand', $value, 10), 'money')
            ->table('Top products', [
                'name' => 'Product',
                'brand' => 'Brand',
                'quotations' => ['Quotations', 'number'],
                'quantity' => ['Quantity', 'number'],
                'quoted' => ['Quoted', 'money'],
                'won' => ['Won', 'money'],
            ], $current
                ->groupBy(fn (QuotationItem $l): string => $l->product?->name ?? $l->description)
                ->map(fn (Collection $group, string $name): array => [
                    'name' => $name,
                    'brand' => $group->first()?->product?->brand?->name ?? '—',
                    'quotations' => $group->unique('quotation_id')->count(),
                    'quantity' => round((float) $group->sum('quantity'), 2),
                    'quoted' => $totalValue($group),
                    'won' => $totalValue($group->filter(fn (QuotationItem $l): bool => $l->quotation->status === 'accepted')),
                ])
                ->sortByDesc('quoted')
                ->take(20)
                ->values()
                ->all());
    }

    /**
     * The quotation lines of the live quotations dated in the period.
     *
     * @return Collection<int, QuotationItem>
     */
    private function lines(AnalyticsPeriod $period): Collection
    {
        return QuotationItem::query()
            ->whereHas('quotation', fn ($query) => $this->within($query, $period, 'quotation_date')->where('status', '!=', 'revised'))
            ->with(['quotation:id,status', 'product:id,name,brand_id,product_category_id', 'product.brand:id,name', 'product.productCategory:id,name'])
            ->get(['id', 'quotation_id', 'product_id', 'description', 'quantity', 'unit_price']);
    }
}
