<?php

namespace App\Support\Analytics;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

/**
 * How much is being ordered, how much of it is fulfilled, and with whom.
 * Cancelled orders are counted but left out of every value.
 */
abstract class OrderAnalytics extends AnalyticsSection
{
    /**
     * The order type this section reports on: `sale` or `purchase`.
     */
    abstract protected function type(): string;

    public function report(AnalyticsPeriod $period): AnalyticsReport
    {
        $current = $this->orders($period);
        $previous = $this->orders($period->previous());
        $isSale = $this->type() === 'sale';
        $party = $isSale ? 'Customer' : 'Supplier';

        return (new AnalyticsReport)
            ->stat('Orders', $current->count(), $previous->count(), icon: 'clipboard', color: '#4f46e5')
            ->stat('Order value', $this->value($current), $this->value($previous), 'money', 'wallet', '#0ea5e9')
            ->stat('Fulfilled value', $this->value($current->where('status', 'fulfilled')), $this->value($previous->where('status', 'fulfilled')), 'money', 'check', '#16a34a')
            ->stat('Open value', $this->value($this->open($current)), $this->value($this->open($previous)), 'money', 'clock', '#d97706')
            ->stat('Average order', $this->average($current), $this->average($previous), 'money', 'scale', '#7c3aed')
            ->chart('Order value over time', 'trend', $period->trend(
                $this->live($current)->map(fn (Order $order): array => ['date' => $order->order_date, 'value' => (float) $order->total]),
            ), 'money')
            ->chart('Orders by status', 'donut', $this->countEach($current, 'status', Order::STATUSES))
            ->chart("Order value by {$this->ownerLabel()}", 'bar', $this->sumBy(
                $this->live($current),
                fn (Order $order) => $isSale ? $order->creator?->name : $order->supplier?->name,
                fn (Order $order) => (float) $order->total,
                10,
            ), 'money')
            ->table('Top '.strtolower($party).'s by order value', [
                'name' => $party,
                'orders' => ['Orders', 'number'],
                'value' => ['Value', 'money'],
                'fulfilled' => ['Fulfilled', 'money'],
            ], $this->live($current)
                ->groupBy(fn (Order $order): string => $order->partyName() ?? '—')
                ->map(fn (Collection $group, string $name): array => [
                    'name' => $name,
                    'orders' => $group->count(),
                    'value' => $this->value($group),
                    'fulfilled' => $this->value($group->where('status', 'fulfilled')),
                ])
                ->sortByDesc('value')
                ->take(10)
                ->values()
                ->all())
            ->table('Largest orders', [
                'number' => 'Number',
                'party' => $party,
                'date' => ['Date', 'date'],
                'status' => ['Status', 'status'],
                'total' => ['Total', 'money'],
            ], $this->live($current)
                ->sortByDesc('total')
                ->take(10)
                ->map(fn (Order $order): array => [
                    'number' => $order->number,
                    'party' => $order->partyName() ?? '—',
                    'date' => $order->order_date->toDateString(),
                    'status' => $order->status,
                    'total' => (float) $order->total,
                ])
                ->values()
                ->all());
    }

    /**
     * Who the value-by bar chart groups on: the salesperson for sales, the
     * supplier for purchases.
     */
    private function ownerLabel(): string
    {
        return $this->type() === 'sale' ? 'salesperson' : 'supplier';
    }

    /**
     * @return Collection<int, Order>
     */
    private function orders(AnalyticsPeriod $period): Collection
    {
        return $this->within(Order::query(), $period, 'order_date')
            ->where('type', $this->type())
            ->with(['creator:id,name', 'customer:id,name', 'supplier:id,name'])
            ->get(['id', 'type', 'number', 'status', 'total', 'order_date', 'created_by', 'customer_id', 'supplier_id']);
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, Order>
     */
    private function live(Collection $orders): Collection
    {
        return $orders->where('status', '!=', 'cancelled');
    }

    /**
     * Orders confirmed but not yet fully delivered.
     *
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, Order>
     */
    private function open(Collection $orders): Collection
    {
        return $orders->whereIn('status', ['confirmed', 'partially_fulfilled']);
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function value(Collection $orders): float
    {
        return round((float) $this->live($orders)->sum('total'), 2);
    }

    /**
     * @param  Collection<int, Order>  $orders
     */
    private function average(Collection $orders): float
    {
        $live = $this->live($orders);

        return $live->count() ? round((float) $live->avg('total'), 2) : 0.0;
    }
}
