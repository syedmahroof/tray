<?php

namespace App\Support\Analytics;

use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\User;
use App\Models\VisitReport;
use Illuminate\Support\Collection;

/**
 * A leaderboard of what each person did in the period: visits made,
 * enquiries handled, quotations sent and business won.
 */
class TeamAnalytics extends AnalyticsSection
{
    public function title(): string
    {
        return 'Team Performance';
    }

    public function description(): string
    {
        return 'Visits, enquiries, quotations and wins for each team member.';
    }

    public function report(AnalyticsPeriod $period): AnalyticsReport
    {
        $current = $this->activity($period);
        $previous = $this->activity($period->previous());

        $active = fn (Collection $rows): int => $rows->filter(fn (array $row): bool => $row['visits'] + $row['enquiries'] + $row['quotations'] > 0)->count();

        return (new AnalyticsReport)
            ->stat('Active team members', $active($current), $active($previous), icon: 'users', color: '#4f46e5')
            ->stat('Visits', $current->sum('visits'), $previous->sum('visits'), icon: 'map-pin', color: '#0ea5e9')
            ->stat('Quotations sent', $current->sum('quotations'), $previous->sum('quotations'), icon: 'file', color: '#7c3aed')
            ->stat('Won value', round((float) $current->sum('won'), 2), round((float) $previous->sum('won'), 2), 'money', 'trophy', '#16a34a')
            ->chart('Won value by team member', 'bar', $this->colour($current->where('won', '>', 0)->sortByDesc('won')->take(10)->pluck('won', 'name')), 'money')
            ->chart('Visits by team member', 'bar', $this->colour($current->where('visits', '>', 0)->sortByDesc('visits')->take(10)->pluck('visits', 'name')))
            ->chart('Quoted value by team member', 'bar', $this->colour($current->where('quoted', '>', 0)->sortByDesc('quoted')->take(10)->pluck('quoted', 'name')), 'money')
            ->chart('Enquiries converted by team member', 'bar', $this->colour($current->where('converted', '>', 0)->sortByDesc('converted')->take(10)->pluck('converted', 'name')))
            ->table('Leaderboard', [
                'name' => 'Team member',
                'visits' => ['Visits', 'number'],
                'enquiries' => ['Enquiries', 'number'],
                'converted' => ['Converted', 'number'],
                'quotations' => ['Quotations', 'number'],
                'quoted' => ['Quoted', 'money'],
                'won' => ['Won', 'money'],
                'win_rate' => ['Win rate', 'percent'],
            ], $current
                ->filter(fn (array $row): bool => $row['visits'] + $row['enquiries'] + $row['quotations'] > 0)
                ->sortByDesc(fn (array $row): array => [$row['won'], $row['quoted'], $row['visits']])
                ->values()
                ->all());
    }

    /**
     * Each team member's figures for the period.
     *
     * @return Collection<int, array{name: string, visits: int, enquiries: int, converted: int, quotations: int, quoted: float, won: float, win_rate: float}>
     */
    private function activity(AnalyticsPeriod $period): Collection
    {
        $visits = $this->within(VisitReport::query(), $period, 'visit_date')->get(['id', 'user_id'])->countBy('user_id');
        $enquiries = $this->within(Enquiry::query(), $period)->get(['id', 'assigned_to', 'status'])->groupBy('assigned_to');
        $quotations = $this->within(Quotation::query(), $period, 'quotation_date')
            ->where('status', '!=', 'revised')
            ->get(['id', 'created_by', 'status', 'total'])
            ->groupBy('created_by');

        $userIds = $visits->keys()->merge($enquiries->keys())->merge($quotations->keys())->filter()->unique();

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(function (User $user) use ($visits, $enquiries, $quotations): array {
                $ownEnquiries = $enquiries->get($user->id, collect());
                $ownQuotations = $quotations->get($user->id, collect());
                $won = $ownQuotations->where('status', 'accepted');

                return [
                    'name' => $user->name,
                    'visits' => (int) $visits->get($user->id, 0),
                    'enquiries' => $ownEnquiries->count(),
                    'converted' => $ownEnquiries->where('status', 'converted')->count(),
                    'quotations' => $ownQuotations->count(),
                    'quoted' => round((float) $ownQuotations->sum('total'), 2),
                    'won' => round((float) $won->sum('total'), 2),
                    'win_rate' => $this->percent($won->count(), $won->count() + $ownQuotations->where('status', 'rejected')->count()),
                ];
            })
            ->values();
    }
}
