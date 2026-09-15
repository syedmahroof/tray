<?php

namespace App\Exports;

use App\Models\Branch;
use App\Support\PriceList\PriceRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * A single sheet putting every branch's SR, PR and CR against each other, one
 * row per product, for spotting where branches have drifted apart.
 */
class BranchPriceComparisonSheet implements FromArray, ShouldAutoSize, WithColumnWidths, WithEvents, WithHeadings, WithTitle
{
    /** @var list<array{string, string}> */
    private const array TIERS = [
        ['sr_rate', 'SR'],
        ['pr_rate', 'PR'],
        ['cr_rate', 'CR'],
    ];

    /** @var list<string> */
    private const array LEADING = ['CODE', 'ITEMS', 'CATEGORY', 'BRAND', 'UNIT'];

    /**
     * @param  Collection<int, PriceRow>  $rows
     * @param  Collection<int, Branch>  $branches
     */
    public function __construct(
        private Collection $rows,
        private Collection $branches,
    ) {}

    public function title(): string
    {
        return 'Comparison';
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        $headings = self::LEADING;

        foreach ($this->branches as $branch) {
            foreach (self::TIERS as [, $label]) {
                $headings[] = "{$branch->name} {$label}";
            }
        }

        // How far the dearest branch sits above the cheapest, per tier.
        foreach (self::TIERS as [, $label]) {
            $headings[] = "{$label} spread";
        }

        return $headings;
    }

    /**
     * @return array<int, list<string|float|null>>
     */
    public function array(): array
    {
        return $this->rows->map(function (PriceRow $row): array {
            $line = [$row->code, $row->name, $row->category, $row->brand, $row->unit];
            $byTier = [];

            foreach ($this->branches as $branch) {
                $cells = $row->prices[$branch->id] ?? null;

                foreach (self::TIERS as [$key, $label]) {
                    $value = $cells?->{$key};
                    $line[] = $value === null ? null : (float) $value;

                    if ($value !== null) {
                        $byTier[$label][] = (float) $value;
                    }
                }
            }

            foreach (self::TIERS as [, $label]) {
                $values = $byTier[$label] ?? [];

                $line[] = count($values) > 1
                    ? round(max($values) - min($values), 2)
                    : null;
            }

            return $line;
        })->all();
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return ['A' => 16, 'B' => 60];
    }

    /**
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => $this->decorate($event->sheet->getDelegate()),
        ];
    }

    /**
     * House style, rupee formats on every rate, and the spread columns
     * highlighted since they are what this sheet is for.
     */
    private function decorate(Worksheet $sheet): void
    {
        $first = count(self::LEADING) + 1;
        $rateColumns = $this->branches->count() * count(self::TIERS);
        $lastRow = $sheet->getHighestRow();

        SheetStyle::table($sheet, freezeAt: Coordinate::stringFromColumnIndex($first).'2');

        if ($lastRow < 2) {
            return;
        }

        $from = Coordinate::stringFromColumnIndex($first);
        $to = Coordinate::stringFromColumnIndex($first + $rateColumns + count(self::TIERS) - 1);
        $spreadFrom = Coordinate::stringFromColumnIndex($first + $rateColumns);

        SheetStyle::wrap($sheet, "B2:B{$lastRow}");
        SheetStyle::center($sheet, "E2:E{$lastRow}");
        SheetStyle::money($sheet, "{$from}2:{$to}{$lastRow}");
        SheetStyle::highlight($sheet, "{$spreadFrom}2:{$to}{$lastRow}");
    }
}
