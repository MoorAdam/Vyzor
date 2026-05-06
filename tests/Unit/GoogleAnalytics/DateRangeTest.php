<?php

namespace Tests\Unit\GoogleAnalytics;

use App\Modules\Analytics\GoogleAnalytics\Queries\DateRange;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Pin "today" so tests are deterministic.
        CarbonImmutable::setTestNow('2026-05-05 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_today_constructor(): void
    {
        $r = DateRange::today();
        $this->assertSame('2026-05-05', $r->startString());
        $this->assertSame('2026-05-05', $r->endString());
        $this->assertSame(1, $r->days());
    }

    public function test_yesterday_constructor(): void
    {
        $r = DateRange::yesterday();
        $this->assertSame('2026-05-04', $r->startString());
        $this->assertSame('2026-05-04', $r->endString());
        $this->assertSame(1, $r->days());
        $this->assertTrue($r->isOnlyYesterday());
    }

    public function test_last_n_days(): void
    {
        $r = DateRange::lastNDays(7);
        $this->assertSame('2026-04-29', $r->startString());
        $this->assertSame('2026-05-05', $r->endString());
        $this->assertSame(7, $r->days());
    }

    public function test_between_accepts_strings_and_objects(): void
    {
        $r1 = DateRange::between('2026-04-01', '2026-04-15');
        $r2 = DateRange::between(new \DateTimeImmutable('2026-04-01'), new \DateTimeImmutable('2026-04-15'));

        $this->assertSame($r1->signature(), $r2->signature());
        $this->assertSame(15, $r1->days());
    }

    public function test_includes_today(): void
    {
        $this->assertTrue(DateRange::today()->includesToday());
        $this->assertTrue(DateRange::lastNDays(7)->includesToday());
        $this->assertFalse(DateRange::yesterday()->includesToday());
        $this->assertFalse(DateRange::between('2026-01-01', '2026-01-31')->includesToday());
    }

    public function test_is_only_yesterday(): void
    {
        $this->assertTrue(DateRange::yesterday()->isOnlyYesterday());
        $this->assertFalse(DateRange::today()->isOnlyYesterday());
        $this->assertFalse(DateRange::lastNDays(2)->isOnlyYesterday());
    }

    public function test_ends_at_least_days_ago(): void
    {
        // A range ending 2026-04-25 (10 days before frozen "today")
        $r = DateRange::between('2026-04-01', '2026-04-25');
        $this->assertTrue($r->endsAtLeastDaysAgo(8));
        $this->assertTrue($r->endsAtLeastDaysAgo(10));
        $this->assertFalse($r->endsAtLeastDaysAgo(11));
    }

    public function test_previous_period_is_same_length_immediately_before(): void
    {
        $current = DateRange::lastNDays(7); // 2026-04-29..2026-05-05
        $prev    = $current->previousPeriod();

        $this->assertSame('2026-04-22', $prev->startString());
        $this->assertSame('2026-04-28', $prev->endString());
        $this->assertSame(7, $prev->days());
    }

    public function test_signature_is_stable_and_human_readable(): void
    {
        $r = DateRange::between('2026-04-01', '2026-04-15');
        $this->assertSame('2026-04-01..2026-04-15', $r->signature());
    }

    public function test_invalid_range_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DateRange::between('2026-05-10', '2026-05-01');
    }

    public function test_last_n_days_rejects_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        DateRange::lastNDays(0);
    }
}
