<?php

namespace App\Modules\Analytics\GoogleAnalytics\Queries;

use Carbon\CarbonImmutable;
use Google\Analytics\Data\V1beta\DateRange as GaDateRange;

/**
 * Immutable date range value object used by all GA queries.
 *
 * Stores absolute dates internally so cache-tier resolution is deterministic,
 * while exposing helpers that produce the YYYY-MM-DD strings GA4's API expects.
 */
final class DateRange
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {
        if ($this->start->gt($this->end)) {
            throw new \InvalidArgumentException(
                "DateRange start ({$this->start->toDateString()}) is after end ({$this->end->toDateString()})."
            );
        }
    }

    public static function today(): self
    {
        $today = CarbonImmutable::today();
        return new self($today, $today);
    }

    public static function yesterday(): self
    {
        $y = CarbonImmutable::yesterday();
        return new self($y, $y);
    }

    public static function lastNDays(int $n): self
    {
        if ($n < 1) {
            throw new \InvalidArgumentException('lastNDays requires n >= 1');
        }
        $end   = CarbonImmutable::today();
        $start = $end->subDays($n - 1);
        return new self($start, $end);
    }

    public static function last7Days(): self  { return self::lastNDays(7); }
    public static function last28Days(): self { return self::lastNDays(28); }
    public static function last30Days(): self { return self::lastNDays(30); }

    /**
     * Build a range from anything Carbon can parse.
     */
    public static function between(string|\DateTimeInterface $start, string|\DateTimeInterface $end): self
    {
        return new self(
            CarbonImmutable::parse($start)->startOfDay(),
            CarbonImmutable::parse($end)->startOfDay(),
        );
    }

    public function startString(): string { return $this->start->toDateString(); }
    public function endString(): string   { return $this->end->toDateString(); }

    public function toGaDateRange(): GaDateRange
    {
        return new GaDateRange([
            'start_date' => $this->startString(),
            'end_date'   => $this->endString(),
        ]);
    }

    public function days(): int
    {
        return $this->start->diffInDays($this->end) + 1;
    }

    public function includesToday(): bool
    {
        return $this->end->isSameDay(CarbonImmutable::today())
            || $this->end->gt(CarbonImmutable::today());
    }

    public function isOnlyYesterday(): bool
    {
        $y = CarbonImmutable::yesterday();
        return $this->start->isSameDay($y) && $this->end->isSameDay($y);
    }

    public function endsAtLeastDaysAgo(int $days): bool
    {
        return $this->end->lte(CarbonImmutable::today()->subDays($days));
    }

    /**
     * A short, deterministic identifier suitable for hashing into cache keys.
     */
    public function signature(): string
    {
        return $this->startString() . '..' . $this->endString();
    }

    /**
     * Returns a previous range of the same length, ending the day before this range starts.
     */
    public function previousPeriod(): self
    {
        $length = $this->days();
        $end    = $this->start->subDay();
        $start  = $end->subDays($length - 1);
        return new self($start, $end);
    }
}
