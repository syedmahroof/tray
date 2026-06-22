<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\QuotationFactory;
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
 * @property string $number
 * @property int|null $contact_id
 * @property int|null $project_id
 * @property Carbon $quotation_date
 * @property Carbon|null $valid_until
 * @property string $status
 * @property string $subtotal
 * @property string $discount
 * @property string $tax_percent
 * @property string $tax_amount
 * @property string $total
 * @property string|null $notes
 * @property string|null $terms
 * @property int|null $created_by
 * @property-read Contact|null $contact
 * @property-read Project|null $project
 * @property-read User|null $creator
 * @property-read Collection<int, QuotationItem> $items
 */
#[Fillable([
    'branch_id', 'number', 'contact_id', 'project_id', 'quotation_date',
    'valid_until', 'status', 'subtotal', 'discount', 'tax_percent',
    'tax_amount', 'total', 'notes', 'terms', 'created_by',
])]
class Quotation extends Model
{
    use BelongsToBranch;

    /** @use HasFactory<QuotationFactory> */
    use HasFactory;

    public const array STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quotation_date' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<QuotationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }
}
