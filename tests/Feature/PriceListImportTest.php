<?php

use App\Actions\Products\SaveProductBranchPrice;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductBranchPrice;
use App\Models\ProductCategory;
use App\Models\ProductPriceHistory;
use App\Support\PriceList\PriceListParser;
use App\Support\PriceList\SheetLayouts;
use Database\Seeders\ProductPriceSeeder;

/**
 * A trimmed snapshot standing in for the full workbook: three rows each from
 * five sheets, chosen to cover the awkward shapes.
 */
function fixturePath(): string
{
    return base_path('tests/Fixtures/price-list.json');
}

function seedFixture(?Branch $branch = null): Branch
{
    $branch ??= Branch::factory()->create(['code' => 'HO']);

    app(ProductPriceSeeder::class)->run(fixturePath(), $branch->code);

    return $branch;
}

test('the seeder creates products, categories, brands and branch prices', function () {
    $branch = seedFixture();

    expect(Product::count())->toBe(15);
    expect(ProductBranchPrice::withoutGlobalScopes()->where('branch_id', $branch->id)->count())->toBe(15);
    expect(ProductCategory::pluck('name')->all())
        ->toEqualCanonicalizing(['Pipe Supports', 'Tools', 'Solar Panel Supports', 'Water Gully Covers', 'Valves']);
    expect(Brand::pluck('name')->all())->toContain('Fibrocast', 'Leader');
});

test('imported rates match the workbook exactly', function () {
    $branch = seedFixture();

    $product = Product::where('name', 'SLOTTED CHANNEL - 1.2 MM (Electrical)')->sole();
    $price = $product->priceFor($branch->id);

    expect($price->cost)->toBe('58.0000');
    expect($price->rate('SR'))->toBe('79.75');
    expect($price->rate('PR'))->toBe('86.45');
    expect($price->rate('CR'))->toBe('98.50');
    expect($price->discount('SR'))->toBe('30.00');
});

test('tax inclusive rates equal the ex-tax rate plus eighteen percent', function () {
    $branch = seedFixture();

    ProductBranchPrice::withoutGlobalScopes()->with('product')->get()
        ->each(function (ProductBranchPrice $price): void {
            foreach (['SR', 'PR', 'CR'] as $tier) {
                if ($price->rate($tier) === null) {
                    continue;
                }

                expect((float) $price->rateWithTax($tier))
                    ->toEqualWithDelta((float) $price->rate($tier) * 1.18, 0.01);
            }
        });
});

test('a sheet with a single rate column leaves the other tiers empty', function () {
    $branch = seedFixture();

    $price = Product::where('name', 'like', 'CORE BIT 51%')->sole()->priceFor($branch->id);

    expect($price->rate('SR'))->toBe('890.00');
    expect($price->rateWithTax('SR'))->toBe('1050.20');
    expect($price->rate('PR'))->toBeNull();
    expect($price->rate('CR'))->toBeNull();
});

test('a sheet with no tax columns has all three derived', function () {
    $branch = seedFixture();

    $price = Product::where('name', 'like', '%END CLAMP%')->sole()->priceFor($branch->id);

    expect($price->rateWithTax('SR'))->toBe('18.79');
    expect($price->rateWithTax('PR'))->toBe('20.73');
    expect($price->rateWithTax('CR'))->toBe('22.80');
});

test('load capacity is carried into the description', function () {
    seedFixture();

    expect(Product::where('name', 'like', '%WATER GULLY COVER%')->first()->description)
        ->toBe('Load capacity: Pedestrian');
});

test('every imported product gets a unique code and import key', function () {
    seedFixture();

    expect(Product::whereNull('code')->count())->toBe(0);
    expect(Product::pluck('code')->unique())->toHaveCount(15);
    expect(Product::pluck('import_key')->unique())->toHaveCount(15);
});

test('re-seeding updates in place and logs nothing when nothing changed', function () {
    $branch = seedFixture();

    seedFixture($branch);

    expect(Product::count())->toBe(15);
    expect(ProductBranchPrice::withoutGlobalScopes()->count())->toBe(15);
    expect(ProductPriceHistory::count())->toBe(0);
});

test('re-seeding after a manual price change logs the move back', function () {
    $branch = seedFixture();
    $product = Product::where('name', 'like', 'CORE BIT 51%')->sole();

    app(SaveProductBranchPrice::class)
        ->handle($product, $branch, ['sr_rate' => 950], null, 'Manual revision');

    seedFixture($branch);

    expect($product->priceFor($branch->id)->rate('SR'))->toBe('890.00');
    expect(ProductPriceHistory::where('field', 'sr_rate')->count())->toBe(2);
});

test('the seeder fails loudly when the branch does not exist', function () {
    expect(fn () => app(ProductPriceSeeder::class)->run(fixturePath(), 'NOPE'))
        ->toThrow(RuntimeException::class, 'No branch with code [NOPE]');

    expect(Product::count())->toBe(0);
});

test('the parser reads a repeated header block and resets the column map', function () {
    // CABLE TRAY repeats its header for every thickness; the second block's
    // columns must replace the first block's, not merge with them.
    $rows = [
        ['SL NO.', 'SIZE', 'Qty', 'Unit', 'COST', null, null, null, null, 'SR', 'SR+TAX', 'PR', 'PR+TAX', 'CR', 'CR+TAX'],
        [1, '50x50X1', 1, 'Mtr', 120, null, null, null, null, 138.6, 163.548, 144.9, 170.982, 151.2, 178.416],
        ['CI GRATINGS'],
        [null, null, 'SR', 'SR+TAX', 'PR', 'PR+TAX', 'CR', 'CR+TAX'],
        ['CI GRATINGS 4"x4"', 44, 61.6, 72.688, 70.4, 83.072, 79.2, 93.456],
    ];

    $items = app(PriceListParser::class)->parse($rows);

    expect($items)->toHaveCount(2);
    expect($items[0]['name'])->toBe('50x50X1');
    expect($items[0]['cost'])->toBe(120.0);
    expect($items[1]['name'])->toBe('CI GRATINGS 4"x4"');
    expect($items[1]['sr_rate'])->toBe(61.6);
    // The title sits directly above the second header, so it opens a section.
    expect($items[1]['section'])->toBe('CI GRATINGS');
});

test('the parser demotes a repeated SR column to a discount', function () {
    // SKS and CIM label their discount columns SR/PR/CR ahead of the rates.
    $rows = [
        ['SL NO.', 'ITEMS', 'MRP', 'SR', 'PR', 'CR', 'UNIT', 'SR', 'SR+TAX', 'PR', 'PR+TAX', 'CR', 'CR+TAX'],
        [null, '3/8"- 10 MM', 296, 0.35, 0.3, 0.25, 'Nos', 192.4, 227.032, 207.2, 244.496, 222, 261.96],
    ];

    $item = app(PriceListParser::class)->parse($rows)[0];

    expect($item['mrp'])->toBe(296.0);
    expect($item['sr_discount'])->toBe(35.0);
    expect($item['cr_discount'])->toBe(25.0);
    expect($item['sr_rate'])->toBe(192.4);
    expect($item['unit'])->toBe('Nos');
});

test('the parser treats a zero rate as unpriced rather than free', function () {
    $rows = [
        ['SL NO.', 'ITEMS', 'UNIT', 'COST', null, null, null, 'SR', 'SR+TAX', 'PR', 'PR+TAX', 'CR', 'CR+TAX'],
        [null, 'Unpriced item', 'Nos', 10, 0.25, 0.35, 0.5, 0, 0, 0, 0, 0, 0],
        [null, 'Priced item', 'Nos', 10, 0.25, 0.35, 0.5, 12, 14.16, 13, 15.34, 14, 16.52],
    ];

    $items = app(PriceListParser::class)->parse($rows);

    expect($items)->toHaveCount(1);
    expect($items[0]['name'])->toBe('Priced item');
});

test('sheet layouts cover every priced sheet in the workbook', function () {
    expect(SheetLayouts::for('FIBROCAST '))->not->toBeNull();
    expect(SheetLayouts::for('fibre expert'))->not->toBeNull();
    expect(SheetLayouts::for('Sheet1'))->toBeNull();
    expect(SheetLayouts::nameColumnsFor('FKI MHC'))->toBe([1]);
    expect(SheetLayouts::nameColumnsFor('SUPPORTS'))->toBeNull();
});
