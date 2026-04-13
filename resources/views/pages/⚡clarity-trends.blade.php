<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Models\ClarityInsight;
use Carbon\Carbon;

new #[Layout('layouts.app')] class extends Component {

    public string $dateFrom = '';
    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(3)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    #[On('current-project-changed')]
    public function onProjectChanged() {}

    #[On('clarity-fetched')]
    public function onClarityFetched(): void {}

    public function with(): array
    {
        $projectId = session('current_project_id');

        if (!$projectId) {
            return ['chartData' => null];
        }

        $start = Carbon::parse($this->dateFrom)->startOfDay();
        $end = Carbon::parse($this->dateTo)->endOfDay();

        // Use date_from to find data covering the last 3 days
        $insights = ClarityInsight::where('project_id', $projectId)
            ->where('date_from', '>=', $start)
            ->where('date_to', '<=', $end)
            ->whereNull('dimension1')
            ->orderBy('date_from')
            ->get();

        // Group by the date range the data covers (date_from → date_to)
        $grouped = $insights->groupBy(function ($i) {
            $from = $i->date_from->format('M d');
            $to = $i->date_to->format('M d');
            return $from === $to ? $from : "{$from} – {$to}";
        });

        $labels = $grouped->keys()->toArray();

        // --- Traffic metrics over time ---
        $sessions = [];
        $uniqueUsers = [];
        $pagesPerSession = [];
        $botSessions = [];

        foreach ($grouped as $time => $metrics) {
            $traffic = $metrics->firstWhere('metric_name', 'Traffic');
            $t = $traffic?->data[0] ?? [];
            $sessions[] = $t['totalSessionCount'] ?? 0;
            $uniqueUsers[] = $t['distinctUserCount'] ?? 0;
            $pagesPerSession[] = round($t['pagesPerSessionPercentage'] ?? 0, 2);
            $botSessions[] = $t['totalBotSessionCount'] ?? 0;
        }

        // --- Scroll depth over time ---
        $scrollDepth = [];
        foreach ($grouped as $time => $metrics) {
            $scroll = $metrics->firstWhere('metric_name', 'ScrollDepth');
            $s = $scroll?->data[0] ?? [];
            $scrollDepth[] = round($s['averageScrollDepth'] ?? 0, 1);
        }

        // --- Engagement time over time ---
        $totalTime = [];
        $activeTime = [];
        foreach ($grouped as $time => $metrics) {
            $eng = $metrics->firstWhere('metric_name', 'EngagementTime');
            $e = $eng?->data[0] ?? [];
            $totalTime[] = $e['totalTime'] ?? 0;
            $activeTime[] = $e['activeTime'] ?? 0;
        }

        // --- UX Signals over time ---
        $signalKeys = ['DeadClickCount', 'RageClickCount', 'QuickbackClick', 'ExcessiveScroll', 'ScriptErrorCount', 'ErrorClickCount'];
        $signalData = [];
        foreach ($signalKeys as $key) {
            $signalData[$key] = [];
        }
        foreach ($grouped as $time => $metrics) {
            foreach ($signalKeys as $key) {
                $metric = $metrics->firstWhere('metric_name', $key);
                $d = $metric?->data[0] ?? [];
                $signalData[$key][] = $d['subTotal'] ?? 0;
            }
        }

        // --- Latest snapshot breakdowns for doughnuts ---
        $deviceBreakdown = $this->buildBreakdown($projectId, $end, 'Device');
        $browserBreakdown = $this->buildBreakdown($projectId, $end, 'Browser');
        $countryBreakdown = $this->buildBreakdown($projectId, $end, 'Country');

        return [
            'chartData' => [
                'labels' => $labels,
                'sessions' => $sessions,
                'uniqueUsers' => $uniqueUsers,
                'pagesPerSession' => $pagesPerSession,
                'botSessions' => $botSessions,
                'scrollDepth' => $scrollDepth,
                'totalTime' => $totalTime,
                'activeTime' => $activeTime,
                'signals' => $signalData,
                'deviceBreakdown' => $deviceBreakdown,
                'browserBreakdown' => $browserBreakdown,
                'countryBreakdown' => $countryBreakdown,
            ],
        ];
    }

    private function buildBreakdown($projectId, $end, string $dimensionMetric): array
    {
        $insight = ClarityInsight::where('project_id', $projectId)
            ->where('metric_name', $dimensionMetric)
            ->where('date_to', '<=', $end)
            ->orderByDesc('date_from')
            ->first();

        if (!$insight || empty($insight->data)) {
            return ['labels' => [], 'values' => []];
        }

        $labels = [];
        $values = [];
        foreach (array_slice($insight->data, 0, 8) as $row) {
            $labels[] = $row['name'] ?? '—';
            $values[] = $row['sessionsCount'] ?? 0;
        }

        return ['labels' => $labels, 'values' => $values];
    }
};
?>

<div class="p-6 space-y-6" id="clarity-trends-root" data-chart='@json($chartData)'
     data-i18n-sessions="{{ __('Sessions') }}"
     data-i18n-unique-users="{{ __('Unique Users') }}"
     data-i18n-bot-sessions="{{ __('Bot Sessions') }}"
     data-i18n-pages-per-session="{{ __('Pages / Session') }}"
     data-i18n-total-time="{{ __('Total Time') }}"
     data-i18n-active-time="{{ __('Active Time') }}"
     data-i18n-avg-scroll-depth="{{ __('Avg Scroll Depth %') }}"
     data-i18n-dead-clicks="{{ __('Dead Clicks') }}"
     data-i18n-rage-clicks="{{ __('Rage Clicks') }}"
     data-i18n-quick-backs="{{ __('Quick Backs') }}"
     data-i18n-excessive-scroll="{{ __('Excessive Scroll') }}"
     data-i18n-script-errors="{{ __('Script Errors') }}"
     data-i18n-error-clicks="{{ __('Error Clicks') }}"
>
    <div class="flex items-center justify-between">
        <div>
            <x-ui.heading level="h1" size="xl">{{ __('Clarity Trends') }}</x-ui.heading>
            <x-ui.description class="mt-1">{{ __('Clarity data trends for the current project.') }}</x-ui.description>
        </div>
        <div class="flex items-end gap-3">
            <div class="w-52">
                <x-ui.field>
                    <x-ui.label>{{ __('From') }}</x-ui.label>
                    <x-ui.date-picker wire:model.live="dateFrom" class="w-full" />
                </x-ui.field>
            </div>
            <div class="w-52">
                <x-ui.field>
                    <x-ui.label>{{ __('To') }}</x-ui.label>
                    <x-ui.date-picker wire:model.live="dateTo" class="w-full" />
                </x-ui.field>
            </div>
            <livewire:clarity-fetch-button />
        </div>
    </div>

    @if (!$chartData)
        <x-ui.card>
            <x-ui.empty>
                <x-ui.empty.contents>
                    <x-ui.icon name="chart-line-up" class="size-10 text-neutral-300 dark:text-neutral-600" />
                    <x-ui.text>{{ __('No project selected. Please select a project first.') }}</x-ui.text>
                </x-ui.empty.contents>
            </x-ui.empty>
        </x-ui.card>
    @elseif (empty($chartData['labels']))
        <x-ui.card>
            <x-ui.empty>
                <x-ui.empty.contents>
                    <x-ui.icon name="chart-line-up" class="size-10 text-neutral-300 dark:text-neutral-600" />
                    <x-ui.text>{{ __('No trend data available yet. Fetch Clarity data multiple times from the Snapshot page to build up trend history.') }}</x-ui.text>
                </x-ui.empty.contents>
            </x-ui.empty>
        </x-ui.card>
    @else
        {{-- Traffic Over Time --}}
        <x-ui.heading level="h3" size="md" class="mb-3">{{ __('Traffic Over Time') }}</x-ui.heading>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <x-ui.card size="full" class="border-l-4 border-l-blue-500">
                <div class="flex items-center gap-2 mb-4">
                    <x-ui.icon name="users" class="size-5 text-blue-500" />
                    <x-ui.heading level="h3" size="sm">{{ __('Sessions & Unique Users') }}</x-ui.heading>
                </div>
                <div class="h-72" wire:ignore>
                    <canvas id="sessionsChart"></canvas>
                </div>
            </x-ui.card>

            <x-ui.card size="full" class="border-l-4 border-l-emerald-500">
                <div class="flex items-center gap-2 mb-4">
                    <x-ui.icon name="copy" class="size-5 text-emerald-500" />
                    <x-ui.heading level="h3" size="sm">{{ __('Pages Per Session') }}</x-ui.heading>
                </div>
                <div class="h-72" wire:ignore>
                    <canvas id="pagesPerSessionChart"></canvas>
                </div>
            </x-ui.card>
        </div>

        {{-- Engagement Over Time --}}
        <x-ui.heading level="h3" size="md" class="mt-6 mb-3">{{ __('Engagement Over Time') }}</x-ui.heading>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <x-ui.card size="full" class="border-l-4 border-l-cyan-500">
                <div class="flex items-center gap-2 mb-4">
                    <x-ui.icon name="clock" class="size-5 text-cyan-500" />
                    <x-ui.heading level="h3" size="sm">{{ __('Engagement Time (seconds)') }}</x-ui.heading>
                </div>
                <div class="h-72" wire:ignore>
                    <canvas id="engagementChart"></canvas>
                </div>
            </x-ui.card>

            <x-ui.card size="full" class="border-l-4 border-l-amber-500">
                <div class="flex items-center gap-2 mb-4">
                    <x-ui.icon name="arrows-down-up" class="size-5 text-amber-500" />
                    <x-ui.heading level="h3" size="sm">{{ __('Average Scroll Depth (%)') }}</x-ui.heading>
                </div>
                <div class="h-72" wire:ignore>
                    <canvas id="scrollChart"></canvas>
                </div>
            </x-ui.card>
        </div>

        {{-- UX Signals Over Time --}}
        <x-ui.heading level="h3" size="md" class="mt-6 mb-3">{{ __('UX Signals Over Time') }}</x-ui.heading>
        <x-ui.card size="full" class="border-l-4 border-l-red-500">
            <div class="flex items-center gap-2 mb-4">
                <x-ui.icon name="warning" class="size-5 text-red-500" />
                <x-ui.heading level="h3" size="sm">{{ __('UX Issues') }}</x-ui.heading>
            </div>
            <div class="h-80" wire:ignore>
                <canvas id="signalsChart"></canvas>
            </div>
        </x-ui.card>

        {{-- Distribution Doughnuts --}}
        <x-ui.heading level="h3" size="md" class="mt-6 mb-3">{{ __('Audience Breakdown (Latest Snapshot)') }}</x-ui.heading>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-ui.card size="full" class="border-l-4 border-l-violet-500">
                <div class="flex items-center gap-2 mb-4">
                    <x-ui.icon name="device-mobile" class="size-5 text-violet-500" />
                    <x-ui.heading level="h3" size="sm">{{ __('Devices') }}</x-ui.heading>
                </div>
                <div class="h-64 flex items-center justify-center" wire:ignore>
                    <canvas id="deviceChart"></canvas>
                </div>
            </x-ui.card>

            <x-ui.card size="full" class="border-l-4 border-l-blue-500">
                <div class="flex items-center gap-2 mb-4">
                    <x-ui.icon name="globe" class="size-5 text-blue-500" />
                    <x-ui.heading level="h3" size="sm">{{ __('Browsers') }}</x-ui.heading>
                </div>
                <div class="h-64 flex items-center justify-center" wire:ignore>
                    <canvas id="browserChart"></canvas>
                </div>
            </x-ui.card>

            <x-ui.card size="full" class="border-l-4 border-l-amber-500">
                <div class="flex items-center gap-2 mb-4">
                    <x-ui.icon name="map-pin" class="size-5 text-amber-500" />
                    <x-ui.heading level="h3" size="sm">{{ __('Countries') }}</x-ui.heading>
                </div>
                <div class="h-64 flex items-center justify-center" wire:ignore>
                    <canvas id="countryChart"></canvas>
                </div>
            </x-ui.card>
        </div>
    @endif
</div>

@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
@endassets

@script
<script>
    const charts = {};

    function destroyCharts() {
        for (const id in charts) {
            try { charts[id].destroy(); } catch (e) {}
            delete charts[id];
        }
    }

    function renderCharts() {
        const root = document.getElementById('clarity-trends-root');
        if (!root) return;

        destroyCharts();

        let chartData;
        try {
            chartData = JSON.parse(root.dataset.chart);
        } catch (e) {
            return;
        }

        if (!chartData || !chartData.labels || chartData.labels.length === 0) return;

        const i18n = {
            sessions: root.dataset.i18nSessions,
            uniqueUsers: root.dataset.i18nUniqueUsers,
            botSessions: root.dataset.i18nBotSessions,
            pagesPerSession: root.dataset.i18nPagesPerSession,
            totalTime: root.dataset.i18nTotalTime,
            activeTime: root.dataset.i18nActiveTime,
            avgScrollDepth: root.dataset.i18nAvgScrollDepth,
            deadClicks: root.dataset.i18nDeadClicks,
            rageClicks: root.dataset.i18nRageClicks,
            quickBacks: root.dataset.i18nQuickBacks,
            excessiveScroll: root.dataset.i18nExcessiveScroll,
            scriptErrors: root.dataset.i18nScriptErrors,
            errorClicks: root.dataset.i18nErrorClicks,
        };

        const isDark = document.documentElement.classList.contains('dark') ||
            window.matchMedia('(prefers-color-scheme: dark)').matches;

        const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.08)';
        const textColor = isDark ? '#a3a3a3' : '#525252';

        const baseOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: { color: textColor, boxWidth: 12, padding: 16 }
                },
                tooltip: {
                    backgroundColor: isDark ? '#262626' : '#fff',
                    titleColor: isDark ? '#e5e5e5' : '#171717',
                    bodyColor: isDark ? '#a3a3a3' : '#525252',
                    borderColor: isDark ? '#404040' : '#e5e5e5',
                    borderWidth: 1,
                }
            },
            scales: {
                x: {
                    ticks: { color: textColor, maxRotation: 45 },
                    grid: { color: gridColor }
                },
                y: {
                    ticks: { color: textColor },
                    grid: { color: gridColor },
                    beginAtZero: true
                }
            }
        };

        const labels = chartData.labels;

        const sessionsEl = document.getElementById('sessionsChart');
        if (sessionsEl) {
            charts.sessions = new Chart(sessionsEl, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: i18n.sessions,
                            data: chartData.sessions,
                            borderColor: '#3b82f6',
                            backgroundColor: 'rgba(59,130,246,0.1)',
                            fill: true, tension: 0.3,
                        },
                        {
                            label: i18n.uniqueUsers,
                            data: chartData.uniqueUsers,
                            borderColor: '#8b5cf6',
                            backgroundColor: 'rgba(139,92,246,0.1)',
                            fill: true, tension: 0.3,
                        },
                        {
                            label: i18n.botSessions,
                            data: chartData.botSessions,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239,68,68,0.05)',
                            borderDash: [5, 5],
                            fill: false, tension: 0.3,
                        }
                    ]
                },
                options: baseOptions
            });
        }

        const ppsEl = document.getElementById('pagesPerSessionChart');
        if (ppsEl) {
            charts.pps = new Chart(ppsEl, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: i18n.pagesPerSession,
                        data: chartData.pagesPerSession,
                        backgroundColor: 'rgba(16,185,129,0.6)',
                        borderColor: '#10b981',
                        borderWidth: 1, borderRadius: 4,
                    }]
                },
                options: baseOptions
            });
        }

        const engEl = document.getElementById('engagementChart');
        if (engEl) {
            charts.engagement = new Chart(engEl, {
                type: 'line',
                data: {
                    labels,
                    datasets: [
                        {
                            label: i18n.totalTime,
                            data: chartData.totalTime,
                            borderColor: '#06b6d4',
                            backgroundColor: 'rgba(6,182,212,0.1)',
                            fill: true, tension: 0.3,
                        },
                        {
                            label: i18n.activeTime,
                            data: chartData.activeTime,
                            borderColor: '#14b8a6',
                            backgroundColor: 'rgba(20,184,166,0.1)',
                            fill: true, tension: 0.3,
                        }
                    ]
                },
                options: baseOptions
            });
        }

        const scrollEl = document.getElementById('scrollChart');
        if (scrollEl) {
            charts.scroll = new Chart(scrollEl, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: i18n.avgScrollDepth,
                        data: chartData.scrollDepth,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245,158,11,0.1)',
                        fill: true, tension: 0.3,
                    }]
                },
                options: {
                    ...baseOptions,
                    scales: {
                        ...baseOptions.scales,
                        y: { ...baseOptions.scales.y, max: 100 }
                    }
                }
            });
        }

        const signalColors = {
            DeadClickCount:   { border: '#f97316', bg: 'rgba(249,115,22,0.1)' },
            RageClickCount:   { border: '#ef4444', bg: 'rgba(239,68,68,0.1)' },
            QuickbackClick:   { border: '#f59e0b', bg: 'rgba(245,158,11,0.1)' },
            ExcessiveScroll:  { border: '#eab308', bg: 'rgba(234,179,8,0.1)' },
            ScriptErrorCount: { border: '#e11d48', bg: 'rgba(225,29,72,0.1)' },
            ErrorClickCount:  { border: '#ec4899', bg: 'rgba(236,72,153,0.1)' },
        };
        const signalLabels = {
            DeadClickCount: i18n.deadClicks,
            RageClickCount: i18n.rageClicks,
            QuickbackClick: i18n.quickBacks,
            ExcessiveScroll: i18n.excessiveScroll,
            ScriptErrorCount: i18n.scriptErrors,
            ErrorClickCount: i18n.errorClicks,
        };

        const signalDatasets = Object.entries(chartData.signals || {})
            .filter(([_, values]) => values.some(v => v > 0))
            .map(([key, values]) => ({
                label: signalLabels[key] || key,
                data: values,
                borderColor: signalColors[key]?.border || '#888',
                backgroundColor: signalColors[key]?.bg || 'rgba(136,136,136,0.1)',
                fill: false, tension: 0.3,
            }));

        const signalsEl = document.getElementById('signalsChart');
        if (signalsEl) {
            charts.signals = new Chart(signalsEl, {
                type: 'line',
                data: { labels, datasets: signalDatasets },
                options: baseOptions
            });
        }

        const doughnutColors = [
            '#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444',
            '#06b6d4', '#ec4899', '#84cc16'
        ];
        const doughnutOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: textColor, boxWidth: 10, padding: 10, font: { size: 11 } }
                }
            }
        };

        const deviceEl = document.getElementById('deviceChart');
        if (deviceEl && chartData.deviceBreakdown?.labels?.length > 0) {
            charts.device = new Chart(deviceEl, {
                type: 'doughnut',
                data: {
                    labels: chartData.deviceBreakdown.labels,
                    datasets: [{ data: chartData.deviceBreakdown.values, backgroundColor: doughnutColors, borderWidth: 0 }]
                },
                options: doughnutOptions
            });
        }

        const browserEl = document.getElementById('browserChart');
        if (browserEl && chartData.browserBreakdown?.labels?.length > 0) {
            charts.browser = new Chart(browserEl, {
                type: 'doughnut',
                data: {
                    labels: chartData.browserBreakdown.labels,
                    datasets: [{ data: chartData.browserBreakdown.values, backgroundColor: doughnutColors, borderWidth: 0 }]
                },
                options: doughnutOptions
            });
        }

        const countryEl = document.getElementById('countryChart');
        if (countryEl && chartData.countryBreakdown?.labels?.length > 0) {
            charts.country = new Chart(countryEl, {
                type: 'doughnut',
                data: {
                    labels: chartData.countryBreakdown.labels,
                    datasets: [{ data: chartData.countryBreakdown.values, backgroundColor: doughnutColors, borderWidth: 0 }]
                },
                options: doughnutOptions
            });
        }
    }

    renderCharts();

    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => {
            queueMicrotask(() => renderCharts());
        });
    });
</script>
@endscript
