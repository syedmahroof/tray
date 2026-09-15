<?php

namespace App\Models;

use App\Models\Scopes\BrandScope;
use App\Support\StoredImage;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $product_category_id
 * @property int|null $brand_id
 * @property string|null $code
 * @property string|null $import_key
 * @property string $name
 * @property string|null $unit
 * @property string|null $hsn_code
 * @property string|null $price
 * @property string|null $taxable_amount
 * @property string|null $tax_type
 * @property string $tax_percentage
 * @property string|null $area_sqft
 * @property string|null $description
 * @property string|null $image_path
 * @property-read string|null $image_url
 * @property int|null $created_by
 * @property-read ProductCategory $productCategory
 * @property-read Brand|null $brand
 * @property-read User|null $creator
 * @property-read Collection<int, ProductBranchPrice> $branchPrices
 * @property-read Collection<int, ProductPriceHistory> $priceHistories
 * @property-read Collection<int, Project> $projects
 */
#[Fillable(['product_category_id', 'brand_id', 'code', 'import_key', 'name', 'unit', 'hsn_code', 'price', 'taxable_amount', 'tax_type', 'tax_percentage', 'area_sqft', 'description', 'image_path', 'created_by'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $appends = ['image_url'];

    /**
     * GST rate slabs, mapping the human label to its percentage.
     *
     * @var array<string, int>
     */
    public const array GST_SLABS = [
        'GST 0%' => 0,
        'GST 5%' => 5,
        'GST 12%' => 12,
        'GST 18%' => 18,
        'GST 28%' => 28,
        'Exempt' => 0,
    ];

    /**
     * Units of measure used across the catalogue.
     *
     * @var list<string>
     */
    public const array UNITS = ['Mtr', 'Nos', 'Kg', 'Sqm', 'Ltr', 'Set'];

    /**
     * Boot the model and restrict queries to the user's accessible brands.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new BrandScope);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'tax_percentage' => 'decimal:2',
            'area_sqft' => 'decimal:2',
        ];
    }

    /**
     * The public URL of the product image. Read from the raw attributes so
     * queries that select only a few columns still serialise.
     *
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => StoredImage::url($this->attributes['image_path'] ?? null));
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the per-branch price rows for this product.
     *
     * @return HasMany<ProductBranchPrice, $this>
     */
    public function branchPrices(): HasMany
    {
        return $this->hasMany(ProductBranchPrice::class);
    }

    /**
     * Get the price change log for this product across every branch.
     *
     * @return HasMany<ProductPriceHistory, $this>
     */
    public function priceHistories(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class);
    }

    /**
     * Get the price row for the given branch, preferring an already loaded
     * relation so callers that eager load don't fire an extra query.
     */
    public function priceFor(int $branchId): ?ProductBranchPrice
    {
        if ($this->relationLoaded('branchPrices')) {
            return $this->branchPrices->firstWhere('branch_id', $branchId);
        }

        return $this->branchPrices()->where('branch_id', $branchId)->first();
    }

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }
}
