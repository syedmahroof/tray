<?php

namespace App\Support\Analytics;

use App\Models\Enquiry;
use Illuminate\Database\Eloquent\Collection;

/**
 * Where enquiries come from, what they are for, and how many convert.
 */
class EnquiryAnalytics extends AnalyticsSection
{
    public function title(): string
    {
        return 'Enquiries';
    }

    public function description(): string
    {
        return 'Enquiries received, their sources and how many convert.';
    }

    public function report(AnalyticsPeriod $period): AnalyticsReport
    {
        $current = $this->enquiries($period);
        $previous = $this->enquiries($period->previous());

        $open = fn (Collection $items): int => $items->whereIn('status', ['new', 'in_progress'])->count();

        return (new AnalyticsReport)
            ->stat('Enquiries', $current->count(), $previous->count(), icon: 'inbox', color: '#d97706')
            ->stat('Converted', $current->where('status', 'converted')->count(), $previous->where('status', 'converted')->count(), icon: 'check', color: '#16a34a')
            ->stat('Conversion rate', $this->percent($current->where('status', 'converted')->count(), $current->count()), $this->percent($previous->where('status', 'converted')->count(), $previous->count()), 'percent', 'target', '#4f46e5')
            ->stat('Still open', $open($current), $open($previous), icon: 'clock', color: '#0ea5e9')
            ->stat('Lost', $current->where('status', 'lost')->count(), $previous->where('status', 'lost')->count(), icon: 'x', color: '#dc2626')
            ->chart('Enquiries over time', 'trend', $period->trend(
                $current->map(fn (Enquiry $enquiry): array => ['date' => $enquiry->created_at, 'value' => 1]),
            ))
            ->chart('Enquiries by status', 'donut', $this->countEach($current, 'status', Enquiry::STATUSES))
            ->chart('Enquiries by source', 'bar', $this->countBy($current, fn (Enquiry $e) => $e->source ? $this->humanise($e->source) : 'Not recorded', 10))
            ->chart('Most enquired products', 'bar', $this->countBy($current->whereNotNull('product_id'), fn (Enquiry $e) => $e->product?->name, 10))
            ->table('Enquiries by assignee', [
                'name' => 'Assigned to',
                'total' => ['Enquiries', 'number'],
                'open' => ['Open', 'number'],
                'converted' => ['Converted', 'number'],
                'lost' => ['Lost', 'number'],
                'rate' => ['Conversion', 'percent'],
            ], $current
                ->groupBy(fn (Enquiry $e): string => $e->assignee?->name ?? 'Unassigned')
                ->map(fn (Collection $group, string $name): array => [
                    'name' => $name,
                    'total' => $group->count(),
                    'open' => $open($group),
                    'converted' => $group->where('status', 'converted')->count(),
                    'lost' => $group->where('status', 'lost')->count(),
                    'rate' => $this->percent($group->where('status', 'converted')->count(), $group->count()),
                ])
                ->sortByDesc('total')
                ->values()
                ->all());
    }

    /**
     * @return Collection<int, Enquiry>
     */
    private function enquiries(AnalyticsPeriod $period): Collection
    {
        return $this->within(Enquiry::query(), $period)
            ->with(['assignee:id,name', 'product:id,name'])
            ->get(['id', 'status', 'source', 'product_id', 'assigned_to', 'created_at']);
    }
}
