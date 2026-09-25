<?php

namespace App\Support\Analytics;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One page under the Analytics menu: what it is called and how its report is
 * worked out for a period. The helpers keep the counting and grouping the
 * sections share in one place.
 */
abstract class AnalyticsSection
{
    /**
     * A palette for categorical charts, in the order categories are coloured.
     *
     * @var list<string>
     */
    protected const array PALETTE = ['#4f46e5', '#0ea5e9', '#16a34a', '#d97706', '#dc2626', '#7c3aed', '#0891b2', '#65a30d', '#db2777', '#64748b'];

    abstract public function title(): string;

    abstract public function description(): string;

    abstract public function report(AnalyticsPeriod $period): AnalyticsReport;

    /**
     * Narrow a query to the period on the given date column.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function within(Builder $query, AnalyticsPeriod $period, string $column = 'created_at'): Builder
    {
        return $query->whereBetween($column, $this->bounds($period, $column));
    }

    /**
     * Date-only columns compare against plain dates; timestamps against the
     * full start and end of day.
     *
     * @return array{0: string, 1: string}
     */
    protected function bounds(AnalyticsPeriod $period, string $column): array
    {
        return Str::endsWith($column, '_date')
            ? [$period->start->toDateString(), $period->end->toDateString()]
            : [$period->start->toDateTimeString(), $period->end->toDateTimeString()];
    }

    /**
     * Count rows per label, largest first, as chart data.
     *
     * @param  Collection<int, mixed>  $items
     * @return list<array{label: string, value: int|float, color: string}>
     */
    protected function countBy(Collection $items, callable $label, ?int $limit = null): array
    {
        $counts = $items
            ->groupBy(fn ($item): string => (string) ($label($item) ?? 'Unassigned'))
            ->map->count()
            ->sortDesc();

        if ($limit !== null) {
            $counts = $counts->take($limit);
        }

        return $this->colour($counts);
    }

    /**
     * Sum a value per label, largest first, as chart data.
     *
     * @param  Collection<int, mixed>  $items
     * @return list<array{label: string, value: int|float, color: string}>
     */
    protected function sumBy(Collection $items, callable $label, callable $value, ?int $limit = null): array
    {
        $sums = $items
            ->groupBy(fn ($item): string => (string) ($label($item) ?? 'Unassigned'))
            ->map(fn (Collection $group): float => round((float) $group->sum($value), 2))
            ->sortDesc();

        if ($limit !== null) {
            $sums = $sums->take($limit);
        }

        return $this->colour($sums);
    }

    /**
     * Count rows for each of a fixed list of labels, in that order, keeping
     * the ones with none so every status always shows.
     *
     * @param  Collection<int, mixed>  $items
     * @param  list<string>  $labels
     * @return list<array{label: string, value: int|float, color: string}>
     */
    protected function countEach(Collection $items, string $attribute, array $labels): array
    {
        $counts = $items->countBy($attribute);

        return $this->colour(collect($labels)->mapWithKeys(
            fn (string $label): array => [$this->humanise($label) => (int) $counts->get($label, 0)],
        ));
    }

    /**
     * Turn [label => value] into chart data with a colour per category.
     *
     * @param  Collection<array-key, int|float>  $values
     * @return list<array{label: string, value: int|float, color: string}>
     */
    protected function colour(Collection $values): array
    {
        return $values
            ->map(fn ($value, $label): array => ['label' => (string) $label, 'value' => $value])
            ->values()
            ->map(fn (array $datum, int $index): array => [...$datum, 'color' => self::PALETTE[$index % count(self::PALETTE)]])
            ->values()
            ->all();
    }

    /**
     * A share of a whole as a percentage, zero when there is no whole.
     */
    protected function percent(float|int $part, float|int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }

    protected function humanise(string $value): string
    {
        return Str::of($value)->replace(['_', '-'], ' ')->title()->toString();
    }
}
