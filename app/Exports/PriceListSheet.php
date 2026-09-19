<?php

namespace App\Exports;

use App\Models\Branch;
use App\Support\PriceList\PriceRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One worksheet of the price list, laid out the way the source workbook is:
 * SL NO. / CODE / ITEMS / UNIT, then a COST + SR/PR/CR block per branch.
 */
class PriceListSheet implements FromArray, ShouldAutoSize, WithColumnWidths, WithEvents, WithStrictNullComparison, WithTitle
{
    /**
     * The columns each branch contributes: its cost, what its percentages are
     * worked out from, then the rate block.
     *
     * @var list<array{string, string}>
     */
    private const array BRANCH_COLUMNS = [
        ['cost', 'COST'],
        ['rate_basis', 'BASIS'],
        ['sr_discount', 'SR %'],
        ['sr_rate', 'SR'],
        ['sr_rate_with_tax', 'SR+TAX'],
        ['pr_discount', 'PR %'],
        ['pr_rate', 'PR'],
        ['pr_rate_with_tax', 'PR+TAX'],
        ['cr_discount', 'CR %'],
        ['cr_rate', 'CR'],
        ['cr_rate_with_tax', 'CR+TAX'],
    ];

    /**
     * Columns before the first branch block.
     *
     * @var list<string>
     */
    private const array LEADING = ['SL NO.', 'CODE', 'ITEMS', 'UNIT'];

    /**
     * Rows that are category headings rather than products, by row number.
     *
     * @var list<int>
     */
    private array $headingRows = [];

    /**
     * @param  Collection<int, PriceRow>  $rows
     * @param  Collection<int, Branch>  $branches
     */
    public function __construct(
        private string $title,
        private Collection $rows,
        private Collection $branches,
        private bool $groupByCategory = false,
    ) {}

    public function title(): string
    {
        return SheetTitle::sanitise($this->title);
    }

    /**
     * Two header rows: the branch names spanning their block, then the columns.
     * Written as part of the body because a nested WithHeadings array is not
     * offset against FromArray rows.
     *
     * @return array<int, list<string>>
     */
    private function headerRows(): array
    {
        // A1 carries the sheet's title the way the source workbook does. It
        // also has to be non-empty: the writer works out where to resume each
        // 1000-row chunk by checking whether A1 exists.
        $branchRow = array_fill(0, count(self::LEADING), '');
        $branchRow[0] = mb_strtoupper($this->title);
        $columnRow = self::LEADING;

        foreach ($this->branches as $branch) {
            $branchRow[] = $branch->name;

            // The name sits over the first cell of its block; the rest are blank.
            for ($index = 1; $index < count(self::BRANCH_COLUMNS); $index++) {
                $branchRow[] = '';
            }

            foreach (self::BRANCH_COLUMNS as [, $label]) {
                $columnRow[] = $label;
            }
        }

        return [$branchRow, $columnRow];
    }

    /**
     * @return array<int, list<string|float|null>>
     */
    public function array(): array
    {
        $this->headingRows = [];

        $out = $this->headerRows();
        $serial = 0;
        $category = null;
        // The two header rows occupy sheet rows 1 and 2.
        $sheetRow = 2;

        foreach ($this->rows as $row) {
            if ($this->groupByCategory && $row->category !== $category) {
                $category = $row->category;
                $sheetRow++;
                $this->headingRows[] = $sheetRow;
                $out[] = $this->pad([mb_strtoupper($category)]);
            }

            $sheetRow++;
            $out[] = $this->line(++$serial, $row);
        }

        return $out;
    }

    /**
     * Fixed widths for the item columns; the rate columns size themselves.
     *
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return ['A' => 7, 'B' => 16, 'C' => 60, 'D' => 8];
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
     * Lay the house style over the written rows: a title and a tinted banner
     * per branch, a filled caption row, number formats per column, banded
     * category headings and a rule down each branch block. The item columns
     * stay frozen so a wide comparison remains readable while scrolling.
     */
    private function decorate(Worksheet $sheet): void
    {
        $lastColumn = Coordinate::stringFromColumnIndex($this->columnCount());
        $leadingEnd = Coordinate::stringFromColumnIndex(count(self::LEADING));
        $lastRow = $sheet->getHighestRow();

        $sheet->mergeCells("A1:{$leadingEnd}1");
        SheetStyle::title($sheet, "A1:{$leadingEnd}1");
        SheetStyle::header($sheet, "A2:{$lastColumn}2");

        if ($lastRow > 2) {
            SheetStyle::grid($sheet, "A3:{$lastColumn}{$lastRow}");
            SheetStyle::center($sheet, "A3:A{$lastRow}");
            SheetStyle::center($sheet, "D3:D{$lastRow}");
            SheetStyle::wrap($sheet, "C3:C{$lastRow}");
        }

        foreach ($this->branches->values() as $position => $branch) {
            $this->decorateBranchBlock($sheet, $position, $lastRow);
        }

        foreach ($this->headingRows as $row) {
            $sheet->mergeCells("A{$row}:{$lastColumn}{$row}");
            SheetStyle::band($sheet, "A{$row}:{$lastColumn}{$row}");
        }

        $sheet->freezePane(Coordinate::stringFromColumnIndex(count(self::LEADING) + 1).'3');
        SheetStyle::finish($sheet, 1, 2);
    }

    /**
     * Banner, edge rule and number formats for one branch's block.
     */
    private function decorateBranchBlock(Worksheet $sheet, int $position, int $lastRow): void
    {
        $blockWidth = count(self::BRANCH_COLUMNS);
        $start = count(self::LEADING) + 1 + $position * $blockWidth;
        $from = Coordinate::stringFromColumnIndex($start);
        $to = Coordinate::stringFromColumnIndex($start + $blockWidth - 1);

        $sheet->mergeCells("{$from}1:{$to}1");
        SheetStyle::banner($sheet, "{$from}1:{$to}1", $position);
        SheetStyle::blockEdge($sheet, "{$from}1:{$from}{$lastRow}");

        if ($lastRow < 3) {
            return;
        }

        foreach (array_column(self::BRANCH_COLUMNS, 0) as $offset => $key) {
            $column = Coordinate::stringFromColumnIndex($start + $offset);
            $range = "{$column}3:{$column}{$lastRow}";

            if ($key === 'rate_basis') {
                SheetStyle::center($sheet, $range);
            } elseif (str_ends_with($key, '_discount')) {
                SheetStyle::percent($sheet, $range);
            } else {
                SheetStyle::money($sheet, $range, muted: str_ends_with($key, '_with_tax'));
            }
        }
    }

    /**
     * Build one product line.
     *
     * @return list<string|float|null>
     */
    private function line(int $serial, PriceRow $row): array
    {
        $line = [$serial, $row->code, $row->name, $row->unit];

        foreach ($this->branches as $branch) {
            $cells = $row->prices[$branch->id] ?? null;

            foreach (self::BRANCH_COLUMNS as [$key]) {
                $line[] = $key === 'rate_basis'
                    ? $this->basisLabel($cells?->rate_basis)
                    : $this->number($cells?->{$key});
            }
        }

        return $line;
    }

    private function columnCount(): int
    {
        return count(self::LEADING) + $this->branches->count() * count(self::BRANCH_COLUMNS);
    }

    /**
     * Pad a partial row out to the full column count.
     *
     * @param  list<string|float|null>  $values
     * @return list<string|float|null>
     */
    private function pad(array $values): array
    {
        return array_pad($values, $this->columnCount(), null);
    }

    /**
     * Spell out what a branch's percentages are worked out from, so a reader
     * can tell a discount off MRP from a markup on cost at a glance.
     */
    private function basisLabel(?string $basis): ?string
    {
        if ($basis === null) {
            return null;
        }

        return $basis === 'cost' ? 'COST +%' : 'MRP -%';
    }

    /**
     * Write rates as numbers so the sheet stays sortable and sum-able.
     */
    private function number(?string $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
