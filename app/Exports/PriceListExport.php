<?php

namespace App\Exports;

use App\Models\Branch;
use App\Support\PriceList\PriceRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * The price list as a workbook.
 *
 * Grouped by category it mirrors the source spreadsheet — one sheet per
 * category, every selected branch side by side. Grouped by branch it gives
 * each branch its own sheet plus a comparison sheet across all of them.
 */
class PriceListExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int, PriceRow>  $rows
     * @param  Collection<int, Branch>  $branches
     */
    public function __construct(
        private Collection $rows,
        private Collection $branches,
        private bool $sheetPerBranch = false,
    ) {}

    /**
     * @return list<BranchPriceComparisonSheet|PriceListSheet>
     */
    public function sheets(): array
    {
        if ($this->rows->isEmpty()) {
            return [new PriceListSheet('Price List', $this->rows, $this->branches)];
        }

        return $this->sheetPerBranch ? $this->branchSheets() : $this->categorySheets();
    }

    /**
     * One sheet per category, each carrying every selected branch.
     *
     * @return list<PriceListSheet>
     */
    private function categorySheets(): array
    {
        return array_values(
            $this->rows
                ->groupBy(fn (PriceRow $row): string => $row->category)
                ->map(fn (Collection $rows, string $category): PriceListSheet => new PriceListSheet(
                    $category,
                    $rows->values(),
                    $this->branches,
                ))
                ->all()
        );
    }

    /**
     * One sheet per branch, grouped by category inside, then the comparison.
     *
     * @return list<BranchPriceComparisonSheet|PriceListSheet>
     */
    private function branchSheets(): array
    {
        $sheets = array_values(
            $this->branches
                ->map(fn (Branch $branch): PriceListSheet => new PriceListSheet(
                    $branch->name,
                    $this->rows,
                    collect([$branch]),
                    groupByCategory: true,
                ))
                ->all()
        );

        $sheets[] = new BranchPriceComparisonSheet($this->rows, $this->branches);

        return $sheets;
    }
}
