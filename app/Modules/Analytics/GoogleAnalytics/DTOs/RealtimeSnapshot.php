<?php

namespace App\Modules\Analytics\GoogleAnalytics\DTOs;

use Carbon\CarbonImmutable;

/**
 * Snapshot of currently-active users (last 30 minutes), with optional breakdowns.
 */
final class RealtimeSnapshot
{
    /**
     * @param  list<array{label:string,activeUsers:int}>  $byCountry
     * @param  list<array{label:string,activeUsers:int}>  $byDevice
     * @param  list<array{label:string,activeUsers:int}>  $byPage
     */
    public function __construct(
        public readonly int $activeUsers,
        public readonly array $byCountry,
        public readonly array $byDevice,
        public readonly array $byPage,
        public readonly CarbonImmutable $fetchedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'activeUsers' => $this->activeUsers,
            'byCountry'   => $this->byCountry,
            'byDevice'    => $this->byDevice,
            'byPage'      => $this->byPage,
            'fetchedAt'   => $this->fetchedAt->toIso8601String(),
        ];
    }
}
