<?php

namespace App\Support\Analytics;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

/**
 * The project pipeline: what is coming in, what stage it is at, and whose it is.
 */
class ProjectAnalytics extends AnalyticsSection
{
    public function title(): string
    {
        return 'Projects';
    }

    public function description(): string
    {
        return 'New projects by stage, category, builder and location.';
    }

    public function report(AnalyticsPeriod $period): AnalyticsReport
    {
        $current = $this->projects($period);
        $previous = $this->projects($period->previous());

        return (new AnalyticsReport)
            ->stat('New projects', $current->count(), $previous->count(), icon: 'building', color: '#0891b2')
            ->stat('Planning', $current->where('status', 'planning')->count(), $previous->where('status', 'planning')->count(), icon: 'clipboard', color: '#d97706')
            ->stat('Ongoing', $current->where('status', 'ongoing')->count(), $previous->where('status', 'ongoing')->count(), icon: 'hammer', color: '#0ea5e9')
            ->stat('Completed', $current->where('status', 'completed')->count(), $previous->where('status', 'completed')->count(), icon: 'check', color: '#16a34a')
            ->stat('Total projects', Project::query()->count(), icon: 'layers', color: '#4f46e5')
            ->chart('New projects over time', 'trend', $period->trend(
                $current->map(fn (Project $project): array => ['date' => $project->created_at, 'value' => 1]),
            ))
            ->chart('New projects by stage', 'donut', $this->countEach($current, 'status', Project::STATUSES))
            ->chart('New projects by category', 'bar', $this->countBy($current, fn (Project $p) => $p->projectCategory?->name ?? 'Uncategorised', 10))
            ->chart('New projects by location', 'bar', $this->countBy($current, fn (Project $p) => $p->locationMaster?->name ?? 'No location', 10))
            ->table('New projects by builder', [
                'name' => 'Builder',
                'projects' => ['Projects', 'number'],
                'planning' => ['Planning', 'number'],
                'ongoing' => ['Ongoing', 'number'],
                'completed' => ['Completed', 'number'],
            ], $current
                ->groupBy(fn (Project $p): string => $p->builder?->name ?? 'No builder')
                ->map(fn (Collection $group, string $name): array => [
                    'name' => $name,
                    'projects' => $group->count(),
                    'planning' => $group->where('status', 'planning')->count(),
                    'ongoing' => $group->where('status', 'ongoing')->count(),
                    'completed' => $group->where('status', 'completed')->count(),
                ])
                ->sortByDesc('projects')
                ->take(15)
                ->values()
                ->all());
    }

    /**
     * @return Collection<int, Project>
     */
    private function projects(AnalyticsPeriod $period): Collection
    {
        return $this->within(Project::query(), $period)
            ->with(['projectCategory:id,name', 'builder:id,name', 'locationMaster:id,name'])
            ->get(['id', 'status', 'project_category_id', 'builder_id', 'location_id', 'created_at']);
    }
}
