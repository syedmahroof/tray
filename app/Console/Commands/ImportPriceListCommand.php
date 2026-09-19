<?php

namespace App\Console\Commands;

use App\Actions\Products\SaveProductBranchPrice;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\PriceList\PriceListParser;
use App\Support\PriceList\SheetLayouts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportPriceListCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'products:import-price-list
        {file : Path to the price list workbook}
        {--branch=HO : Code of the branch the prices belong to}
        {--dump= : Write the normalised rows to this JSON file instead of importing}
        {--reason= : Reason recorded against every price change}';

    /**
     * @var string
     */
    protected $description = 'Import the company price list workbook into the product catalogue';

    /**
     * The GST slab every sheet in the workbook is priced at.
     */
    private const string TAX_TYPE = 'GST 18%';

    private const int TAX_PERCENTAGE = 18;

    public function handle(PriceListParser $parser, SaveProductBranchPrice $savePrice): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->components->error("No such file: {$file}");

            return self::FAILURE;
        }

        $rows = $this->read($file, $parser);

        if ($rows === []) {
            $this->components->error('No priced rows found in the workbook.');

            return self::FAILURE;
        }

        if ($dump = $this->option('dump')) {
            file_put_contents(
                (string) $dump,
                json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
            );
            $this->components->info(count($rows).' rows written to '.$dump);

            return self::SUCCESS;
        }

        $branch = Branch::query()->where('code', $this->option('branch'))->first();

        if (! $branch) {
            $this->components->error('No branch with code ['.$this->option('branch').'].');

            return self::FAILURE;
        }

        $this->import($rows, $branch, $savePrice);

        return self::SUCCESS;
    }

    /**
     * Read every recognised worksheet and normalise its priced rows.
     *
     * @return list<array<string, mixed>>
     */
    private function read(string $file, PriceListParser $parser): array
    {
        $reader = IOFactory::createReaderForFile($file);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file);

        $items = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $title = $sheet->getTitle();
            $layout = SheetLayouts::for($title);

            if ($layout === null) {
                $this->components->twoColumnDetail($title, '<fg=gray>skipped</>');

                continue;
            }

            $parsed = $parser->parse(
                $sheet->toArray(null, true, false, false),
                SheetLayouts::nameColumnsFor($title),
            );

            foreach ($parsed as $item) {
                $items[] = $item + ['sheet' => $title, 'layout' => $layout];
            }

            $this->components->twoColumnDetail($title, count($parsed).' items');
        }

        $rows = [];

        foreach ($this->withDisplayNames($items) as $item) {
            $rows[] = $this->normalise($item, $item['sheet'], $item['layout']);
        }

        return $rows;
    }

    /**
     * Give every item a display name that is unique across the whole workbook.
     *
     * The group heading prefixes the row's own label. Where that still
     * collides the section heading is prefixed as well — LONGLAST repeats a
     * "RECTANGULAR SIZE" block under both its 3T and 5T tables, and plain,
     * hot-dipped and per-kilo threaded rod share a name across three sheets.
     * Anything still ambiguous falls back to the sheet name.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function withDisplayNames(array $items): array
    {
        $labels = array_map(fn (array $item): string => $this->label($item), $items);
        $counts = array_count_values($labels);

        foreach ($items as $index => $item) {
            $label = $labels[$index];

            if ($counts[$label] > 1 && $item['section'] !== null) {
                $label = $item['section'].' - '.$label;
            }

            $labels[$index] = $label;
        }

        $counts = array_count_values($labels);

        foreach ($items as $index => $item) {
            $items[$index]['display_name'] = $counts[$labels[$index]] > 1
                ? $item['sheet'].' - '.$labels[$index]
                : $labels[$index];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function label(array $item): string
    {
        return $item['group'] === null
            ? (string) $item['name']
            : $item['group'].' - '.$item['name'];
    }

    /**
     * Flatten a parsed item into the shape the importer and the JSON snapshot
     * both consume.
     *
     * @param  array<string, mixed>  $item
     * @param  array{category: string, brand: string|null, name_columns?: list<int>}  $layout
     * @return array<string, mixed>
     */
    private function normalise(array $item, string $sheet, array $layout): array
    {
        $price = [
            'cost' => $item['cost'],
            'mrp' => $item['mrp'],
            'rate_basis' => $item['rate_basis'] ?? 'mrp',
        ];

        foreach (['sr', 'pr', 'cr'] as $tier) {
            $price["{$tier}_discount"] = $item["{$tier}_discount"];
            $price["{$tier}_rate"] = $item["{$tier}_rate"];
            $price["{$tier}_rate_with_tax"] = $item["{$tier}_rate_with_tax"];
        }

        return [
            'import_key' => $this->importKey($sheet, $item),
            'sheet' => $sheet,
            'category' => $layout['category'],
            'brand' => $layout['brand'],
            'name' => $item['display_name'],
            'unit' => $item['unit'],
            'description' => $item['load'] === null ? null : 'Load capacity: '.$item['load'],
            'price' => $price,
        ];
    }

    /**
     * Build the stable identity for a row: readable enough to debug, with a
     * digest of the untouched headings so two rows can never collide.
     *
     * @param  array<string, mixed>  $item
     */
    private function importKey(string $sheet, array $item): string
    {
        $raw = implode('|', [$sheet, $item['section'] ?? '', $item['group'] ?? '', $item['name']]);

        return Str::of($sheet.' '.$item['display_name'])->slug()->limit(200, '')->value()
            .'-'.substr(sha1($raw), 0, 10);
    }

    /**
     * Upsert the categories, brands, products and branch prices.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function import(array $rows, Branch $branch, SaveProductBranchPrice $savePrice): void
    {
        $categories = $this->resolveCategories($rows);
        $brands = $this->resolveBrands($rows);
        $reason = $this->option('reason');

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        // All or nothing: a workbook that trips a constraint half way through
        // must not leave the catalogue partly updated.
        DB::transaction(function () use ($rows, $branch, $categories, $brands, $savePrice, $reason, $bar): void {
            foreach ($rows as $row) {
                $product = Product::withoutGlobalScopes()->firstOrNew(['import_key' => $row['import_key']]);

                $product->fill([
                    'product_category_id' => $categories[$row['category']],
                    'brand_id' => $row['brand'] === null ? null : $brands[$row['brand']],
                    'name' => $row['name'],
                    'unit' => $row['unit'],
                    'description' => $row['description'],
                    'tax_type' => self::TAX_TYPE,
                    'tax_percentage' => self::TAX_PERCENTAGE,
                    // Kept in step with the SR tier until quotations read the
                    // branch price rows directly.
                    'price' => $row['price']['sr_rate_with_tax'],
                    'taxable_amount' => $row['price']['sr_rate'],
                ]);

                $product->code ??= $this->allocateCode($row);
                $product->save();

                $savePrice->handle($product, $branch, $row['price'], null, $reason);

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->components->info(count($rows).' items imported into '.$branch->name.'.');
    }

    /**
     * Derive a product code from the import key so re-imports stay stable and
     * hand-created products keep their own generated codes.
     *
     * @param  array<string, mixed>  $row
     */
    private function allocateCode(array $row): string
    {
        return Str::upper(Str::substr(Str::slug($row['sheet']), 0, 3))
            .'-'.Str::upper(Str::substr(sha1($row['import_key']), 0, 8));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function resolveCategories(array $rows): array
    {
        $ids = [];

        foreach (array_unique(array_column($rows, 'category')) as $name) {
            $ids[$name] = ProductCategory::firstOrCreate(['name' => $name], ['is_active' => true])->id;
        }

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function resolveBrands(array $rows): array
    {
        $ids = [];

        foreach (array_unique(array_filter(array_column($rows, 'brand'))) as $name) {
            $ids[$name] = Brand::firstOrCreate(['name' => $name], ['is_active' => true])->id;
        }

        return $ids;
    }
}
