<?php

namespace App\Support\PriceList;

use JsonSerializable;

/**
 * A single line of the workbook-shaped price list: the product's identity plus
 * its rates in each branch being compared, keyed by branch id.
 */
final readonly class PriceRow implements JsonSerializable
{
    /**
     * @param  array<int, PriceCells|null>  $prices
     */
    public function __construct(
        public int $id,
        public ?string $code,
        public string $name,
        public ?string $unit,
        public ?string $hsn_code,
        public string $tax_percentage,
        public string $category,
        public int $category_id,
        public ?string $brand,
        public array $prices,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
