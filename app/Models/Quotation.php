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
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property string $number
 * @property int $version
 * @property int|null $parent_id
 * @property int|null $contact_id
 * @property int|null $project_id
 * @property int|null $enquiry_id
 * @property int|null $builder_id
 * @property string|null $gstin
 * @property string $supply_type
 * @property Carbon $quotation_date
 * @property Carbon|null $valid_until
 * @property string $status
 * @property string $subtotal
 * @property string $discount
 * @property string $tax_percent
 * @property string $tax_amount
 * @property string $cgst_amount
 * @property string $sgst_amount
 * @property string $igst_amount
 * @property string $total
 * @property string|null $notes
 * @property string|null $terms
 * @property int|null $created_by
 * @property-read Contact|null $contact
 * @property-read Project|null $project
 * @property-read Enquiry|null $enquiry
 * @property-read Builder|null $builder
 * @property-read User|null $creator
 * @property-read Quotation|null $parent
 * @property-read Collection<int, Quotation> $revisions
 * @property-read Collection<int, QuotationItem> $items
 */
#[Fillable([
    'branch_id', 'number', 'version', 'parent_id', 'customer_id', 'contact_id', 'project_id',
    'enquiry_id', 'builder_id', 'gstin', 'supply_type', 'quotation_date',
    'valid_until', 'status', 'subtotal', 'discount', 'tax_percent', 'tax_amount',
    'cgst_amount', 'sgst_amount', 'igst_amount', 'total', 'notes', 'terms',
    'created_by',
])]
class Quotation extends Model
{
    use BelongsToBranch;

    /** @use HasFactory<QuotationFactory> */
    use HasFactory;

    public const array STATUSES = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'revised'];

    public const array SUPPLY_TYPES = ['intra', 'inter'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'quotation_date' => 'date:Y-m-d',
            'valid_until' => 'date:Y-m-d',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'igst_amount' => 'decimal:2',
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
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Enquiry, $this>
     */
    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    /**
     * @return BelongsTo<Builder, $this>
     */
    public function builder(): BelongsTo
    {
        return $this->belongsTo(Builder::class);
    }

    /**
     * The original quotation this revision descends from.
     *
     * @return BelongsTo<Quotation, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'parent_id');
    }

    /**
     * Later revisions that descend from this quotation.
     *
     * @return HasMany<Quotation, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(Quotation::class, 'parent_id');
    }

    /**
     * The id of the root quotation for this revision group.
     */
    public function rootId(): int
    {
        return $this->parent_id ?? $this->id;
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

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }
}
