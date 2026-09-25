<?php

namespace App\Support\Analytics;

use App\Models\VisitReport;
use Illuminate\Database\Eloquent\Collection;

/**
 * How much time is spent in the field, by whom, and on which routes.
 */
class VisitReportAnalytics extends AnalyticsSection
{
    public function title(): string
    {
        return 'Visit Reports';
    }

    public function description(): string
    {
        return 'Field visits by type, staff member and route.';
    }

    public function report(AnalyticsPeriod $period): AnalyticsReport
    {
        $current = $this->visits($period);
        $previous = $this->visits($period->previous());

        $perDay = fn (Collection $visits, AnalyticsPeriod $span): float => round($visits->count() / max(1, $span->days()), 1);
        $followUps = fn (Collection $visits): int => $visits->filter(fn (VisitReport $v): bool => $v->next_meeting_date !== null || $v->next_call_date !== null)->count();

        return (new AnalyticsReport)
            ->stat('Visits', $current->count(), $previous->count(), icon: 'map-pin', color: '#0ea5e9')
            ->stat('Staff in the field', $current->unique('user_id')->count(), $previous->unique('user_id')->count(), icon: 'users', color: '#4f46e5')
            ->stat('Visits per day', $perDay($current, $period), $perDay($previous, $period->previous()), icon: 'calendar', color: '#16a34a')
            ->stat('Follow-ups planned', $followUps($current), $followUps($previous), icon: 'clock', color: '#d97706')
            ->chart('Visits over time', 'trend', $period->trend(
                $current->map(fn (VisitReport $visit): array => ['date' => $visit->visit_date, 'value' => 1]),
            ))
            ->chart('Visits by type', 'donut', $this->countBy($current, fn (VisitReport $v) => $v->visit_type ?: 'Other'))
            ->chart('Visits by staff member', 'bar', $this->countBy($current, fn (VisitReport $v) => $v->user?->name, 10))
            ->chart('Visits by route', 'bar', $this->countBy($current, fn (VisitReport $v) => $v->route?->name ?? 'No route', 10))
            ->table('Staff activity', [
                'name' => 'Staff member',
                'visits' => ['Visits', 'number'],
                'days' => ['Days in field', 'number'],
                'routes' => ['Routes covered', 'number'],
                'follow_ups' => ['Follow-ups planned', 'number'],
            ], $current
                ->groupBy(fn (VisitReport $v): string => $v->user?->name ?? 'Unknown')
                ->map(fn (Collection $group, string $name): array => [
                    'name' => $name,
                    'visits' => $group->count(),
                    'days' => $group->map(fn (VisitReport $v): string => $v->visit_date->toDateString())->unique()->count(),
                    'routes' => $group->whereNotNull('route_id')->unique('route_id')->count(),
                    'follow_ups' => $followUps($group),
                ])
                ->sortByDesc('visits')
                ->values()
                ->all());
    }

    /**
     * @return Collection<int, VisitReport>
     */
    private function visits(AnalyticsPeriod $period): Collection
    {
        return $this->within(VisitReport::query(), $period, 'visit_date')
            ->with(['user:id,name', 'route:id,name'])
            ->get(['id', 'user_id', 'visit_date', 'visit_type', 'route_id', 'next_meeting_date', 'next_call_date']);
    }
}
