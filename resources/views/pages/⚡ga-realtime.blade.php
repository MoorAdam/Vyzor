<?php

use App\Modules\Analytics\GoogleAnalytics\Exceptions\GoogleAnalyticsException;
use App\Modules\Analytics\GoogleAnalytics\Services\GoogleAnalyticsQueryService;
use App\Modules\Projects\Models\Project;
use App\Modules\Users\Enums\PermissionEnum;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('permission', [PermissionEnum::VIEW_GOOGLE_ANALYTICS, Project::current()]),
            403,
        );
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
            $svc = app(GoogleAnalyticsQueryService::class);
            return [
                ...$base,
                'formatNumber' => fn ($n) => number_format((float) $n, 0, '.', ' '),
                'snapshot' => $svc->getRealtimeUsers($project),
            ];
        } catch (GoogleAnalyticsException $e) {
            return [
                ...$base,
                'formatNumber' => fn ($n) => number_format((float) $n, 0, '.', ' '),
                'error' => $e->getMessage(),
            ];
        }
    }
};
?>

{{-- wire:poll.30s matches the realtime cache TTL — frequent enough to feel "live"
     without burning realtime API quota every few seconds. --}}
<div class="p-6 space-y-6" wire:poll.30s>
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
