<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\VisitReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $user_id
 * @property Carbon $visit_date
 * @property string $visit_type
 * @property string $objective
 * @property string|null $report
 * @property Carbon|null $next_meeting_date
 * @property Carbon|null $next_call_date
 * @property-read User $user
 */
#[Fillable(['branch_id', 'user_id', 'visit_date', 'visit_type', 'objective', 'report', 'next_meeting_date', 'next_call_date'])]
class VisitReport extends Model
{
    use BelongsToBranch;

    /** @use HasFactory<VisitReportFactory> */
    use HasFactory;

    public const array VISIT_TYPES = ['Site Visit', 'Client Meeting', 'Follow-up', 'Inspection', 'Other'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visit_date' => 'date:Y-m-d',
            'next_meeting_date' => 'date:Y-m-d',
            'next_call_date' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'visit_report_project');
    }

    /**
     * @return BelongsToMany<Customer, $this>
     */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'visit_report_customer');
    }

    /**
     * @return BelongsToMany<Contact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'visit_report_contact');
    }
}
