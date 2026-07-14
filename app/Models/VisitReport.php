<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\VisitReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
     * Windows for the "no visit report within" filter, mapping key to days.
     *
     * @var array<string, int>
     */
    public const array NO_VISIT_PERIODS = [
        '7d' => 7,
        '30d' => 30,
        '60d' => 60,
        '3m' => 90,
        '6m' => 180,
        '1y' => 365,
    ];

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

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    protected static function booted(): void
    {
        static::created(function (VisitReport $visitReport) {
            $visitReport->auditLogs()->create([
                'user_id' => auth()->id(),
                'action' => 'created',
                'description' => 'Visit report created.',
            ]);
        });

        static::updating(function (VisitReport $visitReport) {
            $dirty = $visitReport->getDirty();
            unset($dirty['updated_at']);

            if ($dirty === []) {
                return;
            }

            $descriptions = [];
            $changes = ['old' => [], 'new' => []];

            foreach ($dirty as $key => $newValue) {
                $oldValue = $visitReport->getOriginal($key);
                $label = ucwords(str_replace('_', ' ', $key));
                $descriptions[] = "{$label} changed";
                $changes['old'][$key] = $oldValue;
                $changes['new'][$key] = $newValue;
            }

            $visitReport->auditLogs()->create([
                'user_id' => auth()->id(),
                'action' => 'updated',
                'description' => implode(', ', $descriptions).'.',
                'changes' => $changes,
            ]);
        });
    }
}
