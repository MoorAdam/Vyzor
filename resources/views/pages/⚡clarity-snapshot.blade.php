<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Models\ClarityInsight;

new #[Layout('layouts.app')] class extends Component {

    public ?string $error = null;
    public string $datetime = '';

    public function mount(): void
    {
        $this->datetime = now()->startOfHour()->format('Y-m-d\TH:i');
    }

    #[On('current-project-changed')]
    public function onProjectChanged()
    {
        // re-render when project changes
    }

    #[On('clarity-fetched')]
    public function onClarityFetched(): void
    {
        // snap the selector to "now" so the newly fetched data is shown
        $this->datetime = now()->startOfHour()->format('Y-m-d\TH:i');
    }

    public function with(): array
    {
        $projectId = session('current_project_id');

        if (!$projectId) {
            $insights = collect();
        } else {
            $selectedTime = \Carbon\Carbon::parse($this->datetime)->startOfHour();

            // Find the fetch closest to the selected datetime
            $closestFetch = ClarityInsight::where('project_id', $projectId)
                ->orderByRaw('ABS(EXTRACT(EPOCH FROM (fetched_for - ?)))', [$selectedTime])
                ->value('fetched_for');

            $insights = $closestFetch
                ? ClarityInsight::where('project_id', $projectId)
                    ->where('fetched_for', $closestFetch)
                    ->get()
                    ->keyBy('metric_name')
                : collect();
        }

        return ['insights' => $insights];
    }
};
?>

<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <x-ui.heading level="h1" size="xl">{{ __('Clarity Snapshot') }}</x-ui.heading>
            <x-ui.description class="mt-1">{{ __('Single point-in-time Microsoft Clarity data for the current project.') }}</x-ui.description>
        </div>
        <div class="flex items-center gap-3">
            <x-ui.input type="datetime-local" wire:model.live="datetime" class="w-60" />
            <livewire:clarity-fetch-button />
        </div>
    </div>

    @if ($error)
        <x-ui.card>
            <x-ui.error :messages="[$error]" />
        </x-ui.card>
    @endif

    @if ($insights->isEmpty())
        <x-ui.card>
            <x-ui.empty>
                <x-ui.empty.contents>
                    <x-ui.icon name="chart-bar" class="size-10 text-neutral-300 dark:text-neutral-600" />
                    <x-ui.text>{{ __('No data available for') }} {{ \Carbon\Carbon::parse($datetime)->format('M d, Y H:i') }}.</x-ui.text>
                </x-ui.empty.contents>
            </x-ui.empty>
        </x-ui.card>
    @else

        <x-ui.heading level="h3" size="md" class="mb-3">{{ __('Overview') }}</x-ui.heading>

        {{-- Overview Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-4">
            @if ($traffic = $insights->get('Traffic'))
                @php $t = $traffic->data[0] ?? []; @endphp
                <x-ui.card size="full" class="border-l-4 border-l-blue-500">
                    <div class="flex items-center gap-2 mb-1">
                        <x-ui.icon name="users" class="size-4 text-blue-500" />
                        <x-ui.description class="uppercase tracking-wide text-xs!">{{ __('Sessions') }}</x-ui.description>
                    </div>
                    <x-ui.heading level="h3" size="xl">{{ number_format($t['totalSessionCount'] ?? 0) }}</x-ui.heading>
                    <x-ui.description class="text-xs! mt-1">{{ number_format($t['totalBotSessionCount'] ?? 0) }} {{ __('bots') }}</x-ui.description>
                </x-ui.card>
                <x-ui.card size="full" class="border-l-4 border-l-violet-500">
                    <div class="flex items-center gap-2 mb-1">
                        <x-ui.icon name="user" class="size-4 text-violet-500" />
                        <x-ui.description class="uppercase tracking-wide text-xs!">{{ __('Unique Users') }}</x-ui.description>
                    </div>
                    <x-ui.heading level="h3" size="xl">{{ number_format($t['distinctUserCount'] ?? 0) }}</x-ui.heading>
                </x-ui.card>
                <x-ui.card size="full" class="border-l-4 border-l-emerald-500">
                    <div class="flex items-center gap-2 mb-1">
                        <x-ui.icon name="copy" class="size-4 text-emerald-500" />
                        <x-ui.description class="uppercase tracking-wide text-xs!">{{ __('Pages / Session') }}</x-ui.description>
                    </div>
                    <x-ui.heading level="h3" size="xl">{{ number_format($t['pagesPerSessionPercentage'] ?? 0, 2) }}</x-ui.heading>
                </x-ui.card>
            @endif

            @if ($scroll = $insights->get('ScrollDepth'))
                @php $s = $scroll->data[0] ?? []; @endphp
                <x-ui.card size="full" class="border-l-4 border-l-amber-500">
                    <div class="flex items-center gap-2 mb-1">
                        <x-ui.icon name="arrows-down-up" class="size-4 text-amber-500" />
                        <x-ui.description class="uppercase tracking-wide text-xs!">{{ __('Avg Scroll Depth') }}</x-ui.description>
                    </div>
                    <x-ui.heading level="h3" size="xl">{{ number_format($s['averageScrollDepth'] ?? 0, 1) }}%</x-ui.heading>
                </x-ui.card>
            @endif
        </div>

        <x-ui.heading level="h3" size="md" class="mt-6 mb-3">{{ __('User Engagement') }}</x-ui.heading>

        {{-- Engagement --}}
        @if ($engagement = $insights->get('EngagementTime'))
            @php $e = $engagement->data[0] ?? []; @endphp
            <div class="grid grid-cols-2 lg:grid-cols-10 gap-4">
                <x-ui.card size="full" class="border-l-4 border-l-cyan-500">
                    <div class="flex items-center gap-2 mb-1">
                        <x-ui.icon name="clock" class="size-4 text-cyan-500" />
                        <x-ui.description class="uppercase tracking-wide text-xs!">{{ __('Total Time') }}</x-ui.description>
                    </div>
                    <x-ui.heading level="h3" size="xl">{{ $e['totalTime'] ?? 0 }}s</x-ui.heading>
                </x-ui.card>
                <x-ui.card size="full" class="border-l-4 border-l-teal-500">
                    <div class="flex items-center gap-2 mb-1">
                        <x-ui.icon name="timer" class="size-4 text-teal-500" />
                        <x-ui.description class="uppercase tracking-wide text-xs!">{{ __('Active Time') }}</x-ui.description>
                    </div>
                    <x-ui.heading level="h3" size="xl">{{ $e['activeTime'] ?? 0 }}s</x-ui.heading>
                </x-ui.card>
            </div>
        @endif

        {{-- UX Signals --}}
        @php
            $signals = [
                'DeadClickCount'   => ['label' => __('Dead Clicks'),      'color' => 'orange', 'icon' => 'cursor-click'],
                'RageClickCount'   => ['label' => __('Rage Clicks'),      'color' => 'red',    'icon' => 'warning'],
                'QuickbackClick'   => ['label' => __('Quick Backs'),      'color' => 'amber',  'icon' => 'arrow-u-up-left'],
                'ExcessiveScroll'  => ['label' => __('Excessive Scroll'), 'color' => 'yellow', 'icon' => 'arrows-down-up'],
                'ScriptErrorCount' => ['label' => __('Script Errors'),    'color' => 'rose',   'icon' => 'code'],
                'ErrorClickCount'  => ['label' => __('Error Clicks'),     'color' => 'pink',   'icon' => 'x-circle'],
            ];
            $hasSignals = $insights->keys()->intersect(array_keys($signals))->isNotEmpty();
        @endphp

        @if ($hasSignals)
            <div>
                <x-ui.heading level="h2" size="md" class="mb-3">{{ __('UX Signals') }}</x-ui.heading>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    @foreach ($signals as $key => $config)
                        @if ($metric = $insights->get($key))
                            @php
                                $d = $metric->data[0] ?? [];
                                $pct = $d['sessionsWithMetricPercentage'] ?? 0;
                            @endphp
                            <x-ui.card size="full" class="border-t-4 border-t-{{ $config['color'] }}-500">
                                <div class="flex items-center gap-2 mb-1">
                                    <x-ui.icon name="{{ $config['icon'] }}" class="size-4 text-{{ $config['color'] }}-500" />
                                    <x-ui.description class="uppercase tracking-wide text-xs!">{{ $config['label'] }}</x-ui.description>
                                </div>
                                <x-ui.heading level="h4" size="xl" class="{{ ($d['subTotal'] ?? 0) > 0 ? 'text-' . $config['color'] . '-600 dark:text-' . $config['color'] . '-400' : '' }}">{{ $d['subTotal'] ?? 0 }}</x-ui.heading>
                                <div class="mt-2">
                                    <div class="w-full h-1.5 rounded-full bg-neutral-200 dark:bg-neutral-700">
                                        <div class="h-1.5 rounded-full bg-{{ $config['color'] }}-500" style="width: {{ min($pct, 100) }}%"></div>
                                    </div>
                                    <x-ui.description class="text-xs! mt-1">{{ $pct }}% {{ __('of sessions') }}</x-ui.description>
                                </div>
                            </x-ui.card>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Breakdowns --}}
        @php
            $dimensions = [
                'Browser'     => ['label' => __('Browsers'),          'icon' => 'globe',         'color' => 'blue'],
                'Device'      => ['label' => __('Devices'),           'icon' => 'device-mobile', 'color' => 'violet'],
                'OS'          => ['label' => __('Operating Systems'), 'icon' => 'desktop',       'color' => 'emerald'],
                'Country'     => ['label' => __('Countries'),         'icon' => 'map-pin',       'color' => 'amber'],
                'ReferrerUrl' => ['label' => __('Referrers'),         'icon' => 'link',           'color' => 'cyan'],
            ];
            $pageTables = [
                'PopularPages' => ['label' => __('Popular Pages'), 'icon' => 'chart-bar', 'color' => 'blue',   'col' => __('URL'),   'metric' => __('Visits')],
                'PageTitle'    => ['label' => __('Page Titles'),   'icon' => 'text-aa',   'color' => 'violet', 'col' => __('Title'), 'metric' => __('Sessions')],
            ];
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($dimensions as $dim => $config)
                @if ($metric = $insights->get($dim))
                    @php $scrollable = count($metric->data) > 20; @endphp
                    <x-ui.card size="full" @class(['flex flex-col', 'max-h-200' => $scrollable])>
                        <div class="flex items-center gap-2 mb-3 shrink-0">
                            <x-ui.icon name="{{ $config['icon'] }}" class="size-5 text-{{ $config['color'] }}-500" />
                            <x-ui.heading level="h3" size="sm">{{ $config['label'] }}</x-ui.heading>
                        </div>
                        <div @class(['space-y-2', 'overflow-y-auto' => $scrollable])>
                            @foreach ($metric->data as $row)
                                @php
                                    $count = (int) ($row['sessionsCount'] ?? 0);
                                    $total = (int) ($insights->get('Traffic')?->data[0]['totalSessionCount'] ?? 1);
                                    $pct = $total > 0 ? round(($count / $total) * 100, 1) : 0;
                                @endphp
                                <div>
                                    <div class="flex justify-between text-sm mb-0.5">
                                        <span class="text-neutral-700 dark:text-neutral-300 truncate">{{ $row['name'] ?? '—' }}</span>
                                        <span class="text-neutral-500 dark:text-neutral-400 shrink-0 ml-2">{{ number_format($count) }}</span>
                                    </div>
                                    <div class="w-full h-1.5 rounded-full bg-neutral-200 dark:bg-neutral-700">
                                        <div class="h-1.5 rounded-full bg-{{ $config['color'] }}-500" style="width: {{ min($pct, 100) }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.card>
                @endif
            @endforeach

            @foreach ($pageTables as $pt => $config)
                @if ($metric = $insights->get($pt))
                    @php $scrollable = count($metric->data) > 20; @endphp
                    <x-ui.card size="full" @class(['overflow-hidden p-0! flex flex-col', 'max-h-200' => $scrollable])>
                        <div class="flex items-center gap-2 px-4 py-3 border-b border-neutral-200 dark:border-neutral-800 bg-neutral-50 dark:bg-neutral-900/50 shrink-0">
                            <x-ui.icon name="{{ $config['icon'] }}" class="size-5 text-{{ $config['color'] }}-500" />
                            <x-ui.heading level="h3" size="sm">{{ $config['label'] }}</x-ui.heading>
                        </div>
                        <div @class([$scrollable ? 'overflow-y-auto' : ''])>
                        <table class="w-full text-sm text-left table-fixed">
                            <colgroup>
                                <col />
                                <col class="w-24" />
                            </colgroup>
                            <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                                @foreach ($metric->data as $row)
                                    <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-900/30 transition-colors">
                                        <td class="px-4 py-2.5 text-neutral-700 dark:text-neutral-300">
                                            <div class="overflow-hidden text-ellipsis whitespace-nowrap" title="{{ $row['url'] ?? $row['name'] ?? '' }}">
                                                @if ($pt === 'PopularPages')
                                                    <x-ui.link href="{{ $row['url'] ?? '#' }}" openInNewTab :primary="false" class="text-sm!">{{ $row['url'] ?? '—' }}</x-ui.link>
                                                @else
                                                    {{ $row['name'] ?? '—' }}
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            <x-ui.badge size="sm" color="{{ $config['color'] }}" variant="outline">{{ number_format($row['visitsCount'] ?? $row['sessionsCount'] ?? 0) }}</x-ui.badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    </x-ui.card>
                @endif
            @endforeach
        </div>
    @endif
</div>
