<?php

use App\Modules\Analytics\GoogleAnalytics\Enums\GaDimension;
use App\Modules\Analytics\GoogleAnalytics\Enums\GaMetric;
use App\Modules\Analytics\GoogleAnalytics\Exceptions\GoogleAnalyticsException;
use App\Modules\Analytics\GoogleAnalytics\Queries\DateRange;
use App\Modules\Analytics\GoogleAnalytics\Queries\Filter;
use App\Modules\Analytics\GoogleAnalytics\Queries\ReportRequest;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsCache;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsQueryService;
use App\Modules\Projects\Models\Project;
use App\Modules\Users\Enums\PermissionEnum;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public string $rangePreset = 'last_7';
    public string $dateFrom = '';
    public string $dateTo = '';

    /** Either 'top' or 'landing'. */
    public string $tab = 'top';

    /** GA metric API name we are sorting by. Always descending. */
    public string $sortMetric = 'screenPageViews';

    /** Filter values — empty string means "no filter". */
    public string $deviceFilter = '';
    public string $channelFilter = '';

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('permission', [PermissionEnum::VIEW_GOOGLE_ANALYTICS, Project::current()]),
            403,
        );

        // Seed custom-mode inputs only — preset modes resolve in with().
        $today = now();
        $this->dateFrom = $today->copy()->subDays(6)->format('Y-m-d');
        $this->dateTo   = $today->format('Y-m-d');
    }

    #[On('current-project-changed')]
    public function onProjectChanged(): void { /* with() re-resolves on render */ }

    public function updatedTab(): void
    {
        // Top pages defaults to views, landing pages defaults to sessions.
        $this->sortMetric = $this->tab === 'landing' ? 'sessions' : 'screenPageViews';
    }

    public function setSort(string $metric): void
    {
        $this->sortMetric = $metric;
    }

    public function forceRefresh(): void
    {
        $project = Project::current();
        if ($project && $project->hasGoogleAnalytics()) {
            app(GoogleAnalyticsCache::class)->forgetForProperty($project->gaPropertyResource());
        }
    }

    /**
     * Resolve the active range every render. See ga-overview.blade.php for the
     * detailed reasoning — short version: computing in updated*() hooks races
     * with with() and serves stale data on the first paint after a preset change.
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
            $range  = $this->resolveRange();
            $svc    = app(GoogleAnalyticsQueryService::class);
            $filter = $this->buildFilter();

            // For top pages: sort by chosen metric. For landing pages: same.
            // We use runCustomReport so the user's sort choice is honored
            // without adding a parameter to every named method.
            if ($this->tab === 'landing') {
                $req = new ReportRequest(
                    dimensions: [GaDimension::LandingPage],
                    metrics:    [GaMetric::Sessions, GaMetric::EngagedSessions, GaMetric::BounceRate, GaMetric::Conversions],
                    dateRange:  $range,
                    orderBy:    [['type' => 'metric', 'name' => $this->sortMetric, 'desc' => true]],
                    limit:      50,
                    dimensionFilter: $filter,
                );
            } else {
                $req = new ReportRequest(
                    dimensions: [GaDimension::PagePath, GaDimension::PageTitle],
                    metrics:    [
                        GaMetric::ScreenPageViews,
                        GaMetric::Sessions,
                        GaMetric::TotalUsers,
                        GaMetric::EngagementRate,
                        GaMetric::UserEngagementDuration,
                    ],
                    dateRange:  $range,
                    orderBy:    [['type' => 'metric', 'name' => $this->sortMetric, 'desc' => true]],
                    limit:      50,
                    dimensionFilter: $filter,
                );
            }

            return [
                ...$base,
                ...$this->viewHelpers(),
                'range'         => $range,
                'rangeFrom'     => $range->startString(),
                'rangeTo'       => $range->endString(),
                'result'        => $svc->runCustomReport($project, $req),
                'filterActive'  => $filter !== null,
            ];
        } catch (GoogleAnalyticsException $e) {
            return [...$base, ...$this->viewHelpers(), 'error' => $e->getMessage()];
        }
    }

    /**
     * Composite filter from the deviceFilter + channelFilter form fields.
     * Returns null when neither is set, a single leaf when one is set,
     * or an AND group when both are set.
     */
    private function buildFilter(): ?Filter
    {
        $parts = [];
        if ($this->deviceFilter !== '') {
            $parts[] = Filter::equals('deviceCategory', $this->deviceFilter);
        }
        if ($this->channelFilter !== '') {
            $parts[] = Filter::equals('sessionDefaultChannelGroup', $this->channelFilter);
        }

        return match (count($parts)) {
            0       => null,
            1       => $parts[0],
            default => Filter::all(...$parts),
        };
    }

    private function viewHelpers(): array
    {
        return [
            'formatNumber'  => fn ($n) => number_format((float) $n, 0, '.', ' '),
            'formatPercent' => fn (float $v) => number_format($v * 100, 1) . '%',
            'formatDuration' => function ($seconds) {
                $s = (float) $seconds;
                $m = (int) floor($s / 60);
                $r = (int) round($s - $m * 60);
                return sprintf('%d:%02d', $m, $r);
            },
        ];
    }
};
?>

<div class="p-6 space-y-6">
    <div>
        <x-ui.heading level="h1" size="xl">{{ __('Google Analytics — Pages') }}</x-ui.heading>
        <x-ui.description class="mt-1">
            {{ __('Top viewed pages and landing pages with engagement metrics.') }}
        </x-ui.description>
    </div>

    @php
        $formatNumber   ??= fn ($n) => number_format((float) $n, 0, '.', ' ');
        $formatPercent  ??= fn (float $v) => number_format($v * 100, 1) . '%';
        $formatDuration ??= fn ($s) => sprintf('%d:%02d', (int) floor((float) $s / 60), (int) round((float) $s - floor((float) $s / 60) * 60));
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
                    <x-ui.text>{{ __('No Google Analytics property configured for this project.') }}</x-ui.text>
                    <x-ui.button :href="route('project.edit', $project)" variant="outline" icon="gear" class="mt-2">
                        {{ __('Configure in project settings') }}
                    </x-ui.button>
                </x-ui.empty.contents>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <x-ui.radio.group wire:model.live="tab" direction="horizontal" variant="segmented">
                <x-ui.radio.item value="top" :label="__('Top pages')" />
                <x-ui.radio.item value="landing" :label="__('Landing pages')" />
            </x-ui.radio.group>

            <div class="flex items-center gap-3 flex-wrap">
                <x-ui.radio.group wire:model.live="rangePreset" direction="horizontal" variant="segmented">
                    <x-ui.radio.item value="last_7" :label="__('7d')" />
                    <x-ui.radio.item value="last_28" :label="__('28d')" />
                    <x-ui.radio.item value="last_30" :label="__('30d')" />
                </x-ui.radio.group>
                <span class="text-xs text-neutral-500 dark:text-neutral-400 tabular-nums">
                    {{ $rangeFrom ?? $dateFrom }} → {{ $rangeTo ?? $dateTo }}
                </span>
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

        {{-- Filters row --}}
        <div class="flex items-center gap-3 flex-wrap">
            <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">
                {{ __('Filter') }}
            </span>
            <div class="flex items-center gap-2">
                <x-ui.label class="text-xs">{{ __('Device') }}</x-ui.label>
                <x-ui.select wire:model.live="deviceFilter" class="w-36">
                    <x-ui.select.option value="">{{ __('All') }}</x-ui.select.option>
                    <x-ui.select.option value="desktop">desktop</x-ui.select.option>
                    <x-ui.select.option value="mobile">mobile</x-ui.select.option>
                    <x-ui.select.option value="tablet">tablet</x-ui.select.option>
                </x-ui.select>
            </div>
            <div class="flex items-center gap-2">
                <x-ui.label class="text-xs">{{ __('Channel') }}</x-ui.label>
                <x-ui.select wire:model.live="channelFilter" class="w-44">
                    <x-ui.select.option value="">{{ __('All') }}</x-ui.select.option>
                    <x-ui.select.option value="Direct">Direct</x-ui.select.option>
                    <x-ui.select.option value="Organic Search">Organic Search</x-ui.select.option>
                    <x-ui.select.option value="Paid Search">Paid Search</x-ui.select.option>
                    <x-ui.select.option value="Paid Social">Paid Social</x-ui.select.option>
                    <x-ui.select.option value="Organic Social">Organic Social</x-ui.select.option>
                    <x-ui.select.option value="Email">Email</x-ui.select.option>
                    <x-ui.select.option value="Referral">Referral</x-ui.select.option>
                    <x-ui.select.option value="Cross-network">Cross-network</x-ui.select.option>
                </x-ui.select>
            </div>
            @if ($filterActive ?? false)
                <x-ui.button wire:click="$set('deviceFilter', ''); $set('channelFilter', '')" variant="outline" size="sm" icon="x">
                    {{ __('Clear filters') }}
                </x-ui.button>
            @endif
        </div>

        @if ($error)
            <x-ui.card>
                <x-ui.error :messages="[$error]" />
            </x-ui.card>
        @elseif ($result->isEmpty())
            <x-ui.card>
                <x-ui.empty>
                    <x-ui.empty.contents>
                        <x-ui.icon name="chart-bar" class="size-10 text-neutral-300 dark:text-neutral-600" />
                        <x-ui.text>{{ __('No page data for this range.') }}</x-ui.text>
                    </x-ui.empty.contents>
                </x-ui.empty>
            </x-ui.card>
        @else
            <x-ui.card size="full">
                <table class="w-full text-sm">
                    <thead class="text-xs text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">
                        @if ($tab === 'top')
                            <tr>
                                <th class="text-left py-2 font-medium">{{ __('Path') }} / {{ __('Title') }}</th>
                                <th class="text-right py-2 font-medium">
                                    <button type="button" wire:click="setSort('screenPageViews')"
                                        class="hover:text-neutral-900 dark:hover:text-neutral-100 {{ $sortMetric === 'screenPageViews' ? 'text-neutral-900 dark:text-neutral-100' : '' }}">
                                        {{ __('Views') }} {{ $sortMetric === 'screenPageViews' ? '↓' : '' }}
                                    </button>
                                </th>
                                <th class="text-right py-2 font-medium">
                                    <button type="button" wire:click="setSort('sessions')"
                                        class="hover:text-neutral-900 dark:hover:text-neutral-100 {{ $sortMetric === 'sessions' ? 'text-neutral-900 dark:text-neutral-100' : '' }}">
                                        {{ __('Sessions') }} {{ $sortMetric === 'sessions' ? '↓' : '' }}
                                    </button>
                                </th>
                                <th class="text-right py-2 font-medium">
                                    <button type="button" wire:click="setSort('totalUsers')"
                                        class="hover:text-neutral-900 dark:hover:text-neutral-100 {{ $sortMetric === 'totalUsers' ? 'text-neutral-900 dark:text-neutral-100' : '' }}">
                                        {{ __('Users') }} {{ $sortMetric === 'totalUsers' ? '↓' : '' }}
                                    </button>
                                </th>
                                <th class="text-right py-2 font-medium">
                                    <button type="button" wire:click="setSort('engagementRate')"
                                        class="hover:text-neutral-900 dark:hover:text-neutral-100 {{ $sortMetric === 'engagementRate' ? 'text-neutral-900 dark:text-neutral-100' : '' }}">
                                        {{ __('Engagement') }} {{ $sortMetric === 'engagementRate' ? '↓' : '' }}
                                    </button>
                                </th>
                                <th class="text-right py-2 font-medium" :title="__('Total time users actively engaged with this page (sum across users).')">
                                    <button type="button" wire:click="setSort('userEngagementDuration')"
                                        class="hover:text-neutral-900 dark:hover:text-neutral-100 {{ $sortMetric === 'userEngagementDuration' ? 'text-neutral-900 dark:text-neutral-100' : '' }}">
                                        {{ __('Time on page') }} {{ $sortMetric === 'userEngagementDuration' ? '↓' : '' }}
                                    </button>
                                </th>
                            </tr>
                        @else
                            <tr>
                                <th class="text-left py-2 font-medium">{{ __('Landing page') }}</th>
                                <th class="text-right py-2 font-medium">
                                    <button type="button" wire:click="setSort('sessions')"
                                        class="hover:text-neutral-900 dark:hover:text-neutral-100 {{ $sortMetric === 'sessions' ? 'text-neutral-900 dark:text-neutral-100' : '' }}">
                                        {{ __('Sessions') }} {{ $sortMetric === 'sessions' ? '↓' : '' }}
                                    </button>
                                </th>
                                <th class="text-right py-2 font-medium">
                                    <button type="button" wire:click="setSort('engagedSessions')"
                                        class="hover:text-neutral-900 dark:hover:text-neutral-100 {{ $sortMetric === 'engagedSessions' ? 'text-neutral-900 dark:text-neutral-100' : '' }}">
                                        {{ __('Engaged') }} {{ $sortMetric === 'engagedSessions' ? '↓' : '' }}
                                    </button>
                                </th>
                                <th class="text-right py-2 font-medium">
                                    <button type="button" wire:click="setSort('bounceRate')"
                                        class="hover:text-neutral-900 dark:hover:text-neutral-100 {{ $sortMetric === 'bounceRate' ? 'text-neutral-900 dark:text-neutral-100' : '' }}">
                                        {{ __('Bounce') }} {{ $sortMetric === 'bounceRate' ? '↓' : '' }}
                                    </button>
                                </th>
                                <th class="text-right py-2 font-medium">
                                    <button type="button" wire:click="setSort('conversions')"
                                        class="hover:text-neutral-900 dark:hover:text-neutral-100 {{ $sortMetric === 'conversions' ? 'text-neutral-900 dark:text-neutral-100' : '' }}">
                                        {{ __('Conversions') }} {{ $sortMetric === 'conversions' ? '↓' : '' }}
                                    </button>
                                </th>
                            </tr>
                        @endif
                    </thead>
                    <tbody class="divide-y divide-black/5 dark:divide-white/5">
                        @foreach ($result->rows() as $row)
                            @if ($tab === 'top')
                                <tr>
                                    <td class="py-2 max-w-md">
                                        <div class="font-mono text-xs text-neutral-700 dark:text-neutral-300 truncate">
                                            {{ $row->dimension('pagePath') }}
                                        </div>
                                        <div class="text-xs text-neutral-500 dark:text-neutral-400 truncate">
                                            {{ $row->dimension('pageTitle') }}
                                        </div>
                                    </td>
                                    <td class="py-2 text-right tabular-nums">{{ $formatNumber($row->metric('screenPageViews')) }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $formatNumber($row->metric('sessions')) }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $formatNumber($row->metric('totalUsers')) }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $formatPercent((float) $row->metric('engagementRate')) }}</td>
                                    <td class="py-2 text-right tabular-nums">
                                        @php
                                            // Average per-user engagement time on this page = total / users.
                                            $totalSec = (float) $row->metric('userEngagementDuration');
                                            $users    = (float) $row->metric('totalUsers');
                                            $avgSec   = $users > 0 ? $totalSec / $users : 0;
                                        @endphp
                                        {{ $formatDuration($avgSec) }}
                                    </td>
                                </tr>
                            @else
                                <tr>
                                    <td class="py-2 max-w-md">
                                        <div class="font-mono text-xs text-neutral-700 dark:text-neutral-300 truncate">
                                            {{ $row->dimension('landingPage') }}
                                        </div>
                                    </td>
                                    <td class="py-2 text-right tabular-nums">{{ $formatNumber($row->metric('sessions')) }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $formatNumber($row->metric('engagedSessions')) }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $formatPercent((float) $row->metric('bounceRate')) }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ $formatNumber($row->metric('conversions')) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
                <div class="text-xs text-neutral-400 dark:text-neutral-500 mt-3">
                    {{ __('Showing :n of :t rows', ['n' => $result->count(), 't' => $result->totalRowCount()]) }}
                    · {{ __('Fetched at') }} {{ $result->fetchedAt->setTimezone(config('app.timezone'))->format('Y-m-d H:i') }}
                </div>
            </x-ui.card>
        @endif
    @endif
</div>
