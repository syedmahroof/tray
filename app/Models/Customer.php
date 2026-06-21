<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $branch_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property int|null $country_id
 * @property int|null $state_id
 * @property int|null $district_id
 * @property int|null $assigned_to
 * @property-read Country|null $country
 * @property-read State|null $state
 * @property-read District|null $district
 * @property-read User|null $assignee
 */
#[Fillable(['branch_id', 'name', 'phone', 'email', 'address', 'country_id', 'state_id', 'district_id', 'assigned_to'])]
class Customer extends Model
{
    use BelongsToBranch;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<State, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * Get the user this customer is assigned to.
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
     * @return BelongsToMany<VisitReport, $this>
     */
    public function visitReports(): BelongsToMany
    {
        return $this->belongsToMany(VisitReport::class, 'visit_report_customer');
    }
}
