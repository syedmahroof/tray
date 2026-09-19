<?php

namespace App\Support;

use App\Models\Country;
use App\Models\District;
use App\Models\Location;
use App\Models\Route;
use App\Models\State;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Schema;

/**
 * Shared "filter by place / route" plumbing for the CRM listing screens.
 */
class RouteLocationFilter
{
    /**
     * The location tree tiers a listing can be narrowed by, widest first.
     *
     * Each tier maps to the relation path that reaches it from a listing which
     * does not carry the column itself, such as visit reports.
     *
     * @var array<string, string>
     */
    private const TIERS = [
        'country_id' => 'location.district.state',
        'state_id' => 'location.district',
        'district_id' => 'location',
    ];

    /**
     * Narrow a listing query to the requested place and route.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<TModel>  $query
     * @return EloquentBuilder<TModel>
     */
    public static function apply(EloquentBuilder $query, Request $request): EloquentBuilder
    {
        $query
            ->when($request->input('location_id'), fn ($q, $value) => $q->where('location_id', $value))
            ->when($request->input('route_id'), fn ($q, $value) => $q->where('route_id', $value));

        foreach (self::TIERS as $column => $relation) {
            $query->when(
                $request->input($column),
                fn (EloquentBuilder $q, $value) => self::hasColumn($q->getModel(), $column)
                    ? $q->where($column, $value)
                    : $q->whereHas($relation, fn ($tier) => $tier->where($column, $value)),
            );
        }

        return $query;
    }

    /**
     * The places and routes a listing can actually be filtered by.
     *
     * Only those already in use by the listed model are offered, so the pickers
     * stay short and never contain an option that would return nothing.
     *
     * @param  string  $relation  the relation on Location/Route pointing back at the listed model
     * @return array{
     *     countries: Collection<int, Country>,
     *     states: Collection<int, State>,
     *     districts: Collection<int, District>,
     *     locations: Collection<int, Location>,
     *     routes: Collection<int, Route>,
     * }
     */
    public static function options(string $relation): array
    {
        $model = self::listedModel($relation);

        return [
            'countries' => Country::query()
                ->whereIn('id', self::tierIds($model, $relation, 'country_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'states' => State::query()
                ->whereIn('id', self::tierIds($model, $relation, 'state_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'country_id']),
            'districts' => District::query()
                ->whereIn('id', self::tierIds($model, $relation, 'district_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'state_id']),
            'locations' => Location::query()
                ->whereHas($relation)
                ->with('district:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'district_id']),
            'routes' => Route::query()
                ->whereHas($relation)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    /**
     * The filter values to echo back to the page.
     *
     * @return array{country_id: mixed, state_id: mixed, district_id: mixed, location_id: mixed, route_id: mixed}
     */
    public static function filters(Request $request): array
    {
        return [
            'country_id' => $request->input('country_id'),
            'state_id' => $request->input('state_id'),
            'district_id' => $request->input('district_id'),
            'location_id' => $request->input('location_id'),
            'route_id' => $request->input('route_id'),
        ];
    }

    /**
     * The model being listed, reached through its relation on Location.
     */
    private static function listedModel(string $relation): Model
    {
        /** @var Model $related */
        $related = (new Location)->{$relation}()->getRelated();

        return $related;
    }

    /**
     * The ids of one tier of the location tree that the listing actually sits in.
     *
     * Listings that carry the tier themselves are read straight off their own
     * column; the rest, such as visit reports, are reached through the location
     * tree above them.
     *
     * @return SupportCollection<int, int>
     */
    private static function tierIds(Model $model, string $relation, string $column): SupportCollection
    {
        if (self::hasColumn($model, $column)) {
            return $model->newQuery()->whereNotNull($column)->distinct()->pluck($column);
        }

        return match ($column) {
            'district_id' => Location::query()->whereHas($relation)->distinct()->pluck('district_id'),
            'state_id' => District::query()->whereHas('locations', fn ($q) => $q->whereHas($relation))->distinct()->pluck('state_id'),
            default => State::query()->whereHas('districts.locations', fn ($q) => $q->whereHas($relation))->distinct()->pluck('country_id'),
        };
    }

    /**
     * Determine whether the listed model stores the given tier itself.
     */
    private static function hasColumn(Model $model, string $column): bool
    {
        return Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), $column);
    }
}
