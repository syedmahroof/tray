<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $product_category_id
 * @property string $name
 * @property string|null $price
 * @property string|null $area_sqft
 * @property string|null $description
 * @property-read ProductCategory $productCategory
 */
#[Fillable(['branch_id', 'product_category_id', 'name', 'price', 'area_sqft', 'description'])]
class Product extends Model
{
    use BelongsToBranch;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'area_sqft' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }
}
