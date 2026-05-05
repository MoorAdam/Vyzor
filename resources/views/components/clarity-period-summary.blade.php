@props([
    'insights' => null,
    'dateFrom' => null,
    'dateTo' => null,
    'daysWithData' => 0,
    'totalDays' => 0,
])

@php
    $insights = $insights ?? collect();
    $hasData = $insights->isNotEmpty();
    $coverage = $totalDays > 0 ? round(($daysWithData / $totalDays) * 100) : 0;
@endphp

@if (!$hasData)
    <x-ui.card>
        <x-ui.empty>
            <x-ui.empty.contents>
                <x-ui.icon name="chart-bar" class="size-10 text-neutral-300 dark:text-neutral-600" />
                <x-ui.text>
                    {{ __('No Clarity data available between :from and :to.', [
                        'from' => $dateFrom?->format('M d, Y') ?? '?',
                        'to' => $dateTo?->format('M d, Y') ?? '?',
                    ]) }}
                </x-ui.text>
            </x-ui.empty.contents>
        </x-ui.empty>
    </x-ui.card>
@else
    {{-- Coverage banner — flags partial coverage so the user knows the SUMs aren't apples-to-apples with full periods. --}}
    @if ($totalDays > 0)
        @php
            [$coverageTone, $coverageIcon] = match (true) {
                $coverage >= 100 => ['emerald', 'check-circle'],
                $coverage >= 70  => ['amber',   'clock'],
                default          => ['red',     'warning'],
            };
        @endphp
        <div class="flex items-center justify-between gap-x-3 gap-y-1 flex-wrap p-3 rounded-box border border-{{ $coverageTone }}-200 dark:border-{{ $coverageTone }}-900/50 bg-{{ $coverageTone }}-50 dark:bg-{{ $coverageTone }}-950/20">
            <div class="flex items-center gap-2 min-w-0">
                <x-ui.icon name="{{ $coverageIcon }}" class="size-4 text-{{ $coverageTone }}-600 dark:text-{{ $coverageTone }}-400 shrink-0" />
                <span class="text-sm text-{{ $coverageTone }}-700 dark:text-{{ $coverageTone }}-300">
                    {{ __('Aggregated from :got of :total days in range (:pct% coverage)', [
                        'got' => $daysWithData,
                        'total' => $totalDays,
                        'pct' => $coverage,
                    ]) }}
                </span>
            </div>
            <span class="text-xs text-{{ $coverageTone }}-600 dark:text-{{ $coverageTone }}-400 tabular-nums whitespace-nowrap">
                {{ $dateFrom?->format('M d') }} → {{ $dateTo?->format('M d, Y') }}
            </span>
        </div>
    @endif

    <x-clarity-ux-score :insights="$insights" />

    <x-clarity-metrics :insights="$insights" />
@endif
