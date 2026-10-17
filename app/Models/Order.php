<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property string $type
 * @property string $number
 * @property int|null $customer_id
 * @property int|null $supplier_id
 * @property string|null $gstin
 * @property string $supply_type
 * @property Carbon $order_date
 * @property Carbon|null $expected_date
 * @property string $status
 * @property string $subtotal
 * @property string $discount
 * @property string $tax_amount
 * @property string $cgst_amount
 * @property string $sgst_amount
 * @property string $igst_amount
 * @property string $total
 * @property string|null $notes
 * @property string|null $terms
 * @property int|null $created_by
 * @property-read Customer|null $customer
 * @property-read Supplier|null $supplier
 * @property-read Branch $branch
 * @property-read Collection<int, OrderItem> $items
 * @property-read User|null $creator
 */
#[Fillable([
    'branch_id', 'type', 'number', 'customer_id', 'supplier_id', 'gstin', 'supply_type', 'order_date', 'expected_date', 'status',
    'subtotal', 'discount', 'tax_amount', 'cgst_amount', 'sgst_amount', 'igst_amount',
    'total', 'notes', 'terms', 'created_by',
])]
class Order extends Model
{
    use BelongsToBranch;

    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    public const array TYPES = ['sale', 'purchase'];

    public const array STATUSES = ['draft', 'confirmed', 'partially_fulfilled', 'fulfilled', 'cancelled'];

    public const array SUPPLY_TYPES = ['intra', 'inter'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_date' => 'date:Y-m-d',
            'expected_date' => 'date:Y-m-d',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'igst_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * The customer or supplier on the other side of the order.
     */
    public function partyName(): ?string
    {
        return $this->type === 'sale' ? $this->customer?->name : $this->supplier?->name;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
