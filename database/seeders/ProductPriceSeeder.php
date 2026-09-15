<?php

namespace Database\Seeders;

use App\Actions\Products\SaveProductBranchPrice;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeds the product catalogue and its opening branch prices from the snapshot
 * of the company price list workbook.
 *
 * The snapshot is produced by `products:import-price-list --dump=`, so seeding
 * needs neither the spreadsheet nor a spreadsheet reader.
 */
class ProductPriceSeeder extends Seeder
{
    /**
     * The GST slab every sheet in the workbook is priced at.
     */
    private const string TAX_TYPE = 'GST 18%';

    private const int TAX_PERCENTAGE = 18;

    public function __construct(
        private readonly SaveProductBranchPrice $savePrice,
    ) {}

    /**
     * The snapshot the seeder reads by default.
     */
    public static function defaultPath(): string
    {
        return database_path('data/price-list-2026-04-30.json');
    }

    /**
     * @throws RuntimeException when the snapshot or the target branch is missing
     */
    public function run(?string $path = null, string $branchCode = 'HO'): void
    {
        $path ??= self::defaultPath();

        if (! is_file($path)) {
            throw new RuntimeException("Price list snapshot not found at [{$path}].");
        }

        $branch = Branch::query()->where('code', $branchCode)->firstOr(function () use ($branchCode): never {
            throw new RuntimeException("No branch with code [{$branchCode}] to price against.");
        });

        /** @var list<array<string, mixed>> $rows */
        $rows = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        $categories = $this->findOrCreateIds(ProductCategory::class, array_column($rows, 'category'));
        $brands = $this->findOrCreateIds(Brand::class, array_filter(array_column($rows, 'brand')));

        DB::transaction(function () use ($rows, $branch, $categories, $brands): void {
            foreach ($rows as $row) {
                $this->seedRow($row, $branch, $categories, $brands);
            }
        });

    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $categories
     * @param  array<string, int>  $brands
     */
    private function seedRow(array $row, Branch $branch, array $categories, array $brands): void
    {
        $product = Product::withoutGlobalScopes()->firstOrNew(['import_key' => $row['import_key']]);

        $product->fill([
            'product_category_id' => $categories[$row['category']],
            'brand_id' => $row['brand'] === null ? null : $brands[$row['brand']],
            'name' => $row['name'],
            'unit' => $row['unit'],
            'description' => $row['description'],
            'tax_type' => self::TAX_TYPE,
            'tax_percentage' => self::TAX_PERCENTAGE,
            'price' => $row['price']['sr_rate_with_tax'],
            'taxable_amount' => $row['price']['sr_rate'],
        ]);

        $product->code ??= $this->code($row);
        $product->save();

        $this->savePrice->handle($product, $branch, $row['price']);
    }

    /**
     * Derive the same product code the importer would, so seeding and
     * importing the same workbook produce identical catalogues.
     *
     * @param  array<string, mixed>  $row
     */
    private function code(array $row): string
    {
        return mb_strtoupper(mb_substr(str($row['sheet'])->slug()->value(), 0, 3))
            .'-'.mb_strtoupper(mb_substr(sha1($row['import_key']), 0, 8));
    }

    /**
     * Find or create every named record of the given model, keyed by name.
     *
     * @param  class-string<ProductCategory|Brand>  $model
     * @param  array<int, string>  $names
     * @return array<string, int>
     */
    private function findOrCreateIds(string $model, array $names): array
    {
        $ids = [];

        foreach (array_unique($names) as $name) {
            $ids[$name] = $model::firstOrCreate(['name' => $name], ['is_active' => true])->id;
        }

        return $ids;
    }
}
