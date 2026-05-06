<?php

namespace App\Modules\Analytics\GoogleAnalytics\DTOs;

use Carbon\CarbonImmutable;

/**
 * High-level traffic snapshot for a date range — what a "site overview" card shows.
 */
final class TrafficOverview
{
    public function __construct(
        public readonly int $sessions,
        public readonly int $totalUsers,
        public readonly int $newUsers,
        public readonly int $engagedSessions,
        public readonly int $screenPageViews,
        public readonly float $engagementRate,    // 0..1
        public readonly float $bounceRate,         // 0..1
        public readonly float $averageSessionDurationSeconds,
        public readonly CarbonImmutable $fetchedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'sessions'                       => $this->sessions,
            'totalUsers'                     => $this->totalUsers,
            'newUsers'                       => $this->newUsers,
            'engagedSessions'                => $this->engagedSessions,
            'screenPageViews'                => $this->screenPageViews,
            'engagementRate'                 => $this->engagementRate,
            'bounceRate'                     => $this->bounceRate,
            'averageSessionDurationSeconds'  => $this->averageSessionDurationSeconds,
            'fetchedAt'                      => $this->fetchedAt->toIso8601String(),
        ];
    }
}
