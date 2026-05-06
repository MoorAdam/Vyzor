<?php

namespace App\Modules\Analytics\GoogleAnalytics\Services;

use App\Modules\Analytics\GoogleAnalytics\DTOs\FunnelResult;
use App\Modules\Analytics\GoogleAnalytics\DTOs\PeriodComparison;
use App\Modules\Analytics\GoogleAnalytics\DTOs\RealtimeSnapshot;
use App\Modules\Analytics\GoogleAnalytics\DTOs\ReportResult;
use App\Modules\Analytics\GoogleAnalytics\DTOs\TrafficOverview;
use App\Modules\Analytics\GoogleAnalytics\Enums\GaDimension;
use App\Modules\Analytics\GoogleAnalytics\Enums\GaMetric;
use App\Modules\Analytics\GoogleAnalytics\Exceptions\PropertyNotConfiguredException;
use App\Modules\Analytics\GoogleAnalytics\Queries\DateRange;
use App\Modules\Analytics\GoogleAnalytics\Queries\FunnelDefinition;
use App\Modules\Analytics\GoogleAnalytics\Queries\RealtimeRequest;
use App\Modules\Analytics\GoogleAnalytics\Queries\ReportRequest;
use App\Modules\Projects\Models\Project;
use Carbon\CarbonImmutable;

/**
 * Domain-level GA4 query API for the rest of the app.
 *
 * Every method:
 *   1. validates that the project has GA configured,
 *   2. builds a ReportRequest (or RealtimeRequest),
 *   3. caches the result with a TTL chosen by recency,
 *   4. returns a typed DTO suitable for both UI and AI consumers.
 *
 * Custom queries that don't fit one of the named methods can use runCustomReport().
 */
class GoogleAnalyticsQueryService
{
    public function __construct(
        private readonly GoogleAnalyticsClient $client,
        private readonly GoogleAnalyticsCache $cache,
    ) {}

    // ── Standard Data API: high-level cards ─────────────────────────────

    public function getTrafficOverview(Project $project, DateRange $range): TrafficOverview
    {
        $property = $this->propertyOrFail($project);
        $tier     = $this->cache->tierFor($range);

        $req = new ReportRequest(
            dimensions: [],
            metrics: [
                GaMetric::Sessions,
                GaMetric::TotalUsers,
                GaMetric::NewUsers,
                GaMetric::EngagedSessions,
                GaMetric::ScreenPageViews,
                GaMetric::EngagementRate,
                GaMetric::BounceRate,
                GaMetric::AverageSessionDuration,
            ],
            dateRange: $range,
            limit: 1,
        );

        $key = $this->cache->keyFor($property, 'traffic-overview', $req->cacheSignature());

        return $this->cache->remember($key, $tier, function () use ($req, $property) {
            $result = $this->runReport($req, $property);

            $row = $result->rows()->first();
            return new TrafficOverview(
                sessions:        (int) ($row?->metric('sessions') ?? 0),
                totalUsers:      (int) ($row?->metric('totalUsers') ?? 0),
                newUsers:        (int) ($row?->metric('newUsers') ?? 0),
                engagedSessions: (int) ($row?->metric('engagedSessions') ?? 0),
                screenPageViews: (int) ($row?->metric('screenPageViews') ?? 0),
                engagementRate:  (float) ($row?->metric('engagementRate') ?? 0),
                bounceRate:      (float) ($row?->metric('bounceRate') ?? 0),
                averageSessionDurationSeconds: (float) ($row?->metric('averageSessionDuration') ?? 0),
                fetchedAt:       CarbonImmutable::now(),
            );
        });
    }

    // ── Standard Data API: tabular reports ──────────────────────────────

    public function getTopPages(Project $project, DateRange $range, int $limit = 50, int $offset = 0): ReportResult
    {
        return $this->runNamed(
            project: $project,
            kind: 'top-pages',
            req: new ReportRequest(
                dimensions: [GaDimension::PagePath, GaDimension::PageTitle],
                metrics: [
                    GaMetric::ScreenPageViews,
                    GaMetric::Sessions,
                    GaMetric::EngagementRate,
                    GaMetric::AverageSessionDuration,
                ],
                dateRange: $range,
                orderBy: [['type' => 'metric', 'name' => 'screenPageViews', 'desc' => true]],
                limit: $limit,
                offset: $offset,
            ),
        );
    }

    public function getLandingPages(Project $project, DateRange $range, int $limit = 50, int $offset = 0): ReportResult
    {
        return $this->runNamed(
            project: $project,
            kind: 'landing-pages',
            req: new ReportRequest(
                dimensions: [GaDimension::LandingPage],
                metrics: [
                    GaMetric::Sessions,
                    GaMetric::EngagedSessions,
                    GaMetric::BounceRate,
                    GaMetric::Conversions,
                ],
                dateRange: $range,
                orderBy: [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                limit: $limit,
                offset: $offset,
            ),
        );
    }

    public function getAcquisitionBreakdown(Project $project, DateRange $range): ReportResult
    {
        return $this->runNamed(
            project: $project,
            kind: 'acquisition',
            req: new ReportRequest(
                dimensions: [GaDimension::SessionDefaultChannelGroup],
                metrics: [
                    GaMetric::Sessions,
                    GaMetric::EngagedSessions,
                    GaMetric::EngagementRate,
                    GaMetric::Conversions,
                ],
                dateRange: $range,
                orderBy: [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                limit: 25,
            ),
        );
    }

    public function getDeviceBreakdown(Project $project, DateRange $range): ReportResult
    {
        return $this->runNamed(
            project: $project,
            kind: 'device',
            req: new ReportRequest(
                dimensions: [GaDimension::DeviceCategory],
                metrics: [
                    GaMetric::Sessions,
                    GaMetric::TotalUsers,
                    GaMetric::EngagementRate,
                    GaMetric::BounceRate,
                ],
                dateRange: $range,
                orderBy: [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                limit: 10,
            ),
        );
    }

    public function getGeoBreakdown(Project $project, DateRange $range, int $limit = 25): ReportResult
    {
        return $this->runNamed(
            project: $project,
            kind: 'geo',
            req: new ReportRequest(
                dimensions: [GaDimension::Country],
                metrics: [
                    GaMetric::Sessions,
                    GaMetric::TotalUsers,
                    GaMetric::EngagedSessions,
                ],
                dateRange: $range,
                orderBy: [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                limit: $limit,
            ),
        );
    }

    /**
     * Region (state/county) breakdown. When $country is given, scopes to that
     * country; otherwise returns top regions globally (still useful but mixes
     * countries).
     */
    public function getRegionBreakdown(Project $project, DateRange $range, ?string $country = null, int $limit = 50): ReportResult
    {
        $req = new ReportRequest(
            dimensions: [GaDimension::Region, GaDimension::Country],
            metrics: [
                GaMetric::Sessions,
                GaMetric::TotalUsers,
                GaMetric::EngagedSessions,
            ],
            dateRange: $range,
            orderBy: [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
            limit: $limit,
            dimensionFilter: $country
                ? \App\Modules\Analytics\GoogleAnalytics\Queries\Filter::equals('country', $country)
                : null,
        );

        return $this->runNamedKind(
            $project,
            'region:' . ($country ?? '*'),
            $req,
        );
    }

    /**
     * City breakdown, scoped to a country (recommended — global cities are
     * a long tail with many small audiences) or globally if no country given.
     */
    public function getCityBreakdown(Project $project, DateRange $range, ?string $country = null, int $limit = 50): ReportResult
    {
        $req = new ReportRequest(
            dimensions: [GaDimension::City, GaDimension::Country],
            metrics: [
                GaMetric::Sessions,
                GaMetric::TotalUsers,
                GaMetric::EngagedSessions,
            ],
            dateRange: $range,
            orderBy: [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
            limit: $limit,
            dimensionFilter: $country
                ? \App\Modules\Analytics\GoogleAnalytics\Queries\Filter::equals('country', $country)
                : null,
        );

        return $this->runNamedKind(
            $project,
            'city:' . ($country ?? '*'),
            $req,
        );
    }

    /**
     * Age brackets (users opt-in via Google Signals). Buckets like '18-24',
     * '25-34', etc. plus a '(not set)' bucket for users without demographic data.
     */
    public function getAgeBreakdown(Project $project, DateRange $range): ReportResult
    {
        return $this->runNamed(
            project: $project,
            kind: 'age',
            req: new ReportRequest(
                dimensions: [GaDimension::UserAgeBracket],
                metrics: [GaMetric::TotalUsers, GaMetric::Sessions, GaMetric::EngagementRate],
                dateRange: $range,
                orderBy: [['type' => 'dimension', 'name' => 'userAgeBracket', 'desc' => false]],
                limit: 10,
            ),
        );
    }

    /**
     * Gender split. Same Google Signals dependency as age — '(not set)' bucket
     * dominates if Signals is off or audience is small.
     */
    public function getGenderBreakdown(Project $project, DateRange $range): ReportResult
    {
        return $this->runNamed(
            project: $project,
            kind: 'gender',
            req: new ReportRequest(
                dimensions: [GaDimension::UserGender],
                metrics: [GaMetric::TotalUsers, GaMetric::Sessions, GaMetric::EngagementRate],
                dateRange: $range,
                orderBy: [['type' => 'metric', 'name' => 'totalUsers', 'desc' => true]],
                limit: 5,
            ),
        );
    }

    /**
     * Top screen resolutions (e.g., 1920x1080, 390x844). Useful for QA — lets
     * the developer see which form factors actually need to be tested against.
     */
    public function getResolutionBreakdown(Project $project, DateRange $range, int $limit = 15): ReportResult
    {
        return $this->runNamed(
            project: $project,
            kind: 'resolution',
            req: new ReportRequest(
                dimensions: [GaDimension::ScreenResolution, GaDimension::DeviceCategory],
                metrics: [GaMetric::Sessions, GaMetric::TotalUsers, GaMetric::EngagementRate],
                dateRange: $range,
                orderBy: [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                limit: $limit,
            ),
        );
    }

    /**
     * Browser × OS breakdown. Together these are the targets a frontend
     * developer needs to support / test against.
     */
    public function getBrowserBreakdown(Project $project, DateRange $range, int $limit = 20): ReportResult
    {
        return $this->runNamed(
            project: $project,
            kind: 'browser',
            req: new ReportRequest(
                dimensions: [GaDimension::Browser, GaDimension::OperatingSystem],
                metrics: [GaMetric::Sessions, GaMetric::EngagementRate],
                dateRange: $range,
                orderBy: [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                limit: $limit,
            ),
        );
    }

    private function runNamedKind(Project $project, string $kind, ReportRequest $req): ReportResult
    {
        $property = $this->propertyOrFail($project);
        $tier     = $this->cache->tierFor($req->dateRange);
        $key      = $this->cache->keyFor($property, $kind, $req->cacheSignature());

        return $this->cache->remember($key, $tier, function () use ($req, $property) {
            return $this->runReport($req, $property);
        });
    }

    /**
     * Aggregate top events for the period; pass an event name to get a one-row
     * report for just that event.
     */
    public function getEvents(Project $project, DateRange $range, ?string $eventName = null, int $limit = 50): ReportResult
    {
        return $this->runNamed(
            project: $project,
            kind: 'events:' . ($eventName ?? '*'),
            req: new ReportRequest(
                dimensions: [GaDimension::EventName],
                metrics: [
                    GaMetric::EventCount,
                    GaMetric::EventCountPerUser,
                    GaMetric::EventValue,
                ],
                dateRange: $range,
                orderBy: [['type' => 'metric', 'name' => 'eventCount', 'desc' => true]],
                limit: $limit,
            ),
        );
        // Note: filtering to a single event by name would require a FilterExpression on
        // the request. Deferred to a later iteration when filters are introduced.
    }

    /**
     * Daily timeline for trend charts. The metrics list controls which series are produced.
     *
     * @param  array<int,GaMetric|string>  $metrics
     */
    public function getDailyTimeline(Project $project, DateRange $range, array $metrics): ReportResult
    {
        return $this->runNamed(
            project: $project,
            kind: 'daily',
            req: new ReportRequest(
                dimensions: [GaDimension::Date],
                metrics: $metrics,
                dateRange: $range,
                orderBy: [['type' => 'dimension', 'name' => 'date', 'desc' => false]],
                limit: 366, // up to a year of daily rows
            ),
        );
    }

    // ── Period comparison ───────────────────────────────────────────────

    /**
     * Compare a current range to a previous range across the same metrics.
     *
     * @param  array<int,GaMetric|string>  $metrics
     */
    public function comparePeriod(Project $project, DateRange $current, DateRange $previous, array $metrics): PeriodComparison
    {
        $property = $this->propertyOrFail($project);

        // Cache by the union of both ranges — current's tier dictates TTL since it's
        // the more volatile of the two.
        $tier = $this->cache->tierFor($current);

        $signature = sha1(json_encode([
            'metrics' => array_map(
                fn ($m) => $m instanceof GaMetric ? $m->value : (string) $m,
                $metrics,
            ),
            'current'  => $current->signature(),
            'previous' => $previous->signature(),
        ]));

        $key = $this->cache->keyFor($property, 'compare', $signature);

        return $this->cache->remember($key, $tier, function () use ($project, $current, $previous, $metrics) {
            $cur  = $this->fetchTotalsOnly($project, $current, $metrics);
            $prev = $this->fetchTotalsOnly($project, $previous, $metrics);

            $deltas = [];
            foreach ($cur as $name => $value) {
                $prevValue = $prev[$name] ?? 0;
                $deltas[$name] = $prevValue == 0
                    ? null
                    : ($value - $prevValue) / $prevValue;
            }

            return new PeriodComparison(
                current:       $cur,
                previous:      $prev,
                deltas:        $deltas,
                currentRange:  $current->signature(),
                previousRange: $previous->signature(),
                fetchedAt:     CarbonImmutable::now(),
            );
        });
    }

    // ── Realtime API ────────────────────────────────────────────────────

    public function getRealtimeUsers(Project $project): RealtimeSnapshot
    {
        // Realtime queries skip the cache entirely — the whole point of the
        // realtime tab is to show what's happening *right now*. The UI's
        // wire:poll cadence (30s) is the rate-limiting mechanism instead.
        $this->propertyOrFail($project);

        $total     = $this->runRealtime($project, new RealtimeRequest([], [GaMetric::ActiveUsers], 1));
        $byCountry = $this->runRealtime($project, new RealtimeRequest([GaDimension::Country], [GaMetric::ActiveUsers], 10));
        $byDevice  = $this->runRealtime($project, new RealtimeRequest([GaDimension::DeviceCategory], [GaMetric::ActiveUsers], 5));
        $byPage    = $this->runRealtime($project, new RealtimeRequest(['unifiedScreenName'], [GaMetric::ActiveUsers], 10));

        $totalUsers = $total->isEmpty()
            ? 0
            : (int) $total->rows()->first()->metric('activeUsers');

        return new RealtimeSnapshot(
            activeUsers: $totalUsers,
            byCountry:   $this->breakdownToList($byCountry, 'country'),
            byDevice:    $this->breakdownToList($byDevice, 'deviceCategory'),
            byPage:      $this->breakdownToList($byPage, 'unifiedScreenName'),
            fetchedAt:   CarbonImmutable::now(),
        );
    }

    public function getRealtimeEvents(Project $project, int $limit = 20): ReportResult
    {
        $this->propertyOrFail($project);
        $req = new RealtimeRequest(
            dimensions: [GaDimension::EventName],
            metrics:    [GaMetric::EventCount],
            limit:      $limit,
        );

        // No cache — see getRealtimeUsers for rationale.
        return $this->runRealtime($project, $req);
    }

    // ── Batch runner (cold-cache speedup) ─────────────────────────────

    /**
     * Run multiple ReportRequests with cache-aware batching.
     *
     * - Cache HITs are returned directly (zero API cost).
     * - Cache MISSes are collected and sent to GA's batchRunReports
     *   endpoint in chunks of 5 (GA's per-batch limit). One round-trip
     *   per chunk instead of one per request.
     * - Each result is stored back in the cache with the appropriate
     *   tier-based TTL.
     *
     * The map key doubles as the cache "kind" — e.g. 'overview', 'top-pages'
     * — so each named slot has its own predictable cache namespace.
     *
     * @param  array<string, ReportRequest>  $requests  name => request
     * @return array<string, ReportResult>
     */
    public function runBatch(Project $project, array $requests): array
    {
        $property = $this->propertyOrFail($project);

        $sentinel = new \stdClass();
        $cached   = [];
        $misses   = [];

        foreach ($requests as $name => $req) {
            $tier = $this->cache->tierFor($req->dateRange);
            $key  = $this->cache->keyFor($property, $name, $req->cacheSignature());

            $hit = \Illuminate\Support\Facades\Cache::get($key, $sentinel);
            if ($hit !== $sentinel) {
                $cached[$name] = $hit;
            } else {
                $misses[$name] = ['req' => $req, 'key' => $key, 'tier' => $tier];
            }
        }

        if ($misses === []) {
            return $cached;
        }

        // GA limits each batchRunReports call to 5 sub-reports.
        $chunks = array_chunk($misses, 5, preserve_keys: true);
        foreach ($chunks as $chunk) {
            $names      = array_keys($chunk);
            $gaRequests = array_values(array_map(
                fn ($m) => $m['req']->toGaRequest($property),
                $chunk,
            ));

            $responses = $this->client->batchRunReports($property, $gaRequests);

            foreach ($names as $i => $name) {
                $miss = $chunk[$name];
                $req  = $miss['req'];
                $result = ReportResult::fromGa(
                    $responses[$i],
                    $req->dimensions,
                    $req->metrics,
                    $this->metricFormatHints($req->metrics),
                );
                \Illuminate\Support\Facades\Cache::put($miss['key'], $result, $miss['tier']->ttlSeconds());
                $cached[$name] = $result;
            }
        }

        return $cached;
    }

    // ── Static DTO mappers (used after runBatch to type results) ──────

    /**
     * Hydrate a TrafficOverview DTO from a ReportResult that was queried
     * with the standard 8 overview metrics. Missing metrics default to 0.
     */
    public static function trafficOverviewFromReport(ReportResult $result): TrafficOverview
    {
        $row = $result->rows()->first();

        return new TrafficOverview(
            sessions:                       (int) ($row?->metric('sessions') ?? 0),
            totalUsers:                     (int) ($row?->metric('totalUsers') ?? 0),
            newUsers:                       (int) ($row?->metric('newUsers') ?? 0),
            engagedSessions:                (int) ($row?->metric('engagedSessions') ?? 0),
            screenPageViews:                (int) ($row?->metric('screenPageViews') ?? 0),
            engagementRate:                 (float) ($row?->metric('engagementRate') ?? 0),
            bounceRate:                     (float) ($row?->metric('bounceRate') ?? 0),
            averageSessionDurationSeconds:  (float) ($row?->metric('averageSessionDuration') ?? 0),
            fetchedAt:                      $result->fetchedAt(),
        );
    }

    /**
     * Compute a PeriodComparison from two totals-only ReportResults.
     * Both reports must have been queried with the same metric set.
     *
     * @param  list<string>  $metricNames  metric names to compare
     */
    public static function periodComparisonFrom(
        ReportResult $current,
        ReportResult $previous,
        DateRange $currentRange,
        DateRange $previousRange,
        array $metricNames,
    ): PeriodComparison {
        $curRow  = $current->rows()->first();
        $prevRow = $previous->rows()->first();

        $cur = $prev = $deltas = [];
        foreach ($metricNames as $m) {
            $cur[$m]  = (float) ($curRow?->metric($m) ?? 0);
            $prev[$m] = (float) ($prevRow?->metric($m) ?? 0);
            $deltas[$m] = $prev[$m] == 0
                ? null
                : ($cur[$m] - $prev[$m]) / $prev[$m];
        }

        return new PeriodComparison(
            current:       $cur,
            previous:      $prev,
            deltas:        $deltas,
            currentRange:  $currentRange->signature(),
            previousRange: $previousRange->signature(),
            fetchedAt:     CarbonImmutable::now(),
        );
    }

    // ── Funnels (v1alpha — preview-quality but stable) ─────────────────

    public function getFunnel(Project $project, FunnelDefinition $definition): FunnelResult
    {
        $property = $this->propertyOrFail($project);
        $tier     = $this->cache->tierFor($definition->dateRange);
        $key      = $this->cache->keyFor($property, 'funnel', $definition->cacheSignature());

        return $this->cache->remember($key, $tier, function () use ($definition, $property) {
            $response = $this->client->runFunnelReport($definition->toGaRequest($property));
            $stepNames = array_map(fn ($s) => $s->name, $definition->steps);
            return FunnelResult::fromGa($response, $stepNames);
        });
    }

    // ── Escape hatch: arbitrary report ─────────────────────────────────

    public function runCustomReport(Project $project, ReportRequest $req): ReportResult
    {
        $property = $this->propertyOrFail($project);
        $tier     = $this->cache->tierFor($req->dateRange);
        $key      = $this->cache->keyFor($property, 'custom', $req->cacheSignature());

        return $this->cache->remember($key, $tier, function () use ($req, $property) {
            return $this->runReport($req, $property);
        });
    }

    // ── Internals ──────────────────────────────────────────────────────

    private function propertyOrFail(Project $project): string
    {
        if (!$project->hasGoogleAnalytics()) {
            throw PropertyNotConfiguredException::forProject($project->id);
        }
        return $project->gaPropertyResource();
    }

    private function runNamed(Project $project, string $kind, ReportRequest $req): ReportResult
    {
        $property = $this->propertyOrFail($project);
        $tier     = $this->cache->tierFor($req->dateRange);
        $key      = $this->cache->keyFor($property, $kind, $req->cacheSignature());

        return $this->cache->remember($key, $tier, function () use ($req, $property) {
            return $this->runReport($req, $property);
        });
    }

    private function runReport(ReportRequest $req, string $property): ReportResult
    {
        $response = $this->client->runReport($req->toGaRequest($property));
        return ReportResult::fromGa(
            $response,
            $req->dimensions,
            $req->metrics,
            $this->metricFormatHints($req->metrics),
        );
    }

    private function runRealtime(Project $project, RealtimeRequest $req): ReportResult
    {
        $property = $this->propertyOrFail($project);
        $response = $this->client->runRealtimeReport($req->toGaRequest($property));
        return ReportResult::fromGaRealtime(
            $response,
            $req->dimensions,
            $req->metrics,
            $this->metricFormatHints($req->metrics),
        );
    }

    /**
     * @param  array<int,GaMetric|string>  $metrics
     * @return array<string,float>
     */
    private function fetchTotalsOnly(Project $project, DateRange $range, array $metrics): array
    {
        $req = new ReportRequest(
            dimensions: [],
            metrics:    $metrics,
            dateRange:  $range,
            limit:      1,
        );

        $result = $this->runReport($req, $this->propertyOrFail($project));

        $row = $result->rows()->first();
        $out = [];
        foreach ($req->metrics as $name) {
            $out[$name] = $row ? (float) $row->metric($name) : 0.0;
        }
        return $out;
    }

    /**
     * @param  list<string>  $metricNames
     * @return array<string,string>
     */
    private function metricFormatHints(array $metricNames): array
    {
        $out = [];
        foreach ($metricNames as $name) {
            $enum = GaMetric::tryFrom($name);
            $out[$name] = $enum ? $enum->format() : 'integer';
        }
        return $out;
    }

    /**
     * @return list<array{label:string,activeUsers:int}>
     */
    private function breakdownToList(ReportResult $result, string $dimensionName): array
    {
        return $result->rows()
            ->map(fn ($row) => [
                'label'       => (string) ($row->dimension($dimensionName) ?? ''),
                'activeUsers' => (int) $row->metric('activeUsers'),
            ])
            ->all();
    }
}
