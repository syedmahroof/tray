<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The house look for exported workbooks, so every sheet reads the same: a
 * navy caption row, hairline borders, right-aligned figures, banded section
 * headings and a page setup that prints to width.
 */
final class SheetStyle
{
    public const string BRAND = '1E3A5F';

    public const string HEADER = '2C4A6E';

    public const string BORDER = 'D5DCE6';

    public const string BAND = 'E8EEF6';

    public const string MUTED = '6B7280';

    public const string ACCENT = '0F766E';

    public const string HIGHLIGHT = 'FFF4E0';

    public const string MONEY = '#,##0.00';

    /** Percentages are stored as 12.5 for 12.5%, so the sign is literal. */
    public const string PERCENT = '0.00"%"';

    /**
     * Tints that tell neighbouring branch blocks apart.
     *
     * @var list<string>
     */
    private const array BANNERS = ['DCE7F3', 'E2F0E3', 'FCEBD5', 'ECE3F5'];

    /**
     * A sheet's title bar.
     */
    public static function title(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => self::fill(self::BRAND),
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
                'indent' => 1,
            ],
        ]);

        $sheet->getRowDimension(self::firstRow($range))->setRowHeight(26);
    }

    /**
     * The column caption row.
     */
    public static function header(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => self::fill(self::HEADER),
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '46658C']],
            ],
        ]);

        $sheet->getRowDimension(self::firstRow($range))->setRowHeight(22);
    }

    /**
     * A banner over one branch's block, tinted by its position.
     */
    public static function banner(Worksheet $sheet, string $range, int $position): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::BRAND]],
            'fill' => self::fill(self::BANNERS[$position % count(self::BANNERS)]),
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
    }

    /**
     * Hairline borders and a readable size across the body of a table.
     */
    public static function grid(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['size' => 10],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER]],
            ],
        ]);
    }

    /**
     * A section heading row, such as a category inside a branch sheet.
     */
    public static function band(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::BRAND]],
            'fill' => self::fill(self::BAND),
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
        ]);

        $sheet->getRowDimension(self::firstRow($range))->setRowHeight(20);
    }

    /**
     * Figures in rupees, right aligned; muted for derived columns.
     */
    public static function money(Worksheet $sheet, string $range, bool $muted = false): void
    {
        $style = $sheet->getStyle($range);
        $style->getNumberFormat()->setFormatCode(self::MONEY);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        if ($muted) {
            $style->getFont()->getColor()->setRGB(self::MUTED);
        }
    }

    /**
     * Percentages, right aligned in the accent colour.
     */
    public static function percent(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getNumberFormat()->setFormatCode(self::PERCENT);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $style->getFont()->getColor()->setRGB(self::ACCENT);
    }

    /**
     * Draw attention to cells worth a second look.
     */
    public static function highlight(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFill()->applyFromArray(self::fill(self::HIGHLIGHT));
    }

    public static function center(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    public static function wrap(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getAlignment()->setWrapText(true);
    }

    /**
     * A firm rule down the left of a column block.
     */
    public static function blockEdge(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getLeft()
            ->setBorderStyle(Border::BORDER_MEDIUM)
            ->getColor()->setRGB(self::BRAND);
    }

    /**
     * The whole look for a plain table with one caption row: styled header,
     * bordered body, frozen caption and a filter on every column.
     */
    public static function table(Worksheet $sheet, string $freezeAt = 'A2'): void
    {
        $lastColumn = $sheet->getHighestColumn();
        $lastRow = $sheet->getHighestRow();

        self::header($sheet, "A1:{$lastColumn}1");

        if ($lastRow > 1) {
            self::grid($sheet, "A2:{$lastColumn}{$lastRow}");
        }

        $sheet->freezePane($freezeAt);
        $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");

        self::finish($sheet, 1, 1);
    }

    /**
     * Hide gridlines and set the sheet to print landscape, one page wide,
     * repeating the caption rows on every page.
     */
    public static function finish(Worksheet $sheet, int $repeatFrom, int $repeatTo): void
    {
        $sheet->setShowGridlines(false);
        $sheet->getTabColor()->setRGB(self::BRAND);

        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($repeatFrom, $repeatTo);
    }

    /**
     * @return array{fillType: string, startColor: array{rgb: string}}
     */
    private static function fill(string $rgb): array
    {
        return ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $rgb]];
    }

    private static function firstRow(string $range): int
    {
        return Coordinate::rangeBoundaries($range)[0][1];
    }
}
