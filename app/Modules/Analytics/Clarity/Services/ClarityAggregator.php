<?php

namespace App\Modules\Analytics\Clarity\Services;

use App\Modules\Analytics\Clarity\Models\ClarityInsight;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregates 1-day Clarity insights across a date range into a snapshot-shaped
 * collection keyed by metric_name. The returned objects carry the same `->data`
 * array shape as a single ClarityInsight, so the existing clarity-metrics blade
 * can render either a single-day fetch or a multi-day aggregate.
 */
class ClarityAggregator
{
    private const SIGNAL_KEYS = [
        'DeadClickCount',
        'RageClickCount',
        'QuickbackClick',
        'ExcessiveScroll',
        'ScriptErrorCount',
        'ErrorClickCount',
    ];

    private const NAMED_BREAKDOWNS = ['Browser', 'Device', 'OS', 'Country', 'ReferrerUrl', 'PageTitle'];

    /**
     * Returns a snapshot-shaped collection keyed by metric_name, or null when
     * no 1-day fetches exist in the range.
     */
    public function aggregate(int $projectId, Carbon $from, Carbon $to): ?Collection
    {
        $rows = $this->loadDeduped($projectId, $from, $to);

        if ($rows->isEmpty()) {
            return null;
        }

        // Per-day session count — used as the weighting factor for averaged
        // metrics (scroll depth, UX signal %), so a 10-session day doesn't pull
        // the mean down as hard as a 1000-session day.
        $sessionsPerDay = $rows
            ->where('metric_name', 'Traffic')
            ->mapWithKeys(fn ($r) => [
                $r->date_from->format('Y-m-d') => (int) ($r->data[0]['totalSessionCount'] ?? 0),
            ]);

        $byMetric = $rows->groupBy('metric_name');

        $aggregated = collect();

        $this->aggregateTraffic($aggregated, $byMetric->get('Traffic'));
        $this->aggregateScrollDepth($aggregated, $byMetric->get('ScrollDepth'), $sessionsPerDay);
        $this->aggregateEngagement($aggregated, $byMetric->get('EngagementTime'));

        foreach (self::SIGNAL_KEYS as $key) {
            $this->aggregateSignal($aggregated, $key, $byMetric->get($key));
        }

        foreach (self::NAMED_BREAKDOWNS as $key) {
            $this->aggregateNamedBreakdown($aggregated, $key, $byMetric->get($key));
        }

        $this->aggregatePopularPages($aggregated, $byMetric->get('PopularPages'));

        return $aggregated;
    }

    /**
     * How many distinct days inside the range actually have a fetch — useful
     * for the UI to flag gaps ("5 of 7 days have data").
     */
    public function daysWithData(int $projectId, Carbon $from, Carbon $to): int
    {
        return ClarityInsight::where('project_id', $projectId)
            ->where('date_from', '>=', $from->copy()->startOfDay())
            ->where('date_to', '<=', $to->copy()->endOfDay())
            ->whereColumn('date_from', 'date_to')
            ->whereNull('dimension1')
            ->distinct()
            ->count('date_from');
    }

    private function loadDeduped(int $projectId, Carbon $from, Carbon $to): Collection
    {
        $rows = ClarityInsight::where('project_id', $projectId)
            ->where('date_from', '>=', $from->copy()->startOfDay())
            ->where('date_to', '<=', $to->copy()->endOfDay())
            ->whereColumn('date_from', 'date_to')
            ->whereNull('dimension1')
            ->orderBy('date_from')
            ->get();

        // If a day was fetched more than once, only the latest fetch counts —
        // otherwise SUMs would double-count and weighted means would skew.
        return $rows
            ->groupBy(fn ($r) => $r->date_from->format('Y-m-d'))
            ->flatMap(function ($dayRows) {
                $latestTs = $dayRows->max(fn ($r) => $r->fetched_for->timestamp);
                return $dayRows->filter(fn ($r) => $r->fetched_for->timestamp === $latestTs);
            })
            ->values();
    }

    private function aggregateTraffic(Collection $out, ?Collection $rows): void
    {
        if (!$rows || $rows->isEmpty()) {
            return;
        }

        $totalSessions = 0;
        $totalBots = 0;
        $totalUsers = 0;
        $weightedPagesPer = 0.0;

        foreach ($rows as $r) {
            $d = $r->data[0] ?? [];
            $s = (int) ($d['totalSessionCount'] ?? 0);
            $totalSessions += $s;
            $totalBots += (int) ($d['totalBotSessionCount'] ?? 0);
            $totalUsers += (int) ($d['distinctUserCount'] ?? 0);
            $weightedPagesPer += ((float) ($d['pagesPerSessionPercentage'] ?? 0)) * $s;
        }

        $out['Traffic'] = $this->makeInsight('Traffic', [[
            'totalSessionCount' => $totalSessions,
            'totalBotSessionCount' => $totalBots,
            'distinctUserCount' => $totalUsers,
            'pagesPerSessionPercentage' => $totalSessions > 0
                ? round($weightedPagesPer / $totalSessions, 4)
                : 0,
        ]]);
    }

    private function aggregateScrollDepth(Collection $out, ?Collection $rows, Collection $sessionsPerDay): void
    {
        if (!$rows || $rows->isEmpty()) {
            return;
        }

        $sumWeighted = 0.0;
        $sumWeights = 0;

        foreach ($rows as $r) {
            $day = $r->date_from->format('Y-m-d');
            $w = (int) ($sessionsPerDay[$day] ?? 0);
            $sumWeighted += ((float) ($r->data[0]['averageScrollDepth'] ?? 0)) * $w;
            $sumWeights += $w;
        }

        $out['ScrollDepth'] = $this->makeInsight('ScrollDepth', [[
            'averageScrollDepth' => $sumWeights > 0 ? round($sumWeighted / $sumWeights, 2) : 0,
        ]]);
    }

    private function aggregateEngagement(Collection $out, ?Collection $rows): void
    {
        if (!$rows || $rows->isEmpty()) {
            return;
        }

        $total = 0;
        $active = 0;
        foreach ($rows as $r) {
            $d = $r->data[0] ?? [];
            $total += (int) ($d['totalTime'] ?? 0);
            $active += (int) ($d['activeTime'] ?? 0);
        }

        $out['EngagementTime'] = $this->makeInsight('EngagementTime', [[
            'totalTime' => $total,
            'activeTime' => $active,
        ]]);
    }

    private function aggregateSignal(Collection $out, string $key, ?Collection $rows): void
    {
        if (!$rows || $rows->isEmpty()) {
            return;
        }

        $subTotal = 0;
        $sessionsCount = 0;
        $pagesViews = 0;
        $weightedPct = 0.0;

        foreach ($rows as $r) {
            $d = $r->data[0] ?? [];
            $s = (int) ($d['sessionsCount'] ?? 0);
            $subTotal += (int) ($d['subTotal'] ?? 0);
            $sessionsCount += $s;
            $pagesViews += (int) ($d['pagesViews'] ?? 0);
            $weightedPct += ((float) ($d['sessionsWithMetricPercentage'] ?? 0)) * $s;
        }

        $pct = $sessionsCount > 0 ? round($weightedPct / $sessionsCount, 2) : 0;

        $out[$key] = $this->makeInsight($key, [[
            'sessionsCount' => $sessionsCount,
            'sessionsWithMetricPercentage' => $pct,
            'sessionsWithoutMetricPercentage' => $sessionsCount > 0 ? round(100 - $pct, 2) : 0,
            'pagesViews' => $pagesViews,
            'subTotal' => $subTotal,
        ]]);
    }

    private function aggregateNamedBreakdown(Collection $out, string $key, ?Collection $rows): void
    {
        if (!$rows || $rows->isEmpty()) {
            return;
        }

        $sums = [];
        foreach ($rows as $r) {
            foreach (($r->data ?? []) as $row) {
                $name = $row['name'] ?? '—';
                $sums[$name] = ($sums[$name] ?? 0) + (int) ($row['sessionsCount'] ?? 0);
            }
        }
        arsort($sums);

        $data = [];
        foreach ($sums as $name => $count) {
            $data[] = ['name' => $name, 'sessionsCount' => $count];
        }

        $out[$key] = $this->makeInsight($key, $data);
    }

    private function aggregatePopularPages(Collection $out, ?Collection $rows): void
    {
        if (!$rows || $rows->isEmpty()) {
            return;
        }

        $sums = [];
        foreach ($rows as $r) {
            foreach (($r->data ?? []) as $row) {
                $url = $row['url'] ?? '—';
                $sums[$url] = ($sums[$url] ?? 0) + (int) ($row['visitsCount'] ?? 0);
            }
        }
        arsort($sums);

        $data = [];
        foreach ($sums as $url => $count) {
            $data[] = ['url' => $url, 'visitsCount' => $count];
        }

        $out['PopularPages'] = $this->makeInsight('PopularPages', $data);
    }

    private function makeInsight(string $metricName, array $data): ClarityInsight
    {
        $insight = new ClarityInsight();
        $insight->metric_name = $metricName;
        $insight->data = $data;
        return $insight;
    }
}
