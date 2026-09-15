<?php

namespace App\Actions\Products;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBranchPrice;
use App\Models\ProductPriceHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveProductBranchPrice
{
    /**
     * Create or update a product's price for a branch and log every field that
     * changed. This is the only supported way to write a price row: the admin
     * UI, the API, the price-list importer and the seeders all go through here
     * so the history log can never be bypassed.
     *
     * Rates are stored exactly as supplied — the source price list's discount
     * maths is inconsistent, so nothing is back-calculated from cost or MRP.
     * Only the tax-inclusive rates are derived, and only when not given.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Product $product,
        Branch|int $branch,
        array $attributes,
        ?User $user = null,
        ?string $reason = null,
    ): ProductBranchPrice {
        $branchId = $branch instanceof Branch ? $branch->id : $branch;

        $attributes = $this->withDerivedTaxInclusiveRates($product, $attributes);

        return DB::transaction(function () use ($product, $branchId, $attributes, $user, $reason): ProductBranchPrice {
            $price = ProductBranchPrice::query()
                ->withoutGlobalScopes()
                ->firstOrNew(['product_id' => $product->id, 'branch_id' => $branchId]);

            $isNew = ! $price->exists;
            $original = $this->trackedValues($price);

            $price->fill($attributes);
            $price->save();

            // A brand new row is the baseline, not a change, so it only writes
            // history when the caller explicitly supplies a reason.
            if (! $isNew || $reason !== null) {
                $this->logChanges($price, $original, $user, $reason);
            }

            return $price;
        });
    }

    /**
     * Fill in any missing tax-inclusive rate from its ex-tax counterpart using
     * the product's tax percentage.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withDerivedTaxInclusiveRates(Product $product, array $attributes): array
    {
        $factor = bcadd('1', bcdiv($this->decimal($product->tax_percentage), '100', 6), 6);

        foreach (['sr', 'pr', 'cr'] as $tier) {
            $rate = $attributes["{$tier}_rate"] ?? null;

            if ($rate === null || $rate === '') {
                continue;
            }

            if (($attributes["{$tier}_rate_with_tax"] ?? null) === null) {
                // Multiplied as decimals: 79.75 x 1.18 is 94.105, which binary
                // floats represent as 94.10499... and would round down to 94.10.
                $attributes["{$tier}_rate_with_tax"] = $this->roundHalfUp(
                    bcmul($this->decimal($rate), $factor, 8)
                );
            }
        }

        return $attributes;
    }

    /**
     * Render a numeric value as a plain decimal string bcmath can consume.
     *
     * @return numeric-string
     */
    private function decimal(mixed $value): string
    {
        return sprintf('%.8F', (float) $value);
    }

    /**
     * Round a decimal string to two places, half away from zero.
     *
     * @param  numeric-string  $value
     */
    private function roundHalfUp(string $value): string
    {
        $offset = str_starts_with($value, '-') ? '-0.005' : '0.005';

        return bcadd($value, $offset, 2);
    }

    /**
     * Snapshot the tracked fields of a price row as floats for comparison.
     *
     * @return array<string, float|null>
     */
    private function trackedValues(ProductBranchPrice $price): array
    {
        $values = [];

        foreach (ProductBranchPrice::TRACKED_FIELDS as $field) {
            $value = $price->getAttribute($field);
            $values[$field] = $value === null ? null : (float) $value;
        }

        return $values;
    }

    /**
     * Write one history row for every tracked field whose value moved.
     *
     * @param  array<string, float|null>  $original
     */
    private function logChanges(
        ProductBranchPrice $price,
        array $original,
        ?User $user,
        ?string $reason,
    ): void {
        $current = $this->trackedValues($price);
        $changedAt = now();
        $rows = [];

        foreach (ProductBranchPrice::TRACKED_FIELDS as $field) {
            if ($original[$field] === $current[$field]) {
                continue;
            }

            $rows[] = [
                'product_id' => $price->product_id,
                'branch_id' => $price->branch_id,
                'user_id' => $user?->id,
                'field' => $field,
                'old_value' => $original[$field],
                'new_value' => $current[$field],
                'reason' => $reason,
                'changed_at' => $changedAt,
                'created_at' => $changedAt,
                'updated_at' => $changedAt,
            ];
        }

        if ($rows !== []) {
            ProductPriceHistory::insert($rows);
        }
    }
}
