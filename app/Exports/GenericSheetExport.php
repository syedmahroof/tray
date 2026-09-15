<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;

class GenericSheetExport implements FromArray, ShouldAutoSize, WithEvents, WithHeadings
{
    /**
     * @param  list<string>  $headings
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function __construct(
        private array $headings,
        private array $rows,
    ) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->rows;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->headings;
    }

    /**
     * Apply the house table style: filled caption row, bordered body, frozen
     * caption and a filter on every column.
     *
     * @return array<string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => fn (AfterSheet $event) => SheetStyle::table($event->sheet->getDelegate()),
        ];
    }
}
