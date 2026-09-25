<?php

namespace App\Support\Analytics;

/**
 * What an analytics page shows, in the one shape every section fills: headline
 * figures compared with the period before, charts, and ranked tables.
 */
final class AnalyticsReport
{
    /**
     * @var list<array{label: string, value: float|int, previous: float|int|null, format: string, icon: string, color: string}>
     */
    private array $stats = [];

    /**
     * @var list<array{title: string, type: string, format: string, data: list<array{label: string, value: float|int, color?: string}>}>
     */
    private array $charts = [];

    /**
     * @var list<array{title: string, columns: list<array{key: string, label: string, format: string}>, rows: list<array<string, mixed>>}>
     */
    private array $tables = [];

    /**
     * A headline figure. Give the previous period's value to show the change.
     */
    public function stat(string $label, float|int $value, float|int|null $previous = null, string $format = 'number', string $icon = 'chart', string $color = '#4f46e5'): self
    {
        $this->stats[] = compact('label', 'value', 'previous', 'format', 'icon', 'color');

        return $this;
    }

    /**
     * A chart: "trend" for values over the period, "bar" to compare
     * categories, "donut" for shares of a whole.
     *
     * @param  list<array{label: string, value: float|int, color?: string}>  $data
     */
    public function chart(string $title, string $type, array $data, string $format = 'number'): self
    {
        $this->charts[] = compact('title', 'type', 'format', 'data');

        return $this;
    }

    /**
     * A ranked table. Columns are [key => label] or [key => [label, format]].
     *
     * @param  array<string, string|array{0: string, 1: string}>  $columns
     * @param  iterable<array<string, mixed>>  $rows
     */
    public function table(string $title, array $columns, iterable $rows): self
    {
        $this->tables[] = [
            'title' => $title,
            'columns' => array_map(
                fn (string $key, string|array $column): array => [
                    'key' => $key,
                    'label' => is_array($column) ? $column[0] : $column,
                    'format' => is_array($column) ? $column[1] : 'text',
                ],
                array_keys($columns),
                $columns,
            ),
            'rows' => array_values(is_array($rows) ? $rows : iterator_to_array($rows, false)),
        ];

        return $this;
    }

    /**
     * @return array{stats: list<array<string, mixed>>, charts: list<array<string, mixed>>, tables: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'stats' => $this->stats,
            'charts' => $this->charts,
            'tables' => $this->tables,
        ];
    }
}
