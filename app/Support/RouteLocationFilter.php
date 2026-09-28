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

/**
 * Shared "filter by place / route" plumbing for the CRM listing screens.
 */
class RouteLocationFilter
{
    /**
     * The place and route columns a listing can be narrowed by, widest first.
     *
     * @var list<string>
     */
    private const COLUMNS = ['country_id', 'state_id', 'district_id', 'location_id', 'route_id'];

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
        foreach (self::COLUMNS as $column) {
            $query->when(
                $request->input($column),
                fn (EloquentBuilder $q, $value) => $q->where($q->qualifyColumn($column), $value),
            );
        }

        return $query;
    }

    /**
     * Narrow a listing with no place of its own, such as visit reports, to the
     * records linked to any entity that sits in the requested place and route.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  EloquentBuilder<TModel>  $query
     * @param  list<string>  $relations  the listed model's relations to its linked entities
     * @return EloquentBuilder<TModel>
     */
    public static function applyThroughLinks(EloquentBuilder $query, Request $request, array $relations): EloquentBuilder
    {
        foreach (self::COLUMNS as $column) {
            $query->when(
                $request->input($column),
                fn (EloquentBuilder $q, $value) => $q->where(function (EloquentBuilder $linked) use ($relations, $column, $value) {
                    foreach ($relations as $relation) {
                        $linked->orWhereHas(
                            $relation,
                            fn (EloquentBuilder $entity) => $entity->where($entity->qualifyColumn($column), $value),
                        );
                    }
                }),
            );
        }

        return $query;
    }

    /**
     * The places and routes a listing can be filtered by.
     *
     * The country, state and district pickers offer only the tiers the listed
     * models already sit in, so they stay short. Every active location and
     * route is offered, so those filters are always there to pick from, even
     * before any record has been given one.
     *
     * @param  string|list<string>  $relations  the relation(s) on Location pointing at the models that carry the place
     * @return array{
     *     countries: Collection<int, Country>,
     *     states: Collection<int, State>,
     *     districts: Collection<int, District>,
     *     locations: Collection<int, Location>,
     *     routes: Collection<int, Route>,
     * }
     */
    public static function options(string|array $relations): array
    {
        $models = array_map(self::listedModel(...), (array) $relations);

        return [
            'countries' => Country::query()
                ->whereIn('id', self::tierIds($models, 'country_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'states' => State::query()
                ->whereIn('id', self::tierIds($models, 'state_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'country_id']),
            'districts' => District::query()
                ->whereIn('id', self::tierIds($models, 'district_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'state_id']),
            'locations' => Location::query()
                ->where('is_active', true)
                ->with('district:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'district_id']),
            'routes' => Route::query()
                ->where('is_active', true)
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
     * The ids of one tier of the location tree that the given models sit in.
     *
     * @param  list<Model>  $models
     * @return SupportCollection<int, int>
     */
    private static function tierIds(array $models, string $column): SupportCollection
    {
        return collect($models)
            ->flatMap(fn (Model $model) => $model->newQuery()->whereNotNull($column)->distinct()->pluck($column))
            ->unique()
            ->values();
    }
}
