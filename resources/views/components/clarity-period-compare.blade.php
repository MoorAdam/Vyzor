@props([
    'current' => null,
    'previous' => null,
    'currentFrom' => null,
    'currentTo' => null,
    'previousFrom' => null,
    'previousTo' => null,
])

@php
    $current = $current ?? collect();
    $previous = $previous ?? collect();

    if ($current->isEmpty() && $previous->isEmpty()) {
        // Caller renders an empty state above us — bail out.
        return;
    }

    /**
     * Rules of thumb for a single KPI compare row:
     *  - "direction" = whether a higher number is good (sessions, score) or bad (rage clicks, bots).
     *  - We deliberately don't suppress 0-vs-0 rows so the table reads consistently.
     */
    $traffic = fn ($c) => $c->get('Traffic')?->data[0] ?? [];
    $scroll  = fn ($c) => $c->get('ScrollDepth')?->data[0] ?? [];
    $eng     = fn ($c) => $c->get('EngagementTime')?->data[0] ?? [];
    $signal  = fn ($c, $key) => $c->get($key)?->data[0] ?? [];

    // UX score reused from the score blade — duplicated here so compare can render the delta.
    $signalWeights = [
        'RageClickCount' => 5, 'ScriptErrorCount' => 4, 'DeadClickCount' => 3,
        'ErrorClickCount' => 3, 'QuickbackClick' => 2, 'ExcessiveScroll' => 1,
    ];
    $scoreFor = function ($insights) use ($signalWeights) {
        $deduction = 0.0;
        foreach ($signalWeights as $key => $w) {
            $pct = (float) ($insights->get($key)?->data[0]['sessionsWithMetricPercentage'] ?? 0);
            $deduction += $pct * $w;
        }
        return (int) max(0, min(100, round(100 - $deduction)));
    };

    $rows = [
        ['label' => __('Sessions'),         'cur' => (int) ($traffic($current)['totalSessionCount'] ?? 0),         'prev' => (int) ($traffic($previous)['totalSessionCount'] ?? 0),         'good' => 'up',   'fmt' => 'int'],
        ['label' => __('Unique Users'),     'cur' => (int) ($traffic($current)['distinctUserCount'] ?? 0),          'prev' => (int) ($traffic($previous)['distinctUserCount'] ?? 0),          'good' => 'up',   'fmt' => 'int'],
        ['label' => __('Bot Sessions'),     'cur' => (int) ($traffic($current)['totalBotSessionCount'] ?? 0),       'prev' => (int) ($traffic($previous)['totalBotSessionCount'] ?? 0),       'good' => 'down', 'fmt' => 'int'],
        ['label' => __('Pages / Session'),  'cur' => (float) ($traffic($current)['pagesPerSessionPercentage'] ?? 0),'prev' => (float) ($traffic($previous)['pagesPerSessionPercentage'] ?? 0),'good' => 'up',   'fmt' => 'dec2'],
        ['label' => __('Avg Scroll Depth'), 'cur' => (float) ($scroll($current)['averageScrollDepth'] ?? 0),        'prev' => (float) ($scroll($previous)['averageScrollDepth'] ?? 0),        'good' => 'up',   'fmt' => 'pct1'],
        ['label' => __('Total Time'),       'cur' => (int) ($eng($current)['totalTime'] ?? 0),                     'prev' => (int) ($eng($previous)['totalTime'] ?? 0),                     'good' => 'up',   'fmt' => 'sec'],
        ['label' => __('Active Time'),      'cur' => (int) ($eng($current)['activeTime'] ?? 0),                    'prev' => (int) ($eng($previous)['activeTime'] ?? 0),                    'good' => 'up',   'fmt' => 'sec'],
        ['label' => __('UX Health Score'),  'cur' => $scoreFor($current),                                            'prev' => $scoreFor($previous),                                          'good' => 'up',   'fmt' => 'int', 'highlight' => true],
        ['label' => __('Rage Clicks'),      'cur' => (int) ($signal($current, 'RageClickCount')['subTotal'] ?? 0),  'prev' => (int) ($signal($previous, 'RageClickCount')['subTotal'] ?? 0),  'good' => 'down', 'fmt' => 'int'],
        ['label' => __('Dead Clicks'),      'cur' => (int) ($signal($current, 'DeadClickCount')['subTotal'] ?? 0),  'prev' => (int) ($signal($previous, 'DeadClickCount')['subTotal'] ?? 0),  'good' => 'down', 'fmt' => 'int'],
        ['label' => __('Quick Backs'),      'cur' => (int) ($signal($current, 'QuickbackClick')['subTotal'] ?? 0),  'prev' => (int) ($signal($previous, 'QuickbackClick')['subTotal'] ?? 0),  'good' => 'down', 'fmt' => 'int'],
        ['label' => __('Script Errors'),    'cur' => (int) ($signal($current, 'ScriptErrorCount')['subTotal'] ?? 0),'prev' => (int) ($signal($previous, 'ScriptErrorCount')['subTotal'] ?? 0),'good' => 'down', 'fmt' => 'int'],
        ['label' => __('Error Clicks'),     'cur' => (int) ($signal($current, 'ErrorClickCount')['subTotal'] ?? 0), 'prev' => (int) ($signal($previous, 'ErrorClickCount')['subTotal'] ?? 0), 'good' => 'down', 'fmt' => 'int'],
        ['label' => __('Excessive Scroll'), 'cur' => (int) ($signal($current, 'ExcessiveScroll')['subTotal'] ?? 0), 'prev' => (int) ($signal($previous, 'ExcessiveScroll')['subTotal'] ?? 0), 'good' => 'down', 'fmt' => 'int'],
    ];

    $fmt = function ($v, $type) {
        return match ($type) {
            'int'  => number_format((int) $v),
            'dec2' => number_format((float) $v, 2),
            'pct1' => number_format((float) $v, 1) . '%',
            'sec'  => $v >= 3600
                ? number_format($v / 3600, 1) . 'h'
                : ($v >= 60 ? number_format($v / 60, 1) . 'm' : ((int) $v) . 's'),
            default => (string) $v,
        };
    };

    $changeFor = function (float $cur, float $prev, string $good) {
        if ($prev == 0.0 && $cur == 0.0) {
            return ['pct' => null, 'tone' => 'neutral', 'arrow' => null];
        }
        if ($prev == 0.0) {
            // No baseline — flag as new but skip a misleading %.
            return ['pct' => null, 'tone' => $good === 'up' ? 'good' : 'bad', 'arrow' => 'up', 'isNew' => true];
        }
        $pct = (($cur - $prev) / $prev) * 100;
        $arrow = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : null);
        $tone = 'neutral';
        if ($arrow === 'up') {
            $tone = $good === 'up' ? 'good' : 'bad';
        } elseif ($arrow === 'down') {
            $tone = $good === 'down' ? 'good' : 'bad';
        }
        return ['pct' => $pct, 'tone' => $tone, 'arrow' => $arrow];
    };
@endphp

<x-ui.card size="full" class="overflow-hidden p-0!">
    <div class="px-4 py-3 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/50">
        <div class="flex items-center gap-2">
            <x-ui.icon name="git-diff" class="size-5 text-violet-500" />
            <x-ui.heading level="h3" size="sm">{{ __('Period Comparison') }}</x-ui.heading>
        </div>
        <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-neutral-500 dark:text-neutral-400">
            @if ($currentFrom && $currentTo)
                <span><span class="inline-block size-2 rounded-full bg-blue-500 mr-1.5"></span>{{ __('Current') }}: {{ $currentFrom->format('M d') }} → {{ $currentTo->format('M d, Y') }}</span>
            @endif
            @if ($previousFrom && $previousTo)
                <span><span class="inline-block size-2 rounded-full bg-neutral-400 mr-1.5"></span>{{ __('Previous') }}: {{ $previousFrom->format('M d') }} → {{ $previousTo->format('M d, Y') }}</span>
            @endif
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400 bg-neutral-50/50 dark:bg-neutral-900/30">
                <tr>
                    <th class="text-left px-4 py-2 font-medium">{{ __('Metric') }}</th>
                    <th class="text-right px-4 py-2 font-medium">{{ __('Current') }}</th>
                    <th class="text-right px-4 py-2 font-medium">{{ __('Previous') }}</th>
                    <th class="text-right px-4 py-2 font-medium">{{ __('Change') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                @foreach ($rows as $row)
                    @php
                        $change = $changeFor((float) $row['cur'], (float) $row['prev'], $row['good']);
                        $toneClass = match ($change['tone']) {
                            'good' => 'text-emerald-600 dark:text-emerald-400',
                            'bad'  => 'text-red-600 dark:text-red-400',
                            default => 'text-neutral-500 dark:text-neutral-400',
                        };
                        $arrowIcon = $change['arrow'] === 'up' ? 'arrow-up' : ($change['arrow'] === 'down' ? 'arrow-down' : 'minus');
                        $isHighlight = $row['highlight'] ?? false;
                    @endphp
                    <tr @class(['hover:bg-neutral-50/50 dark:hover:bg-neutral-900/30 transition-colors', 'bg-neutral-50/40 dark:bg-neutral-900/20 font-medium' => $isHighlight])>
                        <td class="px-4 py-2.5 text-neutral-700 dark:text-neutral-300">{{ $row['label'] }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-neutral-900 dark:text-neutral-100">{{ $fmt($row['cur'], $row['fmt']) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-neutral-500 dark:text-neutral-400">{{ $fmt($row['prev'], $row['fmt']) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums {{ $toneClass }}">
                            <span class="inline-flex items-center gap-1 justify-end">
                                @if (!empty($change['isNew']))
                                    <x-ui.badge size="sm" color="emerald" variant="outline">{{ __('NEW') }}</x-ui.badge>
                                @elseif ($change['pct'] === null)
                                    <span>—</span>
                                @else
                                    <x-ui.icon name="{{ $arrowIcon }}" class="size-3" />
                                    <span>{{ ($change['pct'] > 0 ? '+' : '') . number_format($change['pct'], 1) }}%</span>
                                @endif
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-ui.card>
