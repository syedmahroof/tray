<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Support\Str;
use RuntimeException;

class ProductCodeGenerator
{
    /**
     * The prefix used when a product has no brand.
     */
    private const string NO_BRAND_PREFIX = 'GEN';

    /**
     * How many times to retry before giving up on finding a free code.
     */
    private const int MAX_ATTEMPTS = 1000;

    /**
     * Build a unique part number for a product, shaped as CATEGORY-BRAND-NNNNN
     * (for example SUP-LDR-00042).
     *
     * @throws RuntimeException when no free code can be found
     */
    public function generate(ProductCategory $category, ?Brand $brand = null): string
    {
        $brandPrefix = $brand instanceof Brand ? $this->prefix($brand->name) : self::NO_BRAND_PREFIX;

        $prefix = $this->prefix($category->name).'-'.$brandPrefix;

        $sequence = $this->highestSequenceFor($prefix);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = $prefix.'-'.str_pad((string) (++$sequence), 5, '0', STR_PAD_LEFT);

            if (! $this->exists($code)) {
                return $code;
            }
        }

        throw new RuntimeException("Unable to allocate a product code for prefix [{$prefix}].");
    }

    /**
     * Reduce a name to a three character uppercase prefix, padding short names
     * so every code has the same shape.
     */
    private function prefix(string $name): string
    {
        $letters = Str::upper(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($name)) ?? '');

        return $letters === ''
            ? self::NO_BRAND_PREFIX
            : str_pad(substr($letters, 0, 3), 3, 'X');
    }

    /**
     * Find the highest sequence number already issued for the given prefix.
     */
    private function highestSequenceFor(string $prefix): int
    {
        $latest = Product::query()
            ->withoutGlobalScopes()
            ->where('code', 'like', $prefix.'-%')
            ->orderByDesc('code')
            ->value('code');

        return $latest === null ? 0 : (int) substr((string) $latest, strlen($prefix) + 1);
    }

    /**
     * Determine whether the given code is already taken.
     */
    private function exists(string $code): bool
    {
        return Product::query()->withoutGlobalScopes()->where('code', $code)->exists();
    }
}
