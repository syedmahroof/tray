<?php

use App\Actions\Products\SaveProductBranchPrice;
use App\Exports\PriceListExport;
use App\Exports\PriceListSheet;
use App\Exports\SheetStyle;
use App\Exports\SheetTitle;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\PriceList\BranchPriceMatrix;
use App\Support\PriceList\PriceRow;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');

    $this->kochi = Branch::factory()->create(['name' => 'Kochi', 'code' => 'KOC']);
    $this->calicut = Branch::factory()->create(['name' => 'Calicut', 'code' => 'CAL']);

    $this->supports = ProductCategory::factory()->create(['name' => 'Pipe Supports']);
    $this->valves = ProductCategory::factory()->create(['name' => 'Valves']);

    $this->channel = Product::factory()->create([
        'name' => 'Slotted Channel 1.2 MM',
        'code' => 'SUP-00001',
        'unit' => 'Mtr',
        'product_category_id' => $this->supports->id,
        'tax_percentage' => 18,
    ]);

    $valve = Product::factory()->create([
        'name' => 'Ball Valve 15 MM',
        'code' => 'VAL-00001',
        'unit' => 'Nos',
        'product_category_id' => $this->valves->id,
        'tax_percentage' => 18,
    ]);

    $save = app(SaveProductBranchPrice::class);
    $save->handle($this->channel, $this->kochi, ['cost' => 58, 'sr_discount' => 25, 'sr_rate' => 79.75, 'pr_rate' => 86.45, 'cr_rate' => 98.50]);
    $save->handle($this->channel, $this->calicut, ['cost' => 58, 'sr_rate' => 85, 'pr_rate' => 90, 'cr_rate' => 100]);
    $save->handle($valve, $this->kochi, ['mrp' => 628, 'sr_rate' => 314]);
});

/**
 * Build the export rows the way the controller does.
 *
 * @return array{Collection<int, PriceRow>, Collection<int, Branch>}
 */
function exportInput(): array
{
    $matrix = app(BranchPriceMatrix::class);
    $branches = Branch::orderBy('id')->get();
    $products = Product::with(['productCategory', 'brand'])
        ->orderBy('product_category_id')->orderBy('name')->get();

    return [$matrix->map($products, $branches), $branches];
}

test('the price list export downloads a category sheet per category', function () {
    Excel::fake();

    $this->actingAs($this->admin)->get(route('products.price-list.export'))->assertOk();

    Excel::assertDownloaded('price-list.xlsx', function (PriceListExport $export): bool {
        $titles = array_map(fn (object $sheet): string => $sheet->title(), $export->sheets());

        return $titles === ['Pipe Supports', 'Valves'];
    });
});

test('the all-branch export has a sheet per branch plus a comparison', function () {
    Excel::fake();

    $this->actingAs($this->admin)->get(route('products.price-list.export-branches'))->assertOk();

    Excel::assertDownloaded('price-list-all-branches.xlsx', function (PriceListExport $export): bool {
        $titles = array_map(fn (object $sheet): string => $sheet->title(), $export->sheets());

        return $titles === ['Calicut', 'Kochi', 'Comparison'];
    });
});

test('a category sheet carries both header rows and the workbook columns', function () {
    [$rows, $branches] = exportInput();

    $sheet = new PriceListSheet('Pipe Supports', $rows->where('category', 'Pipe Supports')->values(), $branches);
    $data = $sheet->array();

    // Row 1: the sheet title, then each branch over its block.
    expect($data[0][0])->toBe('PIPE SUPPORTS');
    expect($data[0][4])->toBe('Kochi');
    expect($data[0][14])->toBe('Calicut');

    // Row 2: the workbook's own column captions, repeated per branch.
    expect($data[1])->toBe([
        'SL NO.', 'CODE', 'ITEMS', 'UNIT',
        'COST', 'SR %', 'SR', 'SR+TAX', 'PR %', 'PR', 'PR+TAX', 'CR %', 'CR', 'CR+TAX',
        'COST', 'SR %', 'SR', 'SR+TAX', 'PR %', 'PR', 'PR+TAX', 'CR %', 'CR', 'CR+TAX',
    ]);

    // Row 3: the product, with both branches side by side.
    expect($data[2][1])->toBe('SUP-00001');
    expect($data[2][2])->toBe('Slotted Channel 1.2 MM');
    expect($data[2][3])->toBe('Mtr');
    expect($data[2][5])->toBe(25.0);
    expect($data[2][6])->toBe(79.75);
    expect($data[2][7])->toBe(94.11);
    expect($data[2][8])->toBeNull();
    expect($data[2][16])->toBe(85.0);
});

test('an unpriced branch leaves its block empty rather than shifting columns', function () {
    [$rows, $branches] = exportInput();

    $sheet = new PriceListSheet('Valves', $rows->where('category', 'Valves')->values(), $branches);
    $line = $sheet->array()[2];

    expect($line[6])->toBe(314.0);
    // Calicut has no price for this valve.
    expect(array_slice($line, 14, 10))->toBe(array_fill(0, 10, null));
});

test('a per-branch sheet groups its rows by category', function () {
    [$rows, $branches] = exportInput();

    $sheet = new PriceListSheet('Kochi', $rows, collect([$branches->first()]), groupByCategory: true);
    $data = $sheet->array();

    expect($data[2][0])->toBe('PIPE SUPPORTS');
    expect($data[3][2])->toBe('Slotted Channel 1.2 MM');
    expect($data[4][0])->toBe('VALVES');
    expect($data[5][2])->toBe('Ball Valve 15 MM');
});

test('the comparison sheet reports the spread between branches', function () {
    Excel::fake();

    $this->actingAs($this->admin)->get(route('products.price-list.export-branches'))->assertOk();

    Excel::assertDownloaded('price-list-all-branches.xlsx', function (PriceListExport $export): bool {
        $sheets = $export->sheets();
        $comparison = end($sheets);
        $headings = $comparison->headings();
        $line = $comparison->array()[0];

        expect($headings)->toContain('Kochi SR', 'Calicut SR', 'SR spread');
        // 85.00 in Calicut against 79.75 in Kochi.
        expect($line[array_search('SR spread', $headings, true)])->toBe(5.25);

        return true;
    });
});

test('every row survives the writer chunking at a thousand rows', function () {
    // The writer appends in 1000-row chunks and resumes by checking A1, so a
    // blank first cell used to make the second chunk overwrite the first.
    Product::factory()->count(1100)->create(['product_category_id' => $this->supports->id]);

    [$rows, $branches] = exportInput();
    $sheet = new PriceListSheet('Pipe Supports', $rows, $branches, groupByCategory: true);
    $data = $sheet->array();

    expect($data[0][0])->not->toBe('');
    expect(count($data))->toBe(1102 + 2 + 2);
});

/**
 * Render an export to a real xlsx and load it back, so styling can be read.
 */
function renderWorkbook(object $export): Spreadsheet
{
    $path = tempnam(sys_get_temp_dir(), 'price-list').'.xlsx';
    file_put_contents($path, Excel::raw($export, ExcelWriter::XLSX));

    try {
        return IOFactory::load($path);
    } finally {
        unlink($path);
    }
}

test('a branch sheet carries the house style', function () {
    [$rows, $branches] = exportInput();
    $sheet = renderWorkbook(new PriceListExport($rows, $branches, sheetPerBranch: true))
        ->getSheetByName('Kochi');

    // Caption row filled and the item columns frozen.
    expect($sheet->getStyle('A2')->getFill()->getStartColor()->getRGB())->toBe(SheetStyle::HEADER);
    expect($sheet->getFreezePane())->toBe('E3');

    // Row 3 is the category heading, banded across the whole sheet.
    expect($sheet->getMergeCells())->toHaveKey('A3:N3');
    expect($sheet->getStyle('A3')->getFill()->getStartColor()->getRGB())->toBe(SheetStyle::BAND);

    // Row 4 is the channel: COST in E, SR % in F, SR in G.
    expect($sheet->getCell('F4')->getValue())->toEqual(25);
    expect($sheet->getStyle('F4')->getNumberFormat()->getFormatCode())->toBe(SheetStyle::PERCENT);
    expect($sheet->getStyle('G4')->getNumberFormat()->getFormatCode())->toBe(SheetStyle::MONEY);
});

test('the comparison sheet is filterable and highlights the spread', function () {
    [$rows, $branches] = exportInput();
    $sheet = renderWorkbook(new PriceListExport($rows, $branches, sheetPerBranch: true))
        ->getSheetByName('Comparison');

    // Five item columns, three rates for each of two branches, three spreads.
    expect($sheet->getAutoFilter()->getRange())->toBe('A1:N3');
    expect($sheet->getFreezePane())->toBe('F2');
    expect($sheet->getStyle('L2')->getFill()->getStartColor()->getRGB())->toBe(SheetStyle::HIGHLIGHT);
    expect($sheet->getStyle('F2')->getNumberFormat()->getFormatCode())->toBe(SheetStyle::MONEY);
});

test('sheet titles are made safe for excel', function () {
    expect(SheetTitle::sanitise('Valves / Fittings'))->toBe('Valves Fittings');
    expect(SheetTitle::sanitise('A really long category name that exceeds the limit'))
        ->toHaveLength(31);
    expect(SheetTitle::sanitise('   '))->toBe('Sheet');
});

test('the exports are gated on the price permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('products.view');

    $this->actingAs($user)->get(route('products.price-list.export'))->assertForbidden();
    $this->actingAs($user)->get(route('products.price-list.export-branches'))->assertForbidden();
});

test('the exports honour the active filters', function () {
    Excel::fake();

    $this->actingAs($this->admin)
        ->get(route('products.price-list.export', ['product_category_ids' => [$this->valves->id]]))
        ->assertOk();

    Excel::assertDownloaded('price-list.xlsx', function (PriceListExport $export): bool {
        $titles = array_map(fn (object $sheet): string => $sheet->title(), $export->sheets());

        return $titles === ['Valves'];
    });
});

test('the export only shows branches the user can reach', function () {
    Excel::fake();

    $user = User::factory()->create(['branch_id' => $this->kochi->id]);
    $user->assignRole('Sales Executive');
    $user->branches()->sync([$this->kochi->id]);

    $this->actingAs($user)
        ->get(route('products.price-list.export-branches', ['branch_ids' => [$this->calicut->id]]))
        ->assertOk();

    Excel::assertDownloaded('price-list-all-branches.xlsx', function (PriceListExport $export): bool {
        $titles = array_map(fn (object $sheet): string => $sheet->title(), $export->sheets());

        return $titles === ['Kochi', 'Comparison'];
    });
});
