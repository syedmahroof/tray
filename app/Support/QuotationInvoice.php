<?php

namespace App\Support;

use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * A quotation worked out the way the printed tax invoice states it: numbered
 * lines, a tax block, the rounded total, the HSN summary underneath and the
 * figures spelled out in words.
 */
final readonly class QuotationInvoice
{
    public function __construct(private Quotation $quotation) {}

    /**
     * The printed lines, in the column order the invoice uses.
     *
     * Each line also carries its share of the quotation's discount, stated as
     * the percentage off list and the price and total that leaves.
     *
     * @return list<array{sl: int, description: string, detail: string|null, hsn: string|null, quantity: float, unit: string, rate: float, amount: float, discount_percent: float, net_rate: float, net_amount: float}>
     */
    public function lines(): array
    {
        $discountPercent = $this->discountPercent();

        return array_values($this->quotation->items
            ->values()
            ->map(function (QuotationItem $item, int $index) use ($discountPercent): array {
                $rate = (float) $item->unit_price;
                $netRate = round($rate * (1 - $discountPercent / 100), 2);
                $productName = (string) $item->product?->name;
                $description = $item->description ?: $productName;

                return [
                    'sl' => $index + 1,
                    'description' => $description,
                    'detail' => filled($item->product?->description) && $item->product->description !== $description
                        ? $item->product->description
                        : null,
                    'hsn' => $item->hsn_code,
                    'quantity' => (float) $item->quantity,
                    'unit' => $this->unitFor($item),
                    'rate' => $rate,
                    'amount' => round((float) $item->quantity * $rate, 2),
                    'discount_percent' => $discountPercent,
                    'net_rate' => $netRate,
                    'net_amount' => round((float) $item->quantity * $rate * (1 - $discountPercent / 100), 2),
                ];
            })
            ->all());
    }

    public function subtotal(): float
    {
        return (float) $this->quotation->subtotal;
    }

    public function discount(): float
    {
        return (float) $this->quotation->discount;
    }

    /**
     * The quotation's discount as a percentage off the list total.
     */
    public function discountPercent(): float
    {
        $subtotal = $this->subtotal();

        return $subtotal > 0 ? round($this->discount() / $subtotal * 100, 2) : 0.0;
    }

    /**
     * What the tax is charged on: the lines less any discount.
     */
    public function taxableValue(): float
    {
        return round($this->subtotal() - $this->discount(), 2);
    }

    /**
     * The tax lines this quotation carries, as the invoice states them.
     *
     * @return list<array{label: string, rate: float, amount: float}>
     */
    public function taxLines(): array
    {
        $rate = (float) $this->quotation->tax_percent;

        if ($this->isInterState()) {
            $amount = (float) $this->quotation->igst_amount;

            return $amount > 0 ? [['label' => 'IGST OUT '.$this->percent($rate).'%', 'rate' => $rate, 'amount' => $amount]] : [];
        }

        $half = round($rate / 2, 2);
        $lines = [];

        foreach ([['CGST', (float) $this->quotation->cgst_amount], ['SGST', (float) $this->quotation->sgst_amount]] as [$label, $amount]) {
            if ($amount > 0) {
                $lines[] = ['label' => "{$label} OUT ".$this->percent($half).'%', 'rate' => $half, 'amount' => $amount];
            }
        }

        return $lines;
    }

    public function taxTotal(): float
    {
        return round(array_sum(array_column($this->taxLines(), 'amount')), 2);
    }

    /**
     * What the stored total is short of, or over, a whole rupee.
     */
    public function roundOff(): float
    {
        return round($this->total() - (float) $this->quotation->total, 2);
    }

    /**
     * Invoices are settled in whole rupees, so the printed total is rounded.
     */
    public function total(): float
    {
        return round((float) $this->quotation->total);
    }

    public function totalQuantity(): float
    {
        return round(array_sum(array_column($this->lines(), 'quantity')), 3);
    }

    /**
     * The unit to print against the total quantity, when every line shares one.
     */
    public function totalUnit(): ?string
    {
        $units = array_unique(array_column($this->lines(), 'unit'));

        return count($units) === 1 ? reset($units) : null;
    }

    /**
     * The tax summary the invoice carries below its totals, one row per HSN.
     *
     * Taxable values are the lines' own, less their share of any discount, so
     * the summary always adds up to the totals above it.
     *
     * @return list<array{hsn: string, taxable: float, rate: float, cgst: float, sgst: float, igst: float, tax: float}>
     */
    public function hsnSummary(): array
    {
        $subtotal = $this->subtotal();
        $share = $subtotal > 0 ? $this->taxableValue() / $subtotal : 1.0;
        $groups = [];

        foreach ($this->quotation->items as $item) {
            $hsn = $item->hsn_code ?: '—';
            $rate = (float) $item->tax_percentage;
            $key = $hsn.'|'.$rate;

            $groups[$key] ??= ['hsn' => $hsn, 'taxable' => 0.0, 'rate' => $rate];
            $groups[$key]['taxable'] += (float) $item->quantity * (float) $item->unit_price * $share;
        }

        return array_values(array_map(function (array $group): array {
            $taxable = round($group['taxable'], 2);
            $rate = $group['rate'];

            if ($this->isInterState()) {
                $igst = round($taxable * $rate / 100, 2);

                return [...$group, 'taxable' => $taxable, 'cgst' => 0.0, 'sgst' => 0.0, 'igst' => $igst, 'tax' => $igst];
            }

            $half = round($taxable * $rate / 200, 2);

            return [...$group, 'taxable' => $taxable, 'cgst' => $half, 'sgst' => $half, 'igst' => 0.0, 'tax' => round($half * 2, 2)];
        }, $groups));
    }

    /**
     * The whole document as plain data, for the page that shows it on screen.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lines' => $this->lines(),
            'subtotal' => $this->subtotal(),
            'discount' => $this->discount(),
            'discount_percent' => $this->discountPercent(),
            'taxable_value' => $this->taxableValue(),
            'tax_lines' => $this->taxLines(),
            'tax_total' => $this->taxTotal(),
            'round_off' => $this->roundOff(),
            'total' => $this->total(),
            'total_quantity' => $this->totalQuantity(),
            'total_unit' => $this->totalUnit(),
            'hsn_summary' => $this->hsnSummary(),
            'inter_state' => $this->isInterState(),
            'amount_in_words' => self::inWords($this->total()),
            'tax_in_words' => self::inWords($this->taxTotal()),
        ];
    }

    public function isInterState(): bool
    {
        return $this->quotation->supply_type === 'inter';
    }

    /**
     * Spell an amount out the way the invoice states it, for example
     * "INR Three Hundred Seventy Six Only".
     */
    public static function inWords(float $amount): string
    {
        $rupees = (int) floor(abs($amount));
        $paise = (int) round((abs($amount) - $rupees) * 100);

        $words = 'INR '.self::spell($rupees);

        if ($paise > 0) {
            $words .= ' and '.self::spell($paise).' paise';
        }

        return $words.' Only';
    }

    /**
     * Render a quantity without trailing zeros, the way a line reads it.
     */
    public function quantity(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * Render a percentage without trailing zeros: 9, not 9.00.
     */
    public function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * The unit a line is quoted in, falling back to the catalogue's default.
     */
    private function unitFor(QuotationItem $item): string
    {
        return $item->product?->unit ?: 'nos';
    }

    /**
     * Spell a whole number out in title case, without the hyphens the
     * formatter puts between tens and units.
     */
    private static function spell(int $number): string
    {
        return Str::title(str_replace('-', ' ', (string) Number::spell($number, 'en_IN')));
    }
}
