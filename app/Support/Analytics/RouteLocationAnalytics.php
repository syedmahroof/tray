<?php

namespace App\Support\Analytics;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\VisitReport;
use Illuminate\Database\Eloquent\Collection;

/**
 * Where the work happens: field visits, new customers and business, per route
 * and per location.
 */
class RouteLocationAnalytics extends AnalyticsSection
{
    public function title(): string
    {
        return 'Routes & Locations';
    }

    public function description(): string
    {
        return 'Visits, new customers and quoted business for each route and location.';
    }

    public function report(AnalyticsPeriod $period): AnalyticsReport
    {
        $visits = $this->visits($period);
        $previousVisits = $this->visits($period->previous());
        $customers = $this->within(Customer::query(), $period)
            ->with(['route:id,name', 'location:id,name'])
            ->get(['id', 'route_id', 'location_id']);
        $quotations = $this->within(Quotation::query(), $period, 'quotation_date')
            ->where('status', '!=', 'revised')
            ->whereNotNull('customer_id')
            ->with(['customer:id,route_id,location_id', 'customer.route:id,name', 'customer.location:id,name'])
            ->get(['id', 'customer_id', 'status', 'total']);

        $routeOf = fn ($record): string => $record?->route?->name ?? 'No route';
        $locationOf = fn ($record): string => $record?->location?->name ?? 'No location';

        return (new AnalyticsReport)
            ->stat('Routes covered', $visits->whereNotNull('route_id')->unique('route_id')->count(), $previousVisits->whereNotNull('route_id')->unique('route_id')->count(), icon: 'route', color: '#4f46e5')
            ->stat('Locations visited', $visits->whereNotNull('location_id')->unique('location_id')->count(), $previousVisits->whereNotNull('location_id')->unique('location_id')->count(), icon: 'map-pin', color: '#0ea5e9')
            ->stat('Visits without a route', $this->percent($visits->whereNull('route_id')->count(), $visits->count()), $this->percent($previousVisits->whereNull('route_id')->count(), $previousVisits->count()), 'percent', 'alert', '#d97706')
            ->stat('Customers without a route', Customer::query()->whereNull('route_id')->count(), icon: 'users', color: '#dc2626')
            ->chart('Visits by route', 'bar', $this->countBy($visits, $routeOf, 10))
            ->chart('Quoted value by route', 'bar', $this->sumBy($quotations, fn (Quotation $q) => $routeOf($q->customer), fn (Quotation $q) => (float) $q->total, 10), 'money')
            ->chart('Visits by location', 'bar', $this->countBy($visits, $locationOf, 10))
            ->chart('Quoted value by location', 'bar', $this->sumBy($quotations, fn (Quotation $q) => $locationOf($q->customer), fn (Quotation $q) => (float) $q->total, 10), 'money')
            ->table('Routes', $this->columns('Route'), $this->rows($visits, $customers, $quotations, $routeOf, fn (Quotation $q) => $routeOf($q->customer)))
            ->table('Locations', $this->columns('Location'), $this->rows($visits, $customers, $quotations, $locationOf, fn (Quotation $q) => $locationOf($q->customer)));
    }

    /**
     * @return array<string, string|array{0: string, 1: string}>
     */
    private function columns(string $label): array
    {
        return [
            'name' => $label,
            'visits' => ['Visits', 'number'],
            'customers' => ['New customers', 'number'],
            'quotations' => ['Quotations', 'number'],
            'quoted' => ['Quoted', 'money'],
            'won' => ['Won', 'money'],
        ];
    }

    /**
     * One row per route or location that saw any activity, busiest first.
     *
     * @param  Collection<int, VisitReport>  $visits
     * @param  Collection<int, Customer>  $customers
     * @param  Collection<int, Quotation>  $quotations
     * @return list<array<string, mixed>>
     */
    private function rows(Collection $visits, Collection $customers, Collection $quotations, callable $placeOf, callable $quotationPlace): array
    {
        $visitCounts = $visits->groupBy($placeOf)->map->count();
        $customerCounts = $customers->groupBy($placeOf)->map->count();
        $quotationGroups = $quotations->groupBy($quotationPlace);

        return $visitCounts->keys()
            ->merge($customerCounts->keys())
            ->merge($quotationGroups->keys())
            ->unique()
            ->map(fn (string $name): array => [
                'name' => $name,
                'visits' => (int) $visitCounts->get($name, 0),
                'customers' => (int) $customerCounts->get($name, 0),
                'quotations' => $quotationGroups->get($name, collect())->count(),
                'quoted' => round((float) $quotationGroups->get($name, collect())->sum('total'), 2),
                'won' => round((float) $quotationGroups->get($name, collect())->where('status', 'accepted')->sum('total'), 2),
            ])
            ->sortByDesc(fn (array $row): array => [$row['visits'], $row['quoted']])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, VisitReport>
     */
    private function visits(AnalyticsPeriod $period): Collection
    {
        return $this->within(VisitReport::query(), $period, 'visit_date')
            ->with(['route:id,name', 'location:id,name'])
            ->get(['id', 'route_id', 'location_id']);
    }
}
