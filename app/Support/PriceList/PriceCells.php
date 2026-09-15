<?php

namespace App\Support\PriceList;

use App\Models\ProductBranchPrice;
use JsonSerializable;

/**
 * One product's rates in one branch, in the column order the price list
 * workbook uses.
 */
final readonly class PriceCells implements JsonSerializable
{
    public function __construct(
        public ?string $cost,
        public ?string $mrp,
        public ?string $sr_discount,
        public ?string $sr_rate,
        public ?string $sr_rate_with_tax,
        public ?string $pr_discount,
        public ?string $pr_rate,
        public ?string $pr_rate_with_tax,
        public ?string $cr_discount,
        public ?string $cr_rate,
        public ?string $cr_rate_with_tax,
    ) {}

    public static function from(?ProductBranchPrice $price): ?self
    {
        if (! $price) {
            return null;
        }

        return new self(
            cost: $price->cost,
            mrp: $price->mrp,
            sr_discount: $price->sr_discount,
            sr_rate: $price->sr_rate,
            sr_rate_with_tax: $price->sr_rate_with_tax,
            pr_discount: $price->pr_discount,
            pr_rate: $price->pr_rate,
            pr_rate_with_tax: $price->pr_rate_with_tax,
            cr_discount: $price->cr_discount,
            cr_rate: $price->cr_rate,
            cr_rate_with_tax: $price->cr_rate_with_tax,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
