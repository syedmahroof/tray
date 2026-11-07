<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Branch;
use App\Models\Quotation;
use App\Models\QuotationItem;

trait ComputesQuotationTotals
{
    /**
     * Compute per-item tax/discount allocation and the quotation-level totals
     * (subtotal, discount, tax, CGST/SGST/IGST split, grand total) for a set
     * of line items. Shared between the web and API quotation controllers so
     * mobile- and web-created quotations are taxed identically.
     *
     * Each line's price is first settled on its own: a custom line is its MRP
     * less its percentage or its cost plus it, any other line keeps the price
     * it was given.
     * An agent commission is then spread over the lines in proportion to their
     * value, so the printed prices carry it without stating it.
     *
     * @param  array<int, array{product_id?: int|null, description: string, hsn_code?: string|null, quantity: numeric-string|float, rate_tier?: string|null, price_basis?: string|null, list_price?: numeric-string|float|null, price_percent?: numeric-string|float|null, unit_price: numeric-string|float, tax_percentage?: numeric-string|float|null}>  $items
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    protected function computeQuotationTotals(array $items, float $discount, string $supplyType, float $agentCommission = 0.0): array
    {
        $items = array_map(fn (array $item): array => $this->withBasePrice($item), $items);

        $baseSubtotal = array_reduce(
            $items,
            fn (float $carry, array $item): float => $carry + ((float) $item['quantity'] * $item['base_price']),
            0.0,
        );

        $agentCommission = $baseSubtotal > 0 ? max($agentCommission, 0.0) : 0.0;
        $markup = $baseSubtotal > 0 ? $agentCommission / $baseSubtotal : 0.0;

        $items = array_map(fn (array $item): array => [
            ...$item,
            'unit_price' => round($item['base_price'] * (1 + $markup), 2),
        ], $items);

        $subtotal = array_reduce(
            $items,
            fn (float $carry, array $item): float => $carry + ((float) $item['quantity'] * $item['unit_price']),
            0.0,
        );

        $discount = min($discount, $subtotal);

        $rows = [];
        $totalTax = 0.0;

        foreach ($items as $item) {
            $base = (float) $item['quantity'] * $item['unit_price'];
            $allocatedDiscount = $subtotal > 0 ? $discount * ($base / $subtotal) : 0.0;
            $taxable = max($base - $allocatedDiscount, 0);
            $rate = (float) ($item['tax_percentage'] ?? 0);
            $taxAmount = round($taxable * $rate / 100, 2);
            $totalTax += $taxAmount;

            $rows[] = [
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'hsn_code' => $item['hsn_code'] ?? null,
                'quantity' => $item['quantity'],
                'rate_tier' => $item['rate_tier'],
                'price_basis' => $item['price_basis'],
                'list_price' => $item['list_price'],
                'price_percent' => $item['price_percent'],
                'base_price' => $item['base_price'],
                'unit_price' => $item['unit_price'],
                'tax_percentage' => $rate,
                'tax_amount' => $taxAmount,
            ];
        }

        $totalTax = round($totalTax, 2);
        $cgst = $sgst = $igst = 0.0;

        if ($supplyType === 'inter') {
            $igst = $totalTax;
        } else {
            $cgst = round($totalTax / 2, 2);
            $sgst = round($totalTax - $cgst, 2);
        }

        return [
            'rows' => $rows,
            'totals' => [
                'subtotal' => round($subtotal, 2),
                'discount' => round($discount, 2),
                'agent_commission' => round($agentCommission, 2),
                'tax_amount' => $totalTax,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'total' => round($subtotal - $discount + $totalTax, 2),
            ],
        ];
    }

    /**
     * Settle a line's own price before any commission. A custom line with a
     * list price is that price less its percentage (off MRP) or plus it (on
     * cost). Any other line keeps the price it was given, and its basis
     * figures are kept as they were sent, for reference.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function withBasePrice(array $item): array
    {
        $basis = in_array($item['price_basis'] ?? null, ['mrp', 'cost'], true) ? $item['price_basis'] : null;
        $listPrice = is_numeric($item['list_price'] ?? null) && (float) $item['list_price'] > 0 ? (float) $item['list_price'] : null;
        $percent = is_numeric($item['price_percent'] ?? null) ? (float) $item['price_percent'] : null;

        $item = [
            ...$item,
            'rate_tier' => $item['rate_tier'] ?? null,
            'price_basis' => $listPrice !== null ? $basis : null,
            'list_price' => $basis !== null ? $listPrice : null,
            'price_percent' => $basis !== null && $listPrice !== null ? $percent : null,
            'base_price' => (float) $item['unit_price'],
        ];

        if ($item['rate_tier'] !== QuotationItem::CUSTOM_TIER || $item['list_price'] === null) {
            return $item;
        }

        $percent = $item['price_percent'] ?? 0.0;
        $factor = $item['price_basis'] === 'cost' ? 1 + $percent / 100 : 1 - $percent / 100;

        return [
            ...$item,
            'price_percent' => $percent,
            'base_price' => max(0.0, round($item['list_price'] * $factor, 2)),
        ];
    }

    /**
     * Give a newly created quotation its number.
     *
     * A branch with its own series hands out its next number, skipping any
     * already taken (for example after the series is reset), and moves the
     * series on. The branch row is locked so two quotations raised at once
     * cannot draw the same number. Branches without a series fall back to the
     * company-wide QT-YYYY-NNNNN numbering.
     */
    protected function generateQuotationNumber(Quotation $quotation): string
    {
        $branch = Branch::query()->whereKey($quotation->branch_id)->lockForUpdate()->first();

        if (! $branch?->hasQuotationSeries()) {
            return 'QT-'.now()->format('Y').'-'.str_pad((string) $quotation->id, 5, '0', STR_PAD_LEFT);
        }

        $next = max(1, $branch->quotation_next_number);

        while (Quotation::query()->withoutGlobalScopes()->where('number', $branch->quotationNumber($next))->exists()) {
            $next++;
        }

        $branch->update(['quotation_next_number' => $next + 1]);

        return $branch->quotationNumber($next);
    }
}
