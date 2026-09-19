<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\ProductBranchPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property int $id
 * @property int $product_id
 * @property int $branch_id
 * @property string|null $cost
 * @property string|null $mrp
 * @property string $rate_basis
 * @property string|null $sr_discount
 * @property string|null $sr_rate
 * @property string|null $sr_rate_with_tax
 * @property string|null $pr_discount
 * @property string|null $pr_rate
 * @property string|null $pr_rate_with_tax
 * @property string|null $cr_discount
 * @property string|null $cr_rate
 * @property string|null $cr_rate_with_tax
 * @property Carbon|null $effective_from
 * @property bool $is_active
 * @property string|null $notes
 * @property-read Product $product
 * @property-read Branch $branch
 */
#[Fillable([
    'product_id', 'branch_id', 'cost', 'mrp', 'rate_basis',
    'sr_discount', 'sr_rate', 'sr_rate_with_tax',
    'pr_discount', 'pr_rate', 'pr_rate_with_tax',
    'cr_discount', 'cr_rate', 'cr_rate_with_tax',
    'effective_from', 'is_active', 'notes',
])]
class ProductBranchPrice extends Model
{
    use BelongsToBranch;

    /** @use HasFactory<ProductBranchPriceFactory> */
    use HasFactory;

    /**
     * The rate tiers carried by every price row, mapping the key used in the
     * source price list to its human label.
     *
     * @var array<string, string>
     */
    public const array RATE_TIERS = [
        'SR' => 'Stockist Rate',
        'PR' => 'Project Rate',
        'CR' => 'Counter Rate',
    ];

    /**
     * What a tier's percentage is worked out from, mapping the stored value to
     * its human label.
     *
     * @var array<string, string>
     */
    public const array RATE_BASES = [
        'mrp' => 'Discount off MRP',
        'cost' => 'Markup on cost',
    ];

    /**
     * The fields whose changes are written to the price history log.
     *
     * @var list<string>
     */
    public const array TRACKED_FIELDS = [
        'cost', 'mrp',
        'sr_discount', 'sr_rate', 'sr_rate_with_tax',
        'pr_discount', 'pr_rate', 'pr_rate_with_tax',
        'cr_discount', 'cr_rate', 'cr_rate_with_tax',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:4',
            'mrp' => 'decimal:2',
            'sr_discount' => 'decimal:2',
            'sr_rate' => 'decimal:2',
            'sr_rate_with_tax' => 'decimal:2',
            'pr_discount' => 'decimal:2',
            'pr_rate' => 'decimal:2',
            'pr_rate_with_tax' => 'decimal:2',
            'cr_discount' => 'decimal:2',
            'cr_rate' => 'decimal:2',
            'cr_rate_with_tax' => 'decimal:2',
            'effective_from' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Normalise a tier key and assert that it is one we know about.
     *
     * @throws InvalidArgumentException
     */
    public static function normaliseTier(string $tier): string
    {
        $tier = strtoupper($tier);

        if (! array_key_exists($tier, self::RATE_TIERS)) {
            throw new InvalidArgumentException("Unknown rate tier [{$tier}].");
        }

        return $tier;
    }

    /**
     * Determine whether this row's percentages are discounts off its MRP,
     * rather than markups on its cost.
     */
    public function usesMrp(): bool
    {
        return $this->rate_basis !== 'cost';
    }

    /**
     * Get the ex-tax rate for the given tier.
     */
    public function rate(string $tier): ?string
    {
        return $this->{strtolower(self::normaliseTier($tier)).'_rate'};
    }

    /**
     * Get the tax-inclusive rate for the given tier.
     */
    public function rateWithTax(string $tier): ?string
    {
        return $this->{strtolower(self::normaliseTier($tier)).'_rate_with_tax'};
    }

    /**
     * Get the discount percentage recorded for the given tier.
     */
    public function discount(string $tier): ?string
    {
        return $this->{strtolower(self::normaliseTier($tier)).'_discount'};
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
