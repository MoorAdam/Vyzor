@props([
    'insights' => null,
])

@php
    $insights = $insights ?? collect();

    // Each UX signal contributes its session-percentage × weight to a deduction.
    // Weights reflect severity: rage clicks > script errors > dead/error clicks > quickback > excessive scroll.
    $signalWeights = [
        'RageClickCount'   => ['weight' => 5, 'label' => __('Rage Clicks')],
        'ScriptErrorCount' => ['weight' => 4, 'label' => __('Script Errors')],
        'DeadClickCount'   => ['weight' => 3, 'label' => __('Dead Clicks')],
        'ErrorClickCount'  => ['weight' => 3, 'label' => __('Error Clicks')],
        'QuickbackClick'   => ['weight' => 2, 'label' => __('Quick Backs')],
        'ExcessiveScroll'  => ['weight' => 1, 'label' => __('Excessive Scroll')],
    ];

    $deduction = 0.0;
    $contributors = [];
    foreach ($signalWeights as $key => $cfg) {
        $metric = $insights->get($key);
        if (!$metric) continue;
        $pct = (float) ($metric->data[0]['sessionsWithMetricPercentage'] ?? 0);
        $points = $pct * $cfg['weight'];
        $deduction += $points;
        if ($points > 0) {
            $contributors[] = ['label' => $cfg['label'], 'pct' => $pct, 'points' => $points];
        }
    }

    $score = (int) max(0, min(100, round(100 - $deduction)));

    $hasSignalData = !empty($contributors) || $insights->keys()->intersect(array_keys($signalWeights))->isNotEmpty();

    [$tone, $label, $bar] = match (true) {
        $score >= 80 => ['emerald', __('Healthy'),         'bg-emerald-500'],
        $score >= 60 => ['amber',   __('Needs attention'), 'bg-amber-500'],
        default      => ['red',     __('Poor UX'),         'bg-red-500'],
    };

    usort($contributors, fn ($a, $b) => $b['points'] <=> $a['points']);
    $topContributors = array_slice($contributors, 0, 2);
@endphp

@if ($hasSignalData)
    <x-ui.card size="full" class="border-l-4 border-l-{{ $tone }}-500">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4 sm:gap-6">
            {{-- Score circle --}}
            <div class="shrink-0 flex flex-col items-center">
                <div class="relative size-24 flex items-center justify-center rounded-full bg-{{ $tone }}-50 dark:bg-{{ $tone }}-950/30 border-2 border-{{ $tone }}-500">
                    <span class="text-3xl font-bold text-{{ $tone }}-600 dark:text-{{ $tone }}-400 tabular-nums">{{ $score }}</span>
                </div>
                <span class="mt-2 text-xs font-medium text-{{ $tone }}-600 dark:text-{{ $tone }}-400">{{ $label }}</span>
            </div>

            {{-- Description + contributors --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <x-ui.icon name="heart" class="size-4 text-{{ $tone }}-500" />
                    <x-ui.heading level="h3" size="sm">{{ __('UX Health Score') }}</x-ui.heading>
                </div>
                <x-ui.description class="mb-3">
                    {{ __('A 0–100 composite score derived from rage clicks, dead clicks, script errors and other friction signals. Higher is better.') }}
                </x-ui.description>

                @if (!empty($topContributors))
                    <div class="space-y-2">
                        <div class="text-xs uppercase tracking-wide text-neutral-500 dark:text-neutral-400">
                            {{ __('Top issues dragging the score down') }}
                        </div>
                        @foreach ($topContributors as $c)
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-2 min-w-0">
                                    <x-ui.icon name="warning" class="size-3.5 text-{{ $tone }}-500 shrink-0" />
                                    <span class="text-sm text-neutral-700 dark:text-neutral-300 truncate">{{ $c['label'] }}</span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-xs text-neutral-500 dark:text-neutral-400 tabular-nums">{{ number_format($c['pct'], 1) }}% {{ __('of sessions') }}</span>
                                    <div class="w-20 h-1.5 rounded-full bg-neutral-200 dark:bg-neutral-700 overflow-hidden">
                                        <div class="h-1.5 {{ $bar }}" style="width: {{ min($c['pct'], 100) }}%"></div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-ui.description>{{ __('No friction signals detected — clean session.') }}</x-ui.description>
                @endif
            </div>
        </div>
    </x-ui.card>
@endif
