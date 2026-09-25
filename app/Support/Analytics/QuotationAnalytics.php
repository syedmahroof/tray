<?php

namespace App\Support\Analytics;

use App\Models\Quotation;
use Illuminate\Database\Eloquent\Collection;

/**
 * How much is being quoted, how much of it is won, and by whom.
 */
class QuotationAnalytics extends AnalyticsSection
{
    public function title(): string
    {
        return 'Sales & Quotations';
    }

    public function description(): string
    {
        return 'Quoted value, wins and win rate. Superseded revisions are left out.';
    }

    public function report(AnalyticsPeriod $period): AnalyticsReport
    {
        $current = $this->quotations($period);
        $previous = $this->quotations($period->previous());

        [$winRate, $previousWinRate] = [$this->winRate($current), $this->winRate($previous)];

        return (new AnalyticsReport)
            ->stat('Quotations', $current->count(), $previous->count(), icon: 'file', color: '#4f46e5')
            ->stat('Quoted value', (float) $current->sum('total'), (float) $previous->sum('total'), 'money', 'wallet', '#0ea5e9')
            ->stat('Won value', $this->won($current), $this->won($previous), 'money', 'trophy', '#16a34a')
            ->stat('Win rate', $winRate, $previousWinRate, 'percent', 'target', '#d97706')
            ->stat('Average quotation', $current->count() ? round((float) $current->avg('total'), 2) : 0, $previous->count() ? round((float) $previous->avg('total'), 2) : 0, 'money', 'scale', '#7c3aed')
            ->chart('Quoted value over time', 'trend', $period->trend(
                $current->map(fn (Quotation $quotation): array => ['date' => $quotation->quotation_date, 'value' => (float) $quotation->total]),
            ), 'money')
            ->chart('Quotations by status', 'donut', $this->countEach($current, 'status', array_values(array_diff(Quotation::STATUSES, ['revised']))))
            ->chart('Quoted value by salesperson', 'bar', $this->sumBy($current, fn (Quotation $q) => $q->creator?->name, fn (Quotation $q) => (float) $q->total, 10), 'money')
            ->chart('Won value by salesperson', 'bar', $this->sumBy($current->where('status', 'accepted'), fn (Quotation $q) => $q->creator?->name, fn (Quotation $q) => (float) $q->total, 10), 'money')
            ->table('Top customers by quoted value', [
                'name' => 'Customer',
                'quotations' => ['Quotations', 'number'],
                'quoted' => ['Quoted', 'money'],
                'won' => ['Won', 'money'],
            ], $current
                ->groupBy(fn (Quotation $q): string => $q->customer?->name ?? $q->contact?->name ?? 'Walk-in')
                ->map(fn (Collection $group, string $name): array => [
                    'name' => $name,
                    'quotations' => $group->count(),
                    'quoted' => round((float) $group->sum('total'), 2),
                    'won' => round((float) $group->where('status', 'accepted')->sum('total'), 2),
                ])
                ->sortByDesc('quoted')
                ->take(10)
                ->values()
                ->all())
            ->table('Largest quotations', [
                'number' => 'Number',
                'customer' => 'Customer',
                'date' => ['Date', 'date'],
                'status' => ['Status', 'status'],
                'total' => ['Total', 'money'],
            ], $current
                ->sortByDesc('total')
                ->take(10)
                ->map(fn (Quotation $q): array => [
                    'number' => $q->number,
                    'customer' => $q->customer?->name ?? $q->contact?->name ?? '—',
                    'date' => $q->quotation_date->toDateString(),
                    'status' => $q->status,
                    'total' => (float) $q->total,
                ])
                ->values()
                ->all());
    }

    /**
     * @return Collection<int, Quotation>
     */
    private function quotations(AnalyticsPeriod $period): Collection
    {
        return $this->within(Quotation::query(), $period, 'quotation_date')
            ->where('status', '!=', 'revised')
            ->with(['creator:id,name', 'customer:id,name', 'contact:id,name'])
            ->get(['id', 'number', 'status', 'total', 'quotation_date', 'created_by', 'customer_id', 'contact_id']);
    }

    /**
     * @param  Collection<int, Quotation>  $quotations
     */
    private function won(Collection $quotations): float
    {
        return round((float) $quotations->where('status', 'accepted')->sum('total'), 2);
    }

    /**
     * Wins as a share of the quotations that have been decided either way.
     *
     * @param  Collection<int, Quotation>  $quotations
     */
    private function winRate(Collection $quotations): float
    {
        $won = $quotations->where('status', 'accepted')->count();

        return $this->percent($won, $won + $quotations->where('status', 'rejected')->count());
    }
}
