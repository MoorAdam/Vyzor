<?php

namespace App\Modules\Analytics\GoogleAnalytics\DTOs;

use Carbon\CarbonImmutable;

/**
 * Side-by-side comparison of the same metrics across two date ranges.
 *
 * deltas[$metricName] is null when the previous value is 0 (cannot
 * compute a percentage change) — UIs should show "n/a" or hide the delta.
 */
final class PeriodComparison
{
    /**
     * @param  array<string,float|int> $current      metric name => value
     * @param  array<string,float|int> $previous     metric name => value
     * @param  array<string,float|null> $deltas      metric name => fractional change (0.25 = +25%)
     */
    public function __construct(
        public readonly array $current,
        public readonly array $previous,
        public readonly array $deltas,
        public readonly string $currentRange,    // 'YYYY-MM-DD..YYYY-MM-DD'
        public readonly string $previousRange,
        public readonly CarbonImmutable $fetchedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'current'        => $this->current,
            'previous'       => $this->previous,
            'deltas'         => $this->deltas,
            'currentRange'   => $this->currentRange,
            'previousRange'  => $this->previousRange,
            'fetchedAt'      => $this->fetchedAt->toIso8601String(),
        ];
    }
}
