<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasActivity;
use Database\Factories\EnquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $contact_id
 * @property int|null $project_id
 * @property int|null $product_id
 * @property int|null $assigned_to
 * @property string $status
 * @property string|null $source
 * @property string|null $remarks
 * @property-read Contact $contact
 * @property-read Project|null $project
 * @property-read Product|null $product
 * @property-read User|null $assignee
 */
#[Fillable(['branch_id', 'customer_id', 'contact_id', 'project_id', 'product_id', 'assigned_to', 'status', 'source', 'remarks'])]
class Enquiry extends Model
{
    use BelongsToBranch;
    use HasActivity;

    /** @use HasFactory<EnquiryFactory> */
    use HasFactory;

    public const array STATUSES = ['new', 'in_progress', 'converted', 'lost'];

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
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user this enquiry is assigned to.
     *
     * Named "assignee" rather than "assignedTo" because the latter would
     * snake_case to "assigned_to" when serialized, colliding with the
     * raw assigned_to foreign key column.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<Quotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }
}
