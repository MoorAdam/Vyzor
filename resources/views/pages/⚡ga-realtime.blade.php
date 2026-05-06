<?php

use App\Modules\Analytics\GoogleAnalytics\Exceptions\GoogleAnalyticsException;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsQueryService;
use App\Modules\Projects\Models\Project;
use App\Modules\Users\Enums\PermissionEnum;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    /**
     * Gates the GA fetch in with(). Initial render keeps this false so the
     * page paints instantly with a skeleton; wire:init flips it on the first
     * AJAX round-trip, after which every subsequent render (including the
     * 30-second poll) fetches normally.
     */
    public bool $loaded = false;

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('permission', [PermissionEnum::VIEW_GOOGLE_ANALYTICS, Project::current()]),
            403,
        );
    }

    public function loadData(): void
    {
        $this->loaded = true;
    }

    public function with(): array
    {
        $project = Project::current();
        $base = [
            'project'    => $project,
            'configured' => $project?->hasGoogleAnalytics() ?? false,
            'error'      => null,
            'formatNumber' => fn ($n) => number_format((float) $n, 0, '.', ' '),
        ];

        if (!$project || !$project->hasGoogleAnalytics()) {
            return $base;
        }

        // Skeleton-first: skip the realtime API call on the initial paint.
        // wire:init flips $loaded=true; subsequent polls keep $loaded=true so
        // they refresh data normally without flashing the skeleton.
        if (!$this->loaded) {
            return $base;
        }

        try {
            $svc = app(GoogleAnalyticsQueryService::class);
            return [
                ...$base,
                'snapshot' => $svc->getRealtimeUsers($project),
            ];
        } catch (GoogleAnalyticsException $e) {
            return [
                ...$base,
                'error' => $e->getMessage(),
            ];
        }
    }
};
?>

{{-- wire:poll.30s matches the realtime cache TTL — frequent enough to feel "live"
     without burning realtime API quota every few seconds. wire:init defers the
     first realtime fetch off the initial paint so the page shows a skeleton
     immediately and fills in once the API responds. --}}
<div class="p-6 space-y-6" wire:poll.30s wire:init="loadData">
    <div>
        <x-ui.heading level="h1" size="xl">{{ __('Google Analytics — Realtime') }}</x-ui.heading>
        <x-ui.description class="mt-1">
            {{ __('Active users in the last 30 minutes. Auto-refreshes every 30 seconds.') }}
        </x-ui.description>
    </div>

    @php
        $formatNumber ??= fn ($n) => number_format((float) $n, 0, '.', ' ');
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
    @elseif ($error)
        <x-ui.card>
            <x-ui.error :messages="[$error]" />
        </x-ui.card>
    @elseif (!$loaded)
        {{-- Skeleton: mirrors the active-users counter and the 3-column breakdown.
             wire:init triggers loadData() right after hydration; on the next
             render this branch is replaced with the real realtime snapshot. --}}
        <div class="animate-pulse space-y-6" aria-busy="true" aria-label="{{ __('Loading realtime data') }}">
            <x-ui.card size="full">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div class="space-y-2">
                        <div class="h-3 w-24 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                        <div class="h-12 w-20 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                    </div>
                    <div class="h-3 w-40 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                </div>
            </x-ui.card>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @for ($c = 0; $c < 3; $c++)
                    <x-ui.card size="full">
                        <div class="h-5 w-28 bg-neutral-200 dark:bg-neutral-700 rounded mb-4"></div>
                        <ul class="space-y-2.5">
                            @for ($r = 0; $r < 5; $r++)
                                <li class="flex items-center justify-between gap-3">
                                    <div class="h-3 w-2/3 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                                    <div class="h-3 w-8 bg-neutral-200 dark:bg-neutral-700 rounded"></div>
                                </li>
                            @endfor
                        </ul>
                    </x-ui.card>
                @endfor
            </div>
        </div>
    @else
        {{-- Big counter --}}
        <x-ui.card size="full">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">
                        {{ __('Active users') }}
                    </span>
                    <div class="text-5xl font-semibold tabular-nums text-neutral-900 dark:text-neutral-100 mt-1">
                        {{ $formatNumber($snapshot->activeUsers) }}
                    </div>
                </div>
                <div class="flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                    <x-ui.icon name="pulse" class="size-4 text-emerald-500" />
                    <span>{{ __('last 30 min') }}</span>
                    <span class="text-neutral-300 dark:text-neutral-600">·</span>
                    <span>{{ __('updated') }} {{ $snapshot->fetchedAt->setTimezone(config('app.timezone'))->format('H:i:s') }}</span>
                </div>
            </div>
        </x-ui.card>

        @if ($snapshot->activeUsers === 0)
            <x-ui.card>
                <x-ui.empty>
                    <x-ui.empty.contents>
                        <x-ui.icon name="moon" class="size-10 text-neutral-300 dark:text-neutral-600" />
                        <x-ui.text>{{ __('No active users right now.') }}</x-ui.text>
                    </x-ui.empty.contents>
                </x-ui.empty>
            </x-ui.card>
        @else
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- By country --}}
                <x-ui.card size="full">
                    <x-ui.heading level="h2" size="md" class="mb-3">
                        <x-ui.icon name="globe" class="size-4 inline mr-1 text-neutral-400" />
                        {{ __('By country') }}
                    </x-ui.heading>
                    @if (count($snapshot->byCountry) === 0)
                        <x-ui.text class="text-neutral-500">—</x-ui.text>
                    @else
                        <ul class="space-y-1.5 text-sm">
                            @foreach ($snapshot->byCountry as $row)
                                <li class="flex items-center justify-between">
                                    <span class="truncate">{{ $row['label'] ?: __('(unknown)') }}</span>
                                    <span class="tabular-nums text-neutral-700 dark:text-neutral-300 font-medium">
                                        {{ $formatNumber($row['activeUsers']) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>

                {{-- By device --}}
                <x-ui.card size="full">
                    <x-ui.heading level="h2" size="md" class="mb-3">
                        <x-ui.icon name="device-mobile" class="size-4 inline mr-1 text-neutral-400" />
                        {{ __('By device') }}
                    </x-ui.heading>
                    @if (count($snapshot->byDevice) === 0)
                        <x-ui.text class="text-neutral-500">—</x-ui.text>
                    @else
                        <ul class="space-y-1.5 text-sm">
                            @foreach ($snapshot->byDevice as $row)
                                <li class="flex items-center justify-between">
                                    <span class="capitalize">{{ $row['label'] ?: __('(unknown)') }}</span>
                                    <span class="tabular-nums text-neutral-700 dark:text-neutral-300 font-medium">
                                        {{ $formatNumber($row['activeUsers']) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>

                {{-- By page --}}
                <x-ui.card size="full">
                    <x-ui.heading level="h2" size="md" class="mb-3">
                        <x-ui.icon name="browser" class="size-4 inline mr-1 text-neutral-400" />
                        {{ __('By page') }}
                    </x-ui.heading>
                    @if (count($snapshot->byPage) === 0)
                        <x-ui.text class="text-neutral-500">—</x-ui.text>
                    @else
                        <ul class="space-y-1.5 text-sm">
                            @foreach ($snapshot->byPage as $row)
                                <li class="flex items-center justify-between gap-3">
                                    <span class="truncate text-xs text-neutral-700 dark:text-neutral-300">
                                        {{ $row['label'] ?: __('(unknown)') }}
                                    </span>
                                    <span class="tabular-nums text-neutral-700 dark:text-neutral-300 font-medium shrink-0">
                                        {{ $formatNumber($row['activeUsers']) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-ui.card>
            </div>
        @endif
    @endif
</div>
