<?php

namespace App\Modules\Analytics\GoogleAnalytics\Enums;

/**
 * Tiers used by GoogleAnalyticsCache to choose a TTL based on
 * how recent the queried date range is. The more recent, the shorter the TTL,
 * because the data is still being aggregated by GA.
 */
enum GaCacheTier: string
{
    case Today      = 'today';
    case Yesterday  = 'yesterday';
    case Recent     = 'recent';
    case Historical = 'historical';
    case Realtime   = 'realtime';

    public function ttlSeconds(): int
    {
        return (int) match ($this) {
            self::Today      => config('services.google_analytics.cache.today_ttl', 60 * 15),
            self::Yesterday  => config('services.google_analytics.cache.yesterday_ttl', 60 * 60 * 2),
            self::Recent     => config('services.google_analytics.cache.recent_ttl', 60 * 60 * 12),
            self::Historical => config('services.google_analytics.cache.historical_ttl', 60 * 60 * 24 * 7),
            self::Realtime   => config('services.google_analytics.cache.realtime_ttl', 30),
        };
    }
}
