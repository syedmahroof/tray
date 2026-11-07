<?php

namespace App\Models;

use Database\Factories\QuotationItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $quotation_id
 * @property int|null $product_id
 * @property string $description
 * @property string|null $hsn_code
 * @property string $quantity
 * @property string|null $rate_tier
 * @property string|null $price_basis
 * @property string|null $list_price
 * @property string|null $price_percent
 * @property string|null $base_price
 * @property string $unit_price
 * @property string $tax_percentage
 * @property string $tax_amount
 * @property-read Quotation $quotation
 * @property-read Product|null $product
 */
#[Fillable(['quotation_id', 'product_id', 'description', 'hsn_code', 'quantity', 'rate_tier', 'price_basis', 'list_price', 'price_percent', 'base_price', 'unit_price', 'tax_percentage', 'tax_amount'])]
class QuotationItem extends Model
{
    /** @use HasFactory<QuotationItemFactory> */
    use HasFactory;

    /**
     * The price type for a line quoted at a percentage chosen by hand (off
     * MRP, or on cost), rather than at one of the branch rate tiers.
     */
    public const string CUSTOM_TIER = 'custom';

    /**
     * The price types a line can carry: the branch rate tiers, then custom.
     *
     * @return array<string, string>
     */
    public static function rateTiers(): array
    {
        return [...ProductBranchPrice::RATE_TIERS, self::CUSTOM_TIER => 'Custom Price'];
    }

    /**
     * Determine whether the line is priced at a hand-set percentage.
     */
    public function isCustomPriced(): bool
    {
        return $this->rate_tier === self::CUSTOM_TIER;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'list_price' => 'decimal:2',
            'price_percent' => 'decimal:2',
            'base_price' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'tax_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
