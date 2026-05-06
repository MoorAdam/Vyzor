<?php

namespace Tests\Feature\GoogleAnalytics;

use App\Modules\Analytics\GoogleAnalytics\Enums\GaCacheTier;
use App\Modules\Analytics\GoogleAnalytics\Queries\DateRange;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsCache;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class CacheTierTest extends TestCase
{
    private GoogleAnalyticsCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-05-05 10:00:00');
        $this->cache = new GoogleAnalyticsCache();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_today_tier_for_range_including_today(): void
    {
        $this->assertSame(GaCacheTier::Today, $this->cache->tierFor(DateRange::today()));
        $this->assertSame(GaCacheTier::Today, $this->cache->tierFor(DateRange::lastNDays(7)));
        $this->assertSame(GaCacheTier::Today, $this->cache->tierFor(DateRange::lastNDays(30)));
    }

    public function test_yesterday_tier_only_for_yesterday_alone(): void
    {
        $this->assertSame(GaCacheTier::Yesterday, $this->cache->tierFor(DateRange::yesterday()));
    }

    public function test_recent_tier_for_2_to_7_days_ago(): void
    {
        // Range ending 5 days ago (today is 2026-05-05, so 2026-04-30) — past, but < 8 days back.
        $r = DateRange::between('2026-04-25', '2026-04-30');
        $this->assertSame(GaCacheTier::Recent, $this->cache->tierFor($r));
    }

    public function test_historical_tier_for_8_plus_days_ago(): void
    {
        // Ends 2026-04-20 — 15 days before "today".
        $r = DateRange::between('2026-04-01', '2026-04-20');
        $this->assertSame(GaCacheTier::Historical, $this->cache->tierFor($r));
    }

    public function test_ttl_seconds_come_from_config(): void
    {
        config(['services.google_analytics.cache.today_ttl' => 999]);
        config(['services.google_analytics.cache.realtime_ttl' => 7]);
        config(['services.google_analytics.cache.historical_ttl' => 1234]);

        $this->assertSame(999,  GaCacheTier::Today->ttlSeconds());
        $this->assertSame(7,    GaCacheTier::Realtime->ttlSeconds());
        $this->assertSame(1234, GaCacheTier::Historical->ttlSeconds());
    }

    public function test_key_for_strips_properties_prefix(): void
    {
        $key = $this->cache->keyFor('properties/123456789', 'top-pages', 'sig123');
        $this->assertSame('ga:123456789:top-pages:sig123', $key);
    }
}
