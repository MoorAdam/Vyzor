<?php

namespace Tests\Unit\GoogleAnalytics;

use App\Modules\Analytics\GoogleAnalytics\Enums\GaDimension;
use App\Modules\Analytics\GoogleAnalytics\Enums\GaMetric;
use App\Modules\Analytics\GoogleAnalytics\Queries\DateRange;
use App\Modules\Analytics\GoogleAnalytics\Queries\ReportRequest;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ReportRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-05-05 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_dimensions_and_metrics_normalize_enums_to_strings(): void
    {
        $req = new ReportRequest(
            dimensions: [GaDimension::PagePath, 'pageTitle'],
            metrics:    [GaMetric::Sessions, 'totalUsers'],
            dateRange:  DateRange::today(),
        );

        $this->assertSame(['pagePath', 'pageTitle'], $req->dimensions);
        $this->assertSame(['sessions', 'totalUsers'], $req->metrics);
    }

    public function test_cache_signature_is_stable_for_equivalent_inputs(): void
    {
        $a = new ReportRequest(
            dimensions: [GaDimension::PagePath],
            metrics:    [GaMetric::Sessions],
            dateRange:  DateRange::lastNDays(7),
            limit:      50,
        );

        $b = new ReportRequest(
            dimensions: ['pagePath'],
            metrics:    ['sessions'],
            dateRange:  DateRange::lastNDays(7),
            limit:      50,
        );

        $this->assertSame($a->cacheSignature(), $b->cacheSignature());
    }

    public function test_cache_signature_changes_with_any_meaningful_input(): void
    {
        $base = new ReportRequest([GaDimension::PagePath], [GaMetric::Sessions], DateRange::today(), limit: 10);

        $diffMetric    = new ReportRequest([GaDimension::PagePath], [GaMetric::TotalUsers], DateRange::today(), limit: 10);
        $diffDimension = new ReportRequest([GaDimension::PageTitle], [GaMetric::Sessions], DateRange::today(), limit: 10);
        $diffRange     = new ReportRequest([GaDimension::PagePath], [GaMetric::Sessions], DateRange::yesterday(), limit: 10);
        $diffLimit     = new ReportRequest([GaDimension::PagePath], [GaMetric::Sessions], DateRange::today(), limit: 11);
        $diffOffset    = new ReportRequest([GaDimension::PagePath], [GaMetric::Sessions], DateRange::today(), limit: 10, offset: 5);

        $this->assertNotSame($base->cacheSignature(), $diffMetric->cacheSignature());
        $this->assertNotSame($base->cacheSignature(), $diffDimension->cacheSignature());
        $this->assertNotSame($base->cacheSignature(), $diffRange->cacheSignature());
        $this->assertNotSame($base->cacheSignature(), $diffLimit->cacheSignature());
        $this->assertNotSame($base->cacheSignature(), $diffOffset->cacheSignature());
    }

    public function test_to_ga_request_sets_property_and_metrics(): void
    {
        $req = new ReportRequest(
            dimensions: [GaDimension::PagePath],
            metrics:    [GaMetric::Sessions, GaMetric::TotalUsers],
            dateRange:  DateRange::today(),
            limit:      25,
            offset:     10,
        );

        $ga = $req->toGaRequest('properties/123');

        $this->assertSame('properties/123', $ga->getProperty());
        $this->assertCount(1, $ga->getDimensions());
        $this->assertSame('pagePath', $ga->getDimensions()[0]->getName());
        $this->assertCount(2, $ga->getMetrics());
        $this->assertSame('sessions', $ga->getMetrics()[0]->getName());
        $this->assertSame('totalUsers', $ga->getMetrics()[1]->getName());
        $this->assertSame(25, $ga->getLimit());
        $this->assertSame(10, $ga->getOffset());
    }
}
