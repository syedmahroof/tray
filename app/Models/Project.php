<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $branch_id
 * @property int|null $builder_id
 * @property int $project_category_id
 * @property string $name
 * @property string|null $address
 * @property int|null $country_id
 * @property int|null $state_id
 * @property int|null $district_id
 * @property string $status
 * @property string|null $description
 * @property string|null $owner_name
 * @property string|null $owner_phone
 * @property string|null $owner_email
 * @property string|null $location
 * @property string|null $pincode
 * @property string|null $expected_maturity
 * @property string|null $preferred_material
 * @property int|null $assignee_id
 * @property int|null $created_by
 * @property string|null $start_date
 * @property string|null $end_date
 * @property-read Builder|null $builder
 * @property-read ProjectCategory $projectCategory
 * @property-read Country|null $country
 * @property-read State|null $state
 * @property-read District|null $district
 * @property-read User|null $assignee
 * @property-read User|null $creator
 * @property-read Collection<int, ProjectContact> $projectContacts
 */
#[Fillable([
    'branch_id',
    'builder_id',
    'project_category_id',
    'name',
    'address',
    'country_id',
    'state_id',
    'district_id',
    'status',
    'description',
    'owner_name',
    'owner_phone',
    'owner_email',
    'location',
    'pincode',
    'expected_maturity',
    'preferred_material',
    'assignee_id',
    'created_by',
    'start_date',
    'end_date',
])]
class Project extends Model
{
    use BelongsToBranch;

    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    public const array STATUSES = ['planning', 'ongoing', 'completed'];

    /**
     * @return BelongsTo<Builder, $this>
     */
    public function builder(): BelongsTo
    {
        return $this->belongsTo(Builder::class);
    }

    /**
     * @return BelongsTo<ProjectCategory, $this>
     */
    public function projectCategory(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class);
    }

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
     * @return BelongsToMany<VisitReport, $this>
     */
    public function visitReports(): BelongsToMany
    {
        return $this->belongsToMany(VisitReport::class, 'visit_report_project');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<Contact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class);
    }

    /**
     * @return HasMany<ProjectContact, $this>
     */
    public function projectContacts(): HasMany
    {
        return $this->hasMany(ProjectContact::class);
    }
}
