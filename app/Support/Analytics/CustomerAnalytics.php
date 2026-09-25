<?php

namespace App\Support\Analytics;

use App\Models\Customer;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Collection;

/**
 * New customers, and which customers are being quoted and won.
 */
class CustomerAnalytics extends AnalyticsSection
{
    public function title(): string
    {
        return 'Customers';
    }

    public function description(): string
    {
        return 'New customers, and the customers quoted and won in the period.';
    }

    public function report(AnalyticsPeriod $period): AnalyticsReport
    {
        $current = $this->customers($period);
        $previous = $this->customers($period->previous());
        $quotations = $this->quotations($period);
        $previousQuotations = $this->quotations($period->previous());

        $quoted = fn (Collection $items): int => $items->unique('customer_id')->count();
        $won = fn (Collection $items): int => $items->where('status', 'accepted')->unique('customer_id')->count();

        return (new AnalyticsReport)
            ->stat('New customers', $current->count(), $previous->count(), icon: 'user-plus', color: '#65a30d')
            ->stat('Total customers', Customer::query()->count(), icon: 'users', color: '#4f46e5')
            ->stat('Customers quoted', $quoted($quotations), $quoted($previousQuotations), icon: 'file', color: '#0ea5e9')
            ->stat('Customers won', $won($quotations), $won($previousQuotations), icon: 'trophy', color: '#16a34a')
            ->chart('New customers over time', 'trend', $period->trend(
                $current->map(fn (Customer $customer): array => ['date' => $customer->created_at, 'value' => 1]),
            ))
            ->chart('New customers by rate tier', 'donut', $this->countBy($current, fn (Customer $c) => $c->rate_tier ? $this->humanise($c->rate_tier) : 'Standard'))
            ->chart('New customers by route', 'bar', $this->countBy($current, fn (Customer $c) => $c->route?->name ?? 'No route', 10))
            ->chart('New customers by owner', 'bar', $this->countBy($current, fn (Customer $c) => $c->assignee?->name, 10))
            ->table('Top customers in the period', [
                'name' => 'Customer',
                'quotations' => ['Quotations', 'number'],
                'quoted' => ['Quoted', 'money'],
                'won' => ['Won', 'money'],
            ], $quotations
                ->groupBy('customer_id')
                ->map(fn (Collection $group): array => [
                    'name' => $group->first()?->customer?->name ?? '—',
                    'quotations' => $group->count(),
                    'quoted' => round((float) $group->sum('total'), 2),
                    'won' => round((float) $group->where('status', 'accepted')->sum('total'), 2),
                ])
                ->sortByDesc('won')
                ->take(10)
                ->values()
                ->all());
    }

    /**
     * @return Collection<int, Customer>
     */
    private function customers(AnalyticsPeriod $period): Collection
    {
        return $this->within(Customer::query(), $period)
            ->with(['route:id,name', 'assignee:id,name'])
            ->get(['id', 'rate_tier', 'route_id', 'assigned_to', 'created_at']);
    }

    /**
     * @return Collection<int, Quotation>
     */
    private function quotations(AnalyticsPeriod $period): Collection
    {
        return $this->within(Quotation::query(), $period, 'quotation_date')
            ->whereNotNull('customer_id')
            ->where('status', '!=', 'revised')
            ->with('customer:id,name')
            ->get(['id', 'customer_id', 'status', 'total']);
    }
}
