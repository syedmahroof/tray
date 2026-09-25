<?php

namespace App\Support\Analytics;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

/**
 * The stretch of time an analytics page reports on, picked from a preset or a
 * custom range, together with the equal stretch before it for comparison and
 * the buckets its trend is drawn in.
 */
final readonly class AnalyticsPeriod
{
    /**
     * The presets offered on every analytics page, in the order they are shown.
     *
     * @var array<string, string>
     */
    public const array PRESETS = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_quarter' => 'This quarter',
        'this_year' => 'This year',
        'last_year' => 'Last year',
        'custom' => 'Custom range',
    ];

    public const string DEFAULT = '30d';

    public function __construct(
        public string $key,
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    /**
     * Read the period from the request, falling back to the default preset
     * when it is missing or a custom range is incomplete or back to front.
     */
    public static function fromRequest(Request $request): self
    {
        $key = (string) $request->input('period', self::DEFAULT);

        if ($key === 'custom') {
            $from = self::parseDate($request->input('from'));
            $to = self::parseDate($request->input('to'));

            if ($from !== null && $to !== null && $from->lte($to)) {
                return new self('custom', $from->startOfDay(), $to->endOfDay());
            }

            $key = self::DEFAULT;
        }

        return self::preset(array_key_exists($key, self::PRESETS) ? $key : self::DEFAULT);
    }

    /**
     * Build one of the named presets relative to today.
     */
    public static function preset(string $key): self
    {
        $today = CarbonImmutable::today();

        [$start, $end] = match ($key) {
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            '7d' => [$today->subDays(6), $today],
            '90d' => [$today->subDays(89), $today],
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$today->startOfQuarter(), $today],
            'this_year' => [$today->startOfYear(), $today],
            'last_year' => [$today->subYear()->startOfYear(), $today->subYear()->endOfYear()],
            default => [$today->subDays(29), $today],
        };

        return new self($key, $start->startOfDay(), $end->endOfDay());
    }

    /**
     * The equally long stretch immediately before this one.
     */
    public function previous(): self
    {
        $days = $this->days();
        $end = $this->start->subDay()->endOfDay();

        return new self($this->key, $end->subDays($days - 1)->startOfDay(), $end);
    }

    /**
     * How many calendar days the period spans, both ends included.
     */
    public function days(): int
    {
        return (int) $this->start->startOfDay()->diffInDays($this->end->startOfDay()) + 1;
    }

    /**
     * The trend is drawn by day for up to two months, by week for up to six,
     * and by month beyond that.
     */
    public function granularity(): string
    {
        return match (true) {
            $this->days() <= 62 => 'day',
            $this->days() <= 185 => 'week',
            default => 'month',
        };
    }

    /**
     * The bucket a date falls into at this period's granularity.
     */
    public function bucketKey(CarbonInterface|string $date): string
    {
        $date = CarbonImmutable::parse($date);

        return match ($this->granularity()) {
            'day' => $date->format('Y-m-d'),
            'week' => $date->startOfWeek()->format('Y-m-d'),
            default => $date->format('Y-m'),
        };
    }

    /**
     * Every bucket in the period, in order, keyed by bucket key and labelled
     * for the chart axis.
     *
     * @return array<string, string>
     */
    public function buckets(): array
    {
        $buckets = [];
        $cursor = match ($this->granularity()) {
            'day' => $this->start,
            'week' => $this->start->startOfWeek(),
            default => $this->start->startOfMonth(),
        };

        while ($cursor->lte($this->end)) {
            $buckets[$this->bucketKey($cursor)] = match ($this->granularity()) {
                'day' => $cursor->format('d M'),
                'week' => $cursor->format('d M'),
                default => $cursor->format('M Y'),
            };

            $cursor = match ($this->granularity()) {
                'day' => $cursor->addDay(),
                'week' => $cursor->addWeek(),
                default => $cursor->addMonthNoOverflow(),
            };
        }

        return $buckets;
    }

    /**
     * Spread dated values over the period's buckets, summing any that share
     * one, with empty buckets kept at zero so the trend reads continuously.
     *
     * @param  iterable<array{date: mixed, value: float|int}>  $points
     * @return list<array{label: string, value: float|int}>
     */
    public function trend(iterable $points): array
    {
        $totals = array_fill_keys(array_keys($this->buckets()), 0);

        foreach ($points as $point) {
            $key = $this->bucketKey($point['date']);

            if (array_key_exists($key, $totals)) {
                $totals[$key] += $point['value'];
            }
        }

        return array_map(
            fn (string $key, string $label): array => ['label' => $label, 'value' => $totals[$key]],
            array_keys($this->buckets()),
            $this->buckets(),
        );
    }

    /**
     * The period as the page shows and echoes it back.
     *
     * @return array{key: string, label: string, from: string, to: string, granularity: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->key === 'custom'
                ? $this->start->format('d M Y').' – '.$this->end->format('d M Y')
                : self::PRESETS[$this->key],
            'from' => $this->start->toDateString(),
            'to' => $this->end->toDateString(),
            'granularity' => $this->granularity(),
        ];
    }

    /**
     * The presets as options for the period picker.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (string $value, string $label): array => ['value' => $value, 'label' => $label],
            array_keys(self::PRESETS),
            self::PRESETS,
        );
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
