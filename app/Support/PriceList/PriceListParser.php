<?php

namespace App\Support\PriceList;

/**
 * Turns one worksheet of the company price list into normalised item rows.
 *
 * The workbook's 22 sheets use a dozen different column layouts, so rather than
 * hard coding positions the parser reads each sheet's header row and maps
 * columns by their caption. Headers repeat mid-sheet (a new block of sizes, a
 * new thickness) and the later block simply replaces the active map.
 *
 * @phpstan-type ParsedItem array{
 *     section: string|null,
 *     group: string|null,
 *     name: string,
 *     unit: string|null,
 *     load: string|null,
 *     cost: float|null,
 *     mrp: float|null,
 *     rate_basis: string,
 *     sr_discount: float|null, sr_rate: float|null, sr_rate_with_tax: float|null,
 *     pr_discount: float|null, pr_rate: float|null, pr_rate_with_tax: float|null,
 *     cr_discount: float|null, cr_rate: float|null, cr_rate_with_tax: float|null,
 * }
 */
class PriceListParser
{
    /**
     * Header captions mapped to the field they fill. A null target means the
     * column is understood but carries nothing we keep — it still counts as
     * "spoken for" so it can't be mistaken for an unlabelled discount column.
     *
     * @var array<string, string|null>
     */
    private const array HEADER_FIELDS = [
        'SL NO.' => null, 'SL NO' => null, 'NO.' => null, 'NO' => null,
        'QTY' => null, 'BOX QTY' => null, 'MM' => null, 'KG' => null,
        'RATE M2' => null, 'CUTTING CHARGES' => null,

        'ITEMS' => 'name', 'ITEM' => 'name', 'SIZE' => 'name',
        'CLEAR OPEN' => 'name', 'CLEAR OPEN SIZE' => 'name',
        'OUTER SIZE' => 'name', 'INNER SIZE' => 'name',
        'FRAME' => 'name', 'FRAME SIZE' => 'name',
        'THIKNESS' => 'name', 'THICKNESS' => 'name', 'WIDTH X LENGTH (MM)' => 'name',

        'UNIT' => 'unit',
        'LOAD CAPACITY' => 'load',
        'COST' => 'cost',
        'COST + FREIGHT' => 'cost_freight',
        'MRP' => 'mrp', 'MRP W/O TAX' => 'mrp',
        'DISCOUNT' => 'discount',

        'SR' => 'sr_rate', 'PR' => 'pr_rate', 'CR' => 'cr_rate',
        'RATE' => 'sr_rate', 'RATE + GST' => 'sr_rate_with_tax',
        'SR + TAX' => 'sr_rate_with_tax', 'SR + GST' => 'sr_rate_with_tax',
        'PR + TAX' => 'pr_rate_with_tax', 'PR + GST' => 'pr_rate_with_tax',
        'CR + TAX' => 'cr_rate_with_tax', 'CR + GST' => 'cr_rate_with_tax',
    ];

    /**
     * The ex-tax rate fields, in tier order. A row is only an item if it
     * carries at least one of these.
     *
     * @var list<string>
     */
    private const array RATE_FIELDS = ['sr_rate', 'pr_rate', 'cr_rate'];

    /** @var list<string> */
    private const array TIERS = ['sr', 'pr', 'cr'];

    /**
     * Cells whose formula has broken evaluate to these. The workbook carries a
     * few, and they are not part of any product's name.
     */
    private const string ERROR_VALUE = '/^#(REF!|VALUE!|N\/A|DIV\/0!|NAME\?|NULL!|NUM!|GETTING_DATA)$/';

    /**
     * Captions that name a rate tier. Only these can be demoted to a discount
     * when they appear twice — a generic "Rate" column is an intermediate
     * calculation, not a percentage.
     *
     * @var list<string>
     */
    private const array TIER_CAPTIONS = ['SR', 'PR', 'CR'];

    /**
     * Captions that mark a row as a header rather than data.
     *
     * @var list<string>
     */
    private const array HEADER_MARKERS = ['SR', 'SR + TAX', 'SR + GST', 'RATE + GST'];

    /**
     * Parse one worksheet.
     *
     * @param  array<int, array<int, mixed>>  $rows  zero-indexed rows of zero-indexed cells
     * @param  list<int>|null  $nameColumns  overrides the columns the item name is built from
     * @return list<ParsedItem>
     */
    public function parse(array $rows, ?array $nameColumns = null): array
    {
        /** @var array<int, string> $columns */
        $columns = [];
        /** @var list<int> $discountColumns */
        $discountColumns = [];

        $section = null;
        $group = null;
        $pendingTitle = null;
        $items = [];

        foreach ($rows as $cells) {
            if ($this->isHeaderRow($cells)) {
                // A title sitting directly above a header row opens a new
                // section: "LONG LAST MHC - 3T" then its own size table.
                if ($pendingTitle !== null) {
                    $section = $pendingTitle;
                    $group = null;
                    $pendingTitle = null;
                }

                [$columns, $discountColumns] = $this->parseHeader($cells);

                continue;
            }

            if (! $this->hasAnyRate($cells, $columns)) {
                if (($title = $this->joinText($cells)) !== null) {
                    $group = $title;
                    $pendingTitle = $title;
                }

                continue;
            }

            $pendingTitle = null;
            $items[] = $this->buildItem($cells, $columns, $discountColumns, $nameColumns, $section, $group);
        }

        return $items;
    }

    /**
     * @param  array<int, mixed>  $cells
     */
    private function isHeaderRow(array $cells): bool
    {
        foreach ($cells as $value) {
            if (is_string($value) && in_array($this->caption($value), self::HEADER_MARKERS, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map a header row's columns to fields, and work out which columns hold the
     * three discount percentages.
     *
     * @param  array<int, mixed>  $cells
     * @return array{array<int, string>, list<int>}
     */
    private function parseHeader(array $cells): array
    {
        $columns = [];
        $captions = [];
        $declared = [];
        $demoted = [];
        $reserved = [];

        foreach ($cells as $index => $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $reserved[] = $index;
            $caption = $this->caption($value);

            if (! array_key_exists($caption, self::HEADER_FIELDS)) {
                continue;
            }

            $field = self::HEADER_FIELDS[$caption];

            if ($field === null) {
                continue;
            }

            if ($field === 'discount') {
                $declared[] = $index;

                continue;
            }

            // SKS and CIM label their discount columns SR/PR/CR too, ahead of
            // the real rate columns. The later occurrence is the rate, so the
            // earlier one is demoted to a discount — but only when it was a
            // tier caption itself. FIBROCAST GRATINGS has a plain "Rate"
            // column before its SR/PR/CR block, and that is not a percentage.
            if (in_array($field, self::RATE_FIELDS, true) && ($existing = array_search($field, $columns, true)) !== false) {
                if (in_array($caption, self::TIER_CAPTIONS, true)
                    && in_array($captions[$existing], self::TIER_CAPTIONS, true)) {
                    $demoted[] = $existing;
                }

                unset($columns[$existing], $captions[$existing]);
            }

            $columns[$index] = $field;
            $captions[$index] = $caption;
        }

        $discounts = $declared ?: ($demoted ?: $this->inferDiscountColumns($columns, $reserved));

        sort($discounts);

        return [$columns, $discounts];
    }

    /**
     * Most sheets leave the discount columns unlabelled, sitting between the
     * cost/MRP basis and the first rate. Without a basis column there is no
     * reliable anchor, so nothing is inferred.
     *
     * @param  array<int, string>  $columns
     * @param  list<int>  $reserved
     * @return list<int>
     */
    private function inferDiscountColumns(array $columns, array $reserved): array
    {
        $basis = array_keys(array_filter(
            $columns,
            fn (string $field): bool => in_array($field, ['cost', 'cost_freight', 'mrp'], true),
        ));

        $rates = array_keys(array_filter(
            $columns,
            fn (string $field): bool => in_array($field, self::RATE_FIELDS, true),
        ));

        if ($basis === [] || $rates === []) {
            return [];
        }

        $discounts = [];

        for ($index = max($basis) + 1; $index < min($rates); $index++) {
            if (! in_array($index, $reserved, true)) {
                $discounts[] = $index;
            }
        }

        return $discounts;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @param  array<int, string>  $columns
     */
    private function hasAnyRate(array $cells, array $columns): bool
    {
        foreach ($columns as $index => $field) {
            if (in_array($field, self::RATE_FIELDS, true) && $this->number($cells[$index] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, mixed>  $cells
     * @param  array<int, string>  $columns
     * @param  list<int>  $discountColumns
     * @param  list<int>|null  $nameColumns
     * @return ParsedItem
     */
    private function buildItem(
        array $cells,
        array $columns,
        array $discountColumns,
        ?array $nameColumns,
        ?string $section,
        ?string $group,
    ): array {
        /** @var array<string, string|null> $texts */
        $texts = [];
        /** @var array<string, float|null> $numbers */
        $numbers = [];

        foreach ($columns as $index => $field) {
            $value = $cells[$index] ?? null;

            match (true) {
                $field === 'name' => null,
                in_array($field, ['unit', 'load'], true) => $texts[$field] = $this->text($value),
                default => $numbers[$field] = $this->number($value),
            };
        }

        $discounts = [];

        foreach ($discountColumns as $position => $index) {
            if ($position > 2) {
                break;
            }

            $discount = $this->number($cells[$index] ?? null);

            // Stored as fractions in the sheet (0.15), kept as percentages.
            $discounts[self::TIERS[$position]] = $discount === null ? null : round($discount * 100, 2);
        }

        return [
            'section' => $section,
            'group' => $group,
            'name' => $this->buildName($cells, $columns, $nameColumns),
            'unit' => $texts['unit'] ?? null,
            'load' => $texts['load'] ?? null,
            // COST wins over COST + FREIGHT wherever both are present.
            'cost' => $numbers['cost'] ?? $numbers['cost_freight'] ?? null,
            'mrp' => $numbers['mrp'] ?? null,
            // The source sheets take their % off an MRP where they carry one,
            // and put it on cost where they do not.
            'rate_basis' => ($numbers['mrp'] ?? 0) > 0 ? 'mrp' : 'cost',
            'sr_discount' => $discounts['sr'] ?? null,
            'sr_rate' => $numbers['sr_rate'] ?? null,
            'sr_rate_with_tax' => $numbers['sr_rate_with_tax'] ?? null,
            'pr_discount' => $discounts['pr'] ?? null,
            'pr_rate' => $numbers['pr_rate'] ?? null,
            'pr_rate_with_tax' => $numbers['pr_rate_with_tax'] ?? null,
            'cr_discount' => $discounts['cr'] ?? null,
            'cr_rate' => $numbers['cr_rate'] ?? null,
            'cr_rate_with_tax' => $numbers['cr_rate_with_tax'] ?? null,
        ];
    }

    /**
     * Build the item name from the columns the header marked as name parts,
     * falling back to the leading text columns for sheets whose name column
     * carries no caption at all.
     *
     * @param  array<int, mixed>  $cells
     * @param  array<int, string>  $columns
     * @param  list<int>|null  $nameColumns
     */
    private function buildName(array $cells, array $columns, ?array $nameColumns): string
    {
        if ($nameColumns === null) {
            $nameColumns = array_keys(array_filter($columns, fn (string $field): bool => $field === 'name'));

            if ($nameColumns === []) {
                // Everything to the left of the first mapped column, text only:
                // a bare number there is an unlabelled cost, not part of a name.
                $nameColumns = $columns === []
                    ? []
                    : array_values(array_filter(
                        array_keys($cells),
                        fn (int $index): bool => $index < min(array_keys($columns)) && is_string($cells[$index] ?? null),
                    ));
            }
        }

        $parts = [];

        foreach ($nameColumns as $index) {
            $value = $cells[$index] ?? null;

            if (($text = $this->text($value)) !== null) {
                $parts[] = $text;
            } elseif (is_numeric($value)) {
                $parts[] = $this->collapse((string) (0 + $value));
            }
        }

        return implode(' / ', $parts);
    }

    /**
     * Join a structural row's text cells into a single heading.
     *
     * @param  array<int, mixed>  $cells
     */
    private function joinText(array $cells): ?string
    {
        $parts = [];

        foreach ($cells as $value) {
            if (($text = $this->text($value)) !== null) {
                $parts[] = $text;
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * Normalise a header caption for lookup: uppercased, whitespace collapsed
     * (captions wrap onto two lines), and "+" spaced out consistently.
     */
    private function caption(string $value): string
    {
        return $this->collapse(str_replace('+', ' + ', mb_strtoupper($value)));
    }

    private function collapse(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = $this->collapse($value);

        return $text === '' || $this->isError($text) ? null : $text;
    }

    private function isError(string $value): bool
    {
        return preg_match(self::ERROR_VALUE, $value) === 1;
    }

    /**
     * Read a numeric cell. Zero means "not priced" throughout the workbook, so
     * it is treated as absent rather than as a real price of nothing.
     */
    private function number(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $number === 0.0 ? null : $number;
    }
}
