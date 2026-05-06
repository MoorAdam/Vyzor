<?php

namespace App\Modules\Analytics\GoogleAnalytics\Tools;

use App\Modules\Analytics\GoogleAnalytics\Enums\GaDimension;
use App\Modules\Analytics\GoogleAnalytics\Enums\GaMetric;
use App\Modules\Analytics\GoogleAnalytics\Exceptions\GoogleAnalyticsException;
use App\Modules\Analytics\GoogleAnalytics\Queries\DateRange;
use App\Modules\Analytics\GoogleAnalytics\Queries\Filter;
use App\Modules\Analytics\GoogleAnalytics\Queries\ReportRequest;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsQueryService;
use App\Modules\Projects\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Function-calling tool that exposes the GA query service to AI agents.
 *
 * One tool with an action discriminator (the 'query' parameter) instead of
 * one tool per query type — the AI gets a clear menu in the description and
 * a single, simple schema. Cheaper for prompt cache and easier to maintain.
 *
 * The tool is locked to a specific project at construction; the AI cannot
 * read another project's GA data through it.
 */
class GoogleAnalyticsTool implements Tool
{
    public function __construct(
        public readonly Project $project,
        public readonly GoogleAnalyticsQueryService $query,
    ) {}

    public function description(): Stringable|string
    {
        return <<<DESC
Query Google Analytics 4 data for the current project. Use this when you need
GA insights beyond what's already in the prompt — for example a different
date range, a longer top-N list, channel detail, period comparison, or live
realtime activity.

Allowed 'query' values:
  - traffic_overview   sessions/users/engagement totals for a date range
  - top_pages          most-viewed pages with engagement metrics
  - landing_pages      sessions by landing page with bounce rate
  - acquisition        sessions by channel group (Organic, Paid, etc.)
  - device_breakdown   sessions/engagement by device category
  - geo_breakdown      top countries by sessions
  - events             top events by count
  - daily_timeline     daily metric values for charting trends
  - compare_period     compare two date ranges across given metrics
  - realtime_users     active users in the last 30 minutes (with breakdowns)
  - realtime_events    top events in the last 30 minutes

Date parameters accept YYYY-MM-DD or relative shortcuts:
  'today', 'yesterday', 'NdaysAgo' (e.g. '7daysAgo', '30daysAgo').

OPTIONAL filtering — use the 'filter' parameter to narrow results. Pass a list
of {field, op, value} objects (ANDed together):
  [{"field":"deviceCategory","op":"equals","value":"mobile"}]
  [{"field":"sessionDefaultChannelGroup","op":"in","values":["Organic Search","Direct"]}]
Operators: equals, contains, begins_with, ends_with, regexp, in, gt, gte, lt,
lte, between, empty. For OR logic, wrap children: {"op":"or","filters":[...]}.

For 'compare_period' / 'daily_timeline' you can also pass a 'filter' to scope
both halves of the comparison. Filters apply to the dimensions being grouped
on; numeric metric thresholds use 'metric_filter' (same shape).

The result is returned as a JSON string. Numeric metric values are GA's raw
strings — parse to numbers if needed.
DESC;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Type of GA query — see description for allowed values.')
                ->required(),
            'from' => $schema->string()
                ->description('Start date YYYY-MM-DD or relative shortcut. Default: 7daysAgo.'),
            'to' => $schema->string()
                ->description('End date YYYY-MM-DD or relative shortcut. Default: today.'),
            'previous_from' => $schema->string()
                ->description('Only for compare_period: previous range start.'),
            'previous_to' => $schema->string()
                ->description('Only for compare_period: previous range end.'),
            'limit' => $schema->integer()
                ->description('Max rows to return for tabular queries. Default: 50.'),
            'metrics' => $schema->array()
                ->description('Only for daily_timeline and compare_period: GA metric names, e.g. ["sessions","engagedSessions"].'),
            'filter' => $schema->array()
                ->description('Optional dimension filter — list of {field, op, value} entries ANDed together. See description for shape.'),
            'metric_filter' => $schema->array()
                ->description('Optional metric filter — same shape as filter, but field names are metric names and ops are gt/gte/lt/lte/between.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $type = (string) $request['query'];

        try {
            $result = $this->dispatch($type, $request);
        } catch (GoogleAnalyticsException $e) {
            return json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            return json_encode(['error' => 'Tool error: ' . $e->getMessage()], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function dispatch(string $type, Request $req): array
    {
        $from   = (string) ($req['from'] ?? '7daysAgo');
        $to     = (string) ($req['to'] ?? 'today');
        $limit  = (int) ($req['limit'] ?? 50);
        $range  = $this->resolveRange($from, $to);
        $filter = $this->extractFilter($req, 'filter');
        $mfilt  = $this->extractFilter($req, 'metric_filter');

        // Realtime endpoints don't take filters in our wrapper; the rest do.
        if ($type === 'realtime_users') {
            return $this->query->getRealtimeUsers($this->project)->toArray();
        }
        if ($type === 'realtime_events') {
            return $this->query->getRealtimeEvents($this->project, $limit)->toArray();
        }

        // For typed/named methods: if a filter is supplied, fall through to a
        // custom report so the filter can be applied. Otherwise use the named
        // method (cheaper and gets dedicated cache slots).
        if ($filter === null && $mfilt === null) {
            $named = match ($type) {
                'traffic_overview' => $this->query->getTrafficOverview($this->project, $range)->toArray(),
                'top_pages'        => $this->query->getTopPages($this->project, $range, $limit)->toArray(),
                'landing_pages'    => $this->query->getLandingPages($this->project, $range, $limit)->toArray(),
                'acquisition'      => $this->query->getAcquisitionBreakdown($this->project, $range)->toArray(),
                'device_breakdown' => $this->query->getDeviceBreakdown($this->project, $range)->toArray(),
                'geo_breakdown'    => $this->query->getGeoBreakdown($this->project, $range, $limit)->toArray(),
                'events'           => $this->query->getEvents($this->project, $range, null, $limit)->toArray(),

                'daily_timeline' => $this->query->getDailyTimeline(
                    $this->project,
                    $range,
                    $this->extractMetrics($req, default: [GaMetric::Sessions, GaMetric::TotalUsers]),
                )->toArray(),

                'compare_period' => $this->query->comparePeriod(
                    $this->project,
                    $range,
                    $this->resolvePreviousRange($req, $range),
                    $this->extractMetrics($req, default: [GaMetric::Sessions, GaMetric::TotalUsers, GaMetric::EngagementRate]),
                )->toArray(),

                default => null,
            };
            if ($named !== null) {
                return $named;
            }
            return ['error' => "Unknown query type: {$type}. See tool description for allowed values."];
        }

        // Filtered path — re-derive the equivalent ReportRequest and run as custom.
        $req = $this->filteredReportRequest($type, $range, $limit, $filter, $mfilt, $req);
        if ($req === null) {
            return ['error' => "Filtering is not supported for query type '{$type}'."];
        }
        return $this->query->runCustomReport($this->project, $req)->toArray();
    }

    private function filteredReportRequest(
        string $type,
        DateRange $range,
        int $limit,
        ?Filter $filter,
        ?Filter $mfilt,
        Request $rawReq,
    ): ?ReportRequest {
        return match ($type) {
            'top_pages' => new ReportRequest(
                dimensions: [GaDimension::PagePath, GaDimension::PageTitle],
                metrics:    [GaMetric::ScreenPageViews, GaMetric::Sessions, GaMetric::EngagementRate, GaMetric::AverageSessionDuration],
                dateRange:  $range,
                orderBy:    [['type' => 'metric', 'name' => 'screenPageViews', 'desc' => true]],
                limit:      $limit,
                dimensionFilter: $filter,
                metricFilter:    $mfilt,
            ),
            'landing_pages' => new ReportRequest(
                dimensions: [GaDimension::LandingPage],
                metrics:    [GaMetric::Sessions, GaMetric::EngagedSessions, GaMetric::BounceRate, GaMetric::Conversions],
                dateRange:  $range,
                orderBy:    [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                limit:      $limit,
                dimensionFilter: $filter,
                metricFilter:    $mfilt,
            ),
            'acquisition' => new ReportRequest(
                dimensions: [GaDimension::SessionDefaultChannelGroup],
                metrics:    [GaMetric::Sessions, GaMetric::EngagedSessions, GaMetric::Conversions],
                dateRange:  $range,
                orderBy:    [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                limit:      25,
                dimensionFilter: $filter,
                metricFilter:    $mfilt,
            ),
            'device_breakdown' => new ReportRequest(
                dimensions: [GaDimension::DeviceCategory],
                metrics:    [GaMetric::Sessions, GaMetric::TotalUsers, GaMetric::EngagementRate, GaMetric::BounceRate],
                dateRange:  $range,
                orderBy:    [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                limit:      10,
                dimensionFilter: $filter,
                metricFilter:    $mfilt,
            ),
            'geo_breakdown' => new ReportRequest(
                dimensions: [GaDimension::Country],
                metrics:    [GaMetric::Sessions, GaMetric::TotalUsers, GaMetric::EngagedSessions],
                dateRange:  $range,
                orderBy:    [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                limit:      $limit,
                dimensionFilter: $filter,
                metricFilter:    $mfilt,
            ),
            'events' => new ReportRequest(
                dimensions: [GaDimension::EventName],
                metrics:    [GaMetric::EventCount, GaMetric::EventCountPerUser, GaMetric::EventValue],
                dateRange:  $range,
                orderBy:    [['type' => 'metric', 'name' => 'eventCount', 'desc' => true]],
                limit:      $limit,
                dimensionFilter: $filter,
                metricFilter:    $mfilt,
            ),
            'traffic_overview' => new ReportRequest(
                dimensions: [],
                metrics: [
                    GaMetric::Sessions, GaMetric::TotalUsers, GaMetric::NewUsers,
                    GaMetric::EngagedSessions, GaMetric::ScreenPageViews,
                    GaMetric::EngagementRate, GaMetric::BounceRate,
                    GaMetric::AverageSessionDuration,
                ],
                dateRange: $range,
                limit: 1,
                dimensionFilter: $filter,
                metricFilter:    $mfilt,
            ),
            // daily_timeline / compare_period not yet supported with filters via the tool.
            default => null,
        };
    }

    private function extractFilter(Request $req, string $key): ?Filter
    {
        $raw = $req[$key] ?? null;
        if (!is_array($raw) || $raw === []) return null;
        return Filter::fromArray($raw);
    }

    private function resolveRange(string $from, string $to): DateRange
    {
        return new DateRange($this->parseDate($from), $this->parseDate($to));
    }

    private function resolvePreviousRange(Request $req, DateRange $current): DateRange
    {
        $pf = $req['previous_from'] ?? null;
        $pt = $req['previous_to'] ?? null;

        if ($pf && $pt) {
            return new DateRange($this->parseDate((string) $pf), $this->parseDate((string) $pt));
        }
        return $current->previousPeriod();
    }

    /**
     * @param  array<int,GaMetric|string>  $default
     * @return array<int,GaMetric|string>
     */
    private function extractMetrics(Request $req, array $default): array
    {
        $metrics = $req['metrics'] ?? null;
        return is_array($metrics) && $metrics !== [] ? $metrics : $default;
    }

    private function parseDate(string $input): CarbonImmutable
    {
        $input = trim($input);
        $lower = strtolower($input);

        if ($lower === 'today') {
            return CarbonImmutable::today();
        }
        if ($lower === 'yesterday') {
            return CarbonImmutable::yesterday();
        }
        if (preg_match('/^(\d+)daysago$/i', $input, $m)) {
            return CarbonImmutable::today()->subDays((int) $m[1]);
        }

        return CarbonImmutable::parse($input)->startOfDay();
    }
}
