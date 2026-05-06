<?php

use App\Modules\Analytics\GoogleAnalytics\Enums\GaDimension;
use App\Modules\Analytics\GoogleAnalytics\Enums\GaMetric;
use App\Modules\Analytics\GoogleAnalytics\Exceptions\GoogleAnalyticsException;
use App\Modules\Analytics\GoogleAnalytics\Queries\DateRange;
use App\Modules\Analytics\GoogleAnalytics\Queries\ReportRequest;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsCache;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsQueryService;
use App\Modules\Projects\Models\Project;
use App\Modules\Users\Enums\PermissionEnum;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public string $rangePreset = 'last_7';
    public string $dateFrom = '';
    public string $dateTo = '';

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('permission', [PermissionEnum::VIEW_GOOGLE_ANALYTICS, Project::current()]),
            403,
        );

        // Seed the custom-mode inputs with sensible defaults — used only when
        // the user switches to "Custom". Preset modes derive dates fresh in with().
        $today = now();
        $this->dateFrom = $today->copy()->subDays(6)->format('Y-m-d');
        $this->dateTo   = $today->format('Y-m-d');
    }

    #[On('current-project-changed')]
    public function onProjectChanged(): void
    {
        // No-op — with() re-computes the range from the preset on every render.
    }

    /**
     * Manual cache-bust — drops every cached GA entry for this project's
     * property. Triggered by the "Refresh" button. The component re-renders
     * automatically after the action returns; the next render's queries will
     * miss the cache and pull fresh data.
     */
    public function forceRefresh(): void
    {
        $project = Project::current();
        if ($project && $project->hasGoogleAnalytics()) {
            app(GoogleAnalyticsCache::class)->forgetForProperty($project->gaPropertyResource());
        }
    }

    /**
     * Resolve the active range every render. Don't cache to component state —
     * mutating $dateFrom/$dateTo from an updated*() hook would race with with()
     * under Livewire's reactivity model and serve stale data on the first paint
     * after a preset change. Computing here keeps it deterministic.
     */
    private function resolveRange(): DateRange
    {
        return match ($this->rangePreset) {
            'today'    => DateRange::today(),
            'last_7'   => DateRange::lastNDays(7),
            'last_28'  => DateRange::lastNDays(28),
            'last_30'  => DateRange::lastNDays(30),
            'custom'   => DateRange::between($this->dateFrom, $this->dateTo),
            default    => DateRange::lastNDays(7),
        };
    }

    public function with(): array
    {
        $project = Project::current();

        $base = [
            'project'    => $project,
            'configured' => $project?->hasGoogleAnalytics() ?? false,
            'error'      => null,
        ];

        if (!$project || !$project->hasGoogleAnalytics()) {
            return $base;
        }

        try {
            $range    = $this->resolveRange();
            $previous = $range->previousPeriod();
            $svc      = app(GoogleAnalyticsQueryService::class);

            // The 8 overview metrics double as the "current totals" half of
            // the period comparison — sub-set of these is what we delta against.
            $overviewMetrics = [
                GaMetric::Sessions, GaMetric::TotalUsers, GaMetric::NewUsers,
                GaMetric::EngagedSessions, GaMetric::ScreenPageViews,
                GaMetric::EngagementRate, GaMetric::BounceRate,
                GaMetric::AverageSessionDuration,
            ];
            $compareMetricNames = [
                'sessions', 'totalUsers', 'engagedSessions',
                'screenPageViews', 'engagementRate', 'bounceRate',
                'averageSessionDuration',
            ];

            // 5 reports → fits in one GA batchRunReports call. Cold-start
            // drops from ~6s (sequential) to ~1.5s (one round-trip).
            $results = $svc->runBatch($project, [
                'traffic-overview'      => new ReportRequest(
                    dimensions: [],
                    metrics:    $overviewMetrics,
                    dateRange:  $range,
                    limit:      1,
                ),
                'traffic-overview-prev' => new ReportRequest(
                    dimensions: [],
                    metrics:    $overviewMetrics,
                    dateRange:  $previous,
                    limit:      1,
                ),
                'top-pages' => new ReportRequest(
                    dimensions: [GaDimension::PagePath, GaDimension::PageTitle],
                    metrics:    [GaMetric::ScreenPageViews, GaMetric::Sessions, GaMetric::EngagementRate, GaMetric::AverageSessionDuration],
                    dateRange:  $range,
                    orderBy:    [['type' => 'metric', 'name' => 'screenPageViews', 'desc' => true]],
                    limit:      10,
                ),
                'acquisition' => new ReportRequest(
                    dimensions: [GaDimension::SessionDefaultChannelGroup],
                    metrics:    [GaMetric::Sessions, GaMetric::EngagedSessions, GaMetric::EngagementRate, GaMetric::Conversions],
                    dateRange:  $range,
                    orderBy:    [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                    limit:      25,
                ),
                'devices' => new ReportRequest(
                    dimensions: [GaDimension::DeviceCategory],
                    metrics:    [GaMetric::Sessions, GaMetric::TotalUsers, GaMetric::EngagementRate, GaMetric::BounceRate],
                    dateRange:  $range,
                    orderBy:    [['type' => 'metric', 'name' => 'sessions', 'desc' => true]],
                    limit:      10,
                ),
            ]);

            return [
                ...$base,
                ...$this->viewHelpers(),
                'range'       => $range,
                'previous'    => $previous,
                'rangeFrom'   => $range->startString(),
                'rangeTo'     => $range->endString(),
                'overview'    => GoogleAnalyticsQueryService::trafficOverviewFromReport($results['traffic-overview']),
                'compare'     => GoogleAnalyticsQueryService::periodComparisonFrom(
                    $results['traffic-overview'],
                    $results['traffic-overview-prev'],
                    $range, $previous,
                    $compareMetricNames,
                ),
                'topPages'    => $results['top-pages'],
                'acquisition' => $results['acquisition'],
                'devices'     => $results['devices'],
            ];
        } catch (GoogleAnalyticsException $e) {
            return [...$base, ...$this->viewHelpers(), 'error' => $e->getMessage()];
        }
    }

    /**
     * View-only formatting helpers exposed as closures to the Blade view.
     * Defined here (rather than as top-level closures) so the Livewire
     * single-file compiler doesn't trip on code after the class.
     */
    private function viewHelpers(): array
    {
        return [
            'formatNumber'   => fn ($n) => number_format((float) $n, 0, '.', ' '),
            'formatPercent'  => fn (float $v) => number_format($v * 100, 1) . '%',
            'formatDuration' => function ($seconds): string {
                $s = (float) $seconds;
                $m = (int) floor($s / 60);
                $r = (int) round($s - $m * 60);
                return sprintf('%d:%02d', $m, $r);
            },
            'formatDelta' => function (?float $delta): array {
                if ($delta === null) return ['text' => '—', 'class' => 'text-neutral-400'];
                $sign  = $delta >= 0 ? '+' : '';
                $class = $delta >= 0
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : 'text-red-600 dark:text-red-400';
                return [
                    'text'  => $sign . number_format($delta * 100, 1) . '%',
                    'class' => $class,
                ];
            },
        ];
    }
};
?>

<div class="p-6 space-y-6">
    <div>
        <x-ui.heading level="h1" size="xl">{{ __('Google Analytics — Overview') }}</x-ui.heading>
        <x-ui.description class="mt-1">
            {{ __('Traffic overview for the current project, sourced live from GA4.') }}
        </x-ui.description>
    </div>

    @php
        // Fallback helpers when the early-return paths skipped viewHelpers().
        $formatNumber   ??= fn ($n) => number_format((float) $n, 0, '.', ' ');
        $formatPercent  ??= fn (float $v) => number_format($v * 100, 1) . '%';
        $formatDuration ??= fn ($s) => sprintf('%d:%02d', (int) floor((float) $s / 60), (int) round((float) $s - floor((float) $s / 60) * 60));
        $formatDelta    ??= fn (?float $d) => $d === null ? ['text' => '—', 'class' => 'text-neutral-400'] : ['text' => ($d >= 0 ? '+' : '') . number_format($d * 100, 1) . '%', 'class' => $d >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'];
    @endphp

    @if (!$project)
        <x-ui.card>
            <x-ui.empty>
                <x-ui.empty.contents>
                    <x-ui.icon name="folder-open" class="size-10 text-neutral-300 dark:text-neutral-600" />
                    <x-ui.text>{{ __('Select a project from the header to view analytics.') }}</x-ui.text>
                </x-ui.empty.contents>
            </x-ui.empty>
        </x-ui.card>
    @elseif (!$configured)
        <x-ui.card>
            <x-ui.empty>
                <x-ui.empty.contents>
                    <x-ui.icon name="plug" class="size-10 text-neutral-300 dark:text-neutral-600" />
                    <x-ui.text>
                        {{ __('No Google Analytics property configured for this project.') }}
                    </x-ui.text>
                    <x-ui.button :href="route('project.edit', $project)" variant="outline" icon="gear" class="mt-2">
                        {{ __('Configure in project settings') }}
                    </x-ui.button>
                </x-ui.empty.contents>
            </x-ui.empty>
        </x-ui.card>
    @else
        {{-- Range controls + refresh + loading indicator --}}
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <x-ui.radio.group wire:model.live="rangePreset" direction="horizontal" variant="segmented">
                <x-ui.radio.item value="today" :label="__('Today')" />
                <x-ui.radio.item value="last_7" :label="__('Last 7 days')" />
                <x-ui.radio.item value="last_28" :label="__('Last 28 days')" />
                <x-ui.radio.item value="last_30" :label="__('Last 30 days')" />
                <x-ui.radio.item value="custom" :label="__('Custom')" />
            </x-ui.radio.group>

            <div class="flex items-center gap-3 flex-wrap">
                @if ($rangePreset === 'custom')
                    <div class="flex items-center gap-2">
                        <x-ui.label>{{ __('From') }}</x-ui.label>
                        <x-ui.input type="date" wire:model.live="dateFrom" class="w-44" />
                    </div>
                    <div class="flex items-center gap-2">
                        <x-ui.label>{{ __('To') }}</x-ui.label>
                        <x-ui.input type="date" wire:model.live="dateTo" class="w-44" />
                    </div>
                @else
                    <div class="text-xs text-neutral-500 dark:text-neutral-400 tabular-nums">
                        {{ $rangeFrom ?? $dateFrom }} → {{ $rangeTo ?? $dateTo }}
                    </div>
                @endif

                {{-- Loading indicator — appears for any wire request while it's in flight.
                     The .delay.short modifier suppresses flash on warm-cache renders (<200ms). --}}
                <span wire:loading.delay.short class="inline-flex items-center gap-1.5 text-xs text-neutral-500 dark:text-neutral-400">
                    <x-ui.icon name="circle-notch" class="size-3.5 animate-spin" />
                    {{ __('Refreshing...') }}
                </span>

                <x-ui.button type="button" wire:click="forceRefresh" wire:loading.attr="disabled"
                    variant="outline" color="neutral" size="sm" icon="arrow-clockwise">
                    {{ __('Refresh') }}
                </x-ui.button>
            </div>
        </div>

        @if ($error)
            <x-ui.card>
                <x-ui.error :messages="[$error]" />
            </x-ui.card>
        @else
            {{-- KPI tiles --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                @php
                    $tiles = [
                        ['label' => __('Sessions'),         'value' => $formatNumber($overview->sessions),                       'metric' => 'sessions'],
                        ['label' => __('Users'),            'value' => $formatNumber($overview->totalUsers),                     'metric' => 'totalUsers'],
                        ['label' => __('Engagement rate'),  'value' => $formatPercent($overview->engagementRate),                'metric' => 'engagementRate'],
                        ['label' => __('Bounce rate'),      'value' => $formatPercent($overview->bounceRate),                    'metric' => 'bounceRate'],
                        ['label' => __('Avg. session'),     'value' => $formatDuration($overview->averageSessionDurationSeconds), 'metric' => 'averageSessionDuration'],
                    ];
                @endphp
                @foreach ($tiles as $t)
                    @php $delta = $formatDelta($compare->deltas[$t['metric']] ?? null); @endphp
                    <x-ui.card size="full">
                        <div class="flex flex-col gap-1">
                            <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">
                                {{ $t['label'] }}
                            </span>
                            <span class="text-2xl font-semibold tabular-nums text-neutral-900 dark:text-neutral-100">
                                {{ $t['value'] }}
                            </span>
                            <span class="text-xs tabular-nums {{ $delta['class'] }}">
                                {{ $delta['text'] }}
                                <span class="text-neutral-400 dark:text-neutral-500">{{ __('vs prev.') }}</span>
                            </span>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            {{-- Acquisition + Device side by side --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <x-ui.card size="full">
                    <x-ui.heading level="h2" size="md" class="mb-3">{{ __('Acquisition channels') }}</x-ui.heading>
                    @if ($acquisition->isEmpty())
                        <x-ui.text class="text-neutral-500">{{ __('No acquisition data.') }}</x-ui.text>
                    @else
                        <table class="w-full text-sm">
                            <thead class="text-xs text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">
                                <tr>
                                    <th class="text-left py-2 font-medium">{{ __('Channel') }}</th>
                                    <th class="text-right py-2 font-medium">{{ __('Sessions') }}</th>
                                    <th class="text-right py-2 font-medium">{{ __('Engaged') }}</th>
                                    <th class="text-right py-2 font-medium">{{ __('Conv.') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-black/5 dark:divide-white/5">
                                @foreach ($acquisition->rows() as $row)
                                    <tr>
                                        <td class="py-2 font-medium text-neutral-900 dark:text-neutral-100">
                                            {{ $row->dimension('sessionDefaultChannelGroup') ?: __('(not set)') }}
                                        </td>
                                        <td class="py-2 text-right tabular-nums">{{ $formatNumber($row->metric('sessions')) }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ $formatNumber($row->metric('engagedSessions')) }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ $formatNumber($row->metric('conversions')) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </x-ui.card>

                <x-ui.card size="full">
                    <x-ui.heading level="h2" size="md" class="mb-3">{{ __('Device breakdown') }}</x-ui.heading>
                    @if ($devices->isEmpty())
                        <x-ui.text class="text-neutral-500">{{ __('No device data.') }}</x-ui.text>
                    @else
                        <table class="w-full text-sm">
                            <thead class="text-xs text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">
                                <tr>
                                    <th class="text-left py-2 font-medium">{{ __('Device') }}</th>
                                    <th class="text-right py-2 font-medium">{{ __('Sessions') }}</th>
                                    <th class="text-right py-2 font-medium">{{ __('Users') }}</th>
                                    <th class="text-right py-2 font-medium">{{ __('Engagement') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-black/5 dark:divide-white/5">
                                @foreach ($devices->rows() as $row)
                                    <tr>
                                        <td class="py-2 font-medium text-neutral-900 dark:text-neutral-100 capitalize">
                                            {{ $row->dimension('deviceCategory') }}
                                        </td>
                                        <td class="py-2 text-right tabular-nums">{{ $formatNumber($row->metric('sessions')) }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ $formatNumber($row->metric('totalUsers')) }}</td>
                                        <td class="py-2 text-right tabular-nums">{{ $formatPercent((float) $row->metric('engagementRate')) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </x-ui.card>
            </div>

            {{-- Top pages preview --}}
            <x-ui.card size="full">
                <div class="flex items-center justify-between mb-3">
                    <x-ui.heading level="h2" size="md">{{ __('Top pages') }}</x-ui.heading>
                    <x-ui.link href="/google-analytics/pages">{{ __('View all') }} →</x-ui.link>
                </div>
                @if ($topPages->isEmpty())
                    <x-ui.text class="text-neutral-500">{{ __('No page data.') }}</x-ui.text>
                @else
                    <table class="w-full text-sm">
                        <thead class="text-xs text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">
                            <tr>
                                <th class="text-left py-2 font-medium">{{ __('Path') }}</th>
                                <th class="text-right py-2 font-medium">{{ __('Views') }}</th>
                                <th class="text-right py-2 font-medium">{{ __('Sessions') }}</th>
                                <th class="text-right py-2 font-medium">{{ __('Engagement') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-black/5 dark:divide-white/5">
                            @foreach ($topPages->rows() as $row)
                                <tr>
                                    <td class="py-2 font-mono text-xs text-neutral-700 dark:text-neutral-300 truncate max-w-md">
                                        {{ $row->dimension('pagePath') }}
                                    </td>
                                    <td class="py-2 text-right tabular-nums">{{ $formatNumber($row->metric('screenPageViews')) }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $formatNumber($row->metric('sessions')) }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $formatPercent((float) $row->metric('engagementRate')) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-ui.card>

            <div class="text-xs text-neutral-400 dark:text-neutral-500">
                {{ __('Fetched at') }} {{ $overview->fetchedAt->setTimezone(config('app.timezone'))->format('Y-m-d H:i') }}
                · {{ __('cached, refreshes per range recency') }}
            </div>
        @endif
    @endif
</div>
