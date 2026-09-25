<?php

namespace App\Support\Analytics;

use App\Models\Builder;
use App\Models\Project;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Collection;

/**
 * The builders the business works with: who is new, and who brings in
 * projects and orders.
 */
class BuilderAnalytics extends AnalyticsSection
{
    public function title(): string
    {
        return 'Builders';
    }

    public function description(): string
    {
        return 'New builders, and the builders bringing in projects and quotations.';
    }

    public function report(AnalyticsPeriod $period): AnalyticsReport
    {
        $current = $this->builders($period);
        $previous = $this->builders($period->previous());
        $projects = $this->within(Project::query(), $period)->whereNotNull('builder_id')->get(['id', 'builder_id']);
        $quotations = $this->within(Quotation::query(), $period, 'quotation_date')
            ->whereNotNull('builder_id')
            ->where('status', '!=', 'revised')
            ->get(['id', 'builder_id', 'status', 'total']);
        $previousQuotations = $this->within(Quotation::query(), $period->previous(), 'quotation_date')
            ->whereNotNull('builder_id')
            ->where('status', '!=', 'revised')
            ->get(['id', 'builder_id']);

        $names = Builder::query()
            ->whereIn('id', $projects->pluck('builder_id')->merge($quotations->pluck('builder_id'))->unique())
            ->pluck('name', 'id');

        return (new AnalyticsReport)
            ->stat('New builders', $current->count(), $previous->count(), icon: 'hard-hat', color: '#ea580c')
            ->stat('Active builders', Builder::query()->where('is_active', true)->count(), icon: 'users', color: '#4f46e5')
            ->stat('Builders with new projects', $projects->unique('builder_id')->count(), icon: 'building', color: '#0891b2')
            ->stat('Builders quoted', $quotations->unique('builder_id')->count(), $previousQuotations->unique('builder_id')->count(), icon: 'file', color: '#16a34a')
            ->chart('New builders over time', 'trend', $period->trend(
                $current->map(fn (Builder $builder): array => ['date' => $builder->created_at, 'value' => 1]),
            ))
            ->chart('New builders by route', 'bar', $this->countBy($current, fn (Builder $b) => $b->route?->name ?? 'No route', 10))
            ->chart('New builders by owner', 'bar', $this->countBy($current, fn (Builder $b) => $b->assignee?->name, 10))
            ->chart('Quoted value by builder', 'bar', $this->sumBy($quotations, fn (Quotation $q) => $names[$q->builder_id] ?? 'Unknown', fn (Quotation $q) => (float) $q->total, 10), 'money')
            ->table('Top builders in the period', [
                'name' => 'Builder',
                'projects' => ['New projects', 'number'],
                'quotations' => ['Quotations', 'number'],
                'quoted' => ['Quoted', 'money'],
                'won' => ['Won', 'money'],
            ], $names
                ->map(fn (string $name, int $id): array => [
                    'name' => $name,
                    'projects' => $projects->where('builder_id', $id)->count(),
                    'quotations' => $quotations->where('builder_id', $id)->count(),
                    'quoted' => round((float) $quotations->where('builder_id', $id)->sum('total'), 2),
                    'won' => round((float) $quotations->where('builder_id', $id)->where('status', 'accepted')->sum('total'), 2),
                ])
                ->sortByDesc(fn (array $row): array => [$row['won'], $row['quoted'], $row['projects']])
                ->take(15)
                ->values()
                ->all());
    }

    /**
     * @return Collection<int, Builder>
     */
    private function builders(AnalyticsPeriod $period): Collection
    {
        return $this->within(Builder::query(), $period)
            ->with(['route:id,name', 'assignee:id,name'])
            ->get(['id', 'route_id', 'assigned_to', 'created_at']);
    }
}
