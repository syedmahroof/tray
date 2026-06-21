<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\BuilderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $branch_id
 * @property string $name
 * @property string|null $contact_person
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property int|null $country_id
 * @property int|null $state_id
 * @property int|null $district_id
 * @property bool $is_active
 * @property-read Country|null $country
 * @property-read State|null $state
 * @property-read District|null $district
 */
#[Fillable(['branch_id', 'name', 'contact_person', 'phone', 'email', 'address', 'country_id', 'state_id', 'district_id', 'is_active'])]
class Builder extends Model
{
    use BelongsToBranch;

    /** @use HasFactory<BuilderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
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
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
