<?php

namespace App\Support\PriceList;

/**
 * Per-worksheet metadata for the company price list: which product category
 * each sheet feeds, the brand it belongs to when the sheet names one, and the
 * few name-column overrides the header-driven parser cannot infer.
 */
class SheetLayouts
{
    /**
     * @var array<string, array{category: string, brand: string|null, name_columns?: list<int>}>
     */
    private const array SHEETS = [
        'SUPPORTS' => ['category' => 'Pipe Supports', 'brand' => null],
        'OTHER ITEMS' => ['category' => 'Fasteners & Screws', 'brand' => null],
        'SS' => ['category' => 'Stainless Steel', 'brand' => null],
        'LONGLAST BRAND' => ['category' => 'Manhole Covers', 'brand' => 'Long Last'],
        'FIBROCAST' => ['category' => 'Manhole Covers', 'brand' => 'Fibrocast'],
        'CABLE TRAY' => ['category' => 'Cable Tray', 'brand' => null],
        'FIBROCAST GRATINGS' => ['category' => 'Gratings', 'brand' => 'Fibrocast', 'name_columns' => [0, 1, 2]],
        'HP STRONG DRAIN' => ['category' => 'Manhole Covers', 'brand' => 'HP Strong Drain'],
        'HOT DIP' => ['category' => 'Pipe Supports', 'brand' => 'Kaptech'],
        'LEADER VALVE' => ['category' => 'Valves', 'brand' => 'Leader'],
        'KG RATE' => ['category' => 'Pipe Supports', 'brand' => 'Kaptech'],
        'WGC' => ['category' => 'Water Gully Covers', 'brand' => 'Fibrocast'],
        'FIBRO AWT' => ['category' => 'Manhole Covers', 'brand' => 'Fibrocast'],
        'RECESS COVER' => ['category' => 'Recess Covers', 'brand' => 'Fibrocast'],
        'FKI MHC' => ['category' => 'Manhole Covers', 'brand' => 'FKI', 'name_columns' => [1]],
        'CI GRATINGS' => ['category' => 'Gratings', 'brand' => null, 'name_columns' => [0]],
        'CORE BIT' => ['category' => 'Tools', 'brand' => null],
        'SOLAR' => ['category' => 'Solar Panel Supports', 'brand' => null],
        'FIBRE EXPERT' => ['category' => 'Manhole Covers', 'brand' => 'Fibre Expert'],
        'SKS VALVE' => ['category' => 'Valves', 'brand' => 'SKS'],
        'CIM VALVE' => ['category' => 'Valves', 'brand' => 'CIM'],
        'FOAM AND TUBES' => ['category' => 'Insulation', 'brand' => 'K Flex'],
    ];

    /**
     * Look up a sheet by name. Sheet names in the workbook carry stray casing
     * and trailing spaces, so matching is done on a normalised key.
     *
     * @return array{category: string, brand: string|null, name_columns?: list<int>}|null
     */
    public static function for(string $sheetName): ?array
    {
        return self::SHEETS[self::key($sheetName)] ?? null;
    }

    /**
     * The name-part column override for a sheet, when it has one.
     *
     * @return list<int>|null
     */
    public static function nameColumnsFor(string $sheetName): ?array
    {
        return self::for($sheetName)['name_columns'] ?? null;
    }

    /**
     * Every category the price list feeds.
     *
     * @return list<string>
     */
    public static function categories(): array
    {
        return array_values(array_unique(array_column(self::SHEETS, 'category')));
    }

    /**
     * Every brand the price list names.
     *
     * @return list<string>
     */
    public static function brands(): array
    {
        return array_values(array_unique(array_filter(array_column(self::SHEETS, 'brand'))));
    }

    private static function key(string $sheetName): string
    {
        return trim(mb_strtoupper((string) preg_replace('/\s+/u', ' ', $sheetName)));
    }
}
