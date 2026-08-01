<?php

namespace App\Http\Controllers\Concerns;

trait ComputesQuotationTotals
{
    /**
     * Compute per-item tax/discount allocation and the quotation-level totals
     * (subtotal, discount, tax, CGST/SGST/IGST split, grand total) for a set
     * of line items. Shared between the web and API quotation controllers so
     * mobile- and web-created quotations are taxed identically.
     *
     * @param  array<int, array{product_id?: int|null, description: string, hsn_code?: string|null, quantity: numeric-string|float, unit_price: numeric-string|float, tax_percentage?: numeric-string|float|null}>  $items
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    protected function computeQuotationTotals(array $items, float $discount, string $supplyType): array
    {
        $subtotal = array_reduce(
            $items,
            fn (float $carry, array $item): float => $carry + ((float) $item['quantity'] * (float) $item['unit_price']),
            0.0,
        );

        $discount = min($discount, $subtotal);

        $rows = [];
        $totalTax = 0.0;

        foreach ($items as $item) {
            $base = (float) $item['quantity'] * (float) $item['unit_price'];
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
                'tax_amount' => $totalTax,
                'cgst_amount' => $cgst,
                'sgst_amount' => $sgst,
                'igst_amount' => $igst,
                'total' => round($subtotal - $discount + $totalTax, 2),
            ],
        ];
    }

    /**
     * Generate a sequential quotation number for a newly created quotation.
     */
    protected function generateQuotationNumber(int $id): string
    {
        return 'QT-'.now()->format('Y').'-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }
}
