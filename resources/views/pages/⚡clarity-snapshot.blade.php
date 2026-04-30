<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Modules\Analytics\Clarity\Models\ClarityInsight;
use App\Modules\Users\Enums\PermissionEnum;

new #[Layout('layouts.app')] class extends Component {

    public ?string $error = null;
    public ?string $fetchKey = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('permission', [PermissionEnum::VIEW_CLARITY_SNAPSHOTS, \App\Modules\Projects\Models\Project::current()]), 403);
        $this->fetchKey = $this->defaultFetchKey();
    }

    #[On('current-project-changed')]
    public function onProjectChanged(): void
    {
        $this->fetchKey = $this->defaultFetchKey();
    }

    #[On('clarity-fetched')]
    public function onClarityFetched(): void
    {
        $this->fetchKey = $this->defaultFetchKey();
    }

    public function selectFetch(string $key): void
    {
        $this->fetchKey = $key;
    }

    private function defaultFetchKey(): ?string
    {
        $projectId = session('current_project_id');
        if (!$projectId) return null;

        $latest = ClarityInsight::where('project_id', $projectId)
            ->orderByDesc('fetched_for')
            ->first(['fetched_for', 'date_from', 'date_to']);

        return $latest ? $this->keyFor($latest) : null;
    }

    private function keyFor($row): string
    {
        return $row->fetched_for->toIso8601String() . '|' . $row->date_from->toDateString() . '|' . $row->date_to->toDateString();
    }

    public function with(): array
    {
        $projectId = session('current_project_id');

        if (!$projectId) {
            return [
                'insights' => collect(),
                'fetches' => collect(),
                'selectedFetch' => null,
                'hasTodayData' => false,
                'latestDate' => null,
            ];
        }

        $fetches = ClarityInsight::where('project_id', $projectId)
            ->whereNotNull('fetched_for')
            ->select('fetched_for', 'date_from', 'date_to')
            ->orderByDesc('fetched_for')
            ->limit(120)
            ->get()
            ->unique(fn ($r) => $this->keyFor($r))
            ->values();

        $selectedFetch = $this->fetchKey
            ? $fetches->first(fn ($f) => $this->keyFor($f) === $this->fetchKey)
            : null;
        if (!$selectedFetch && $fetches->isNotEmpty()) {
            $selectedFetch = $fetches->first();
            $this->fetchKey = $this->keyFor($selectedFetch);
        }

        $insights = $selectedFetch
            ? ClarityInsight::where('project_id', $projectId)
                ->where('fetched_for', $selectedFetch->fetched_for)
                ->where('date_from', $selectedFetch->date_from)
                ->where('date_to', $selectedFetch->date_to)
                ->get()
                ->keyBy('metric_name')
            : collect();

        $today = now()->toDateString();
        $hasTodayData = $fetches->contains(
            fn ($f) => $f->date_from->toDateString() <= $today && $f->date_to->toDateString() >= $today
        );
        $latestDate = $fetches->isNotEmpty()
            ? $fetches->max(fn ($f) => $f->date_to->toDateString())
            : null;

        return [
            'insights' => $insights,
            'fetches' => $fetches,
            'selectedFetch' => $selectedFetch,
            'hasTodayData' => $hasTodayData,
            'latestDate' => $latestDate,
        ];
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
            @if (!$hasTodayData && $latestDate)
                <div class="flex items-center gap-1.5 text-xs font-medium text-red-600 dark:text-red-400">
                    <x-ui.icon name="warning" class="size-4 shrink-0" />
                    <span class="whitespace-nowrap">
                        {{ __('No data for today (:date)', ['date' => \Carbon\Carbon::now()->format('M d, Y')]) }}
                    </span>
                </div>
            @endif
            <div x-data="{ open: false }" class="relative">
                <button
                    type="button"
                    x-ref="snapshotButton"
                    x-on:click="open = !open"
                    class="inline-flex items-center gap-2 border p-2 w-72 text-sm bg-white dark:bg-neutral-900 rounded-box shadow-xs border-black/10 dark:border-white/15 text-neutral-800 dark:text-neutral-300 transition-colors duration-200"
                >
                    <x-ui.icon name="clock" class="size-4 text-neutral-400 shrink-0" />
                    @if ($selectedFetch)
                        <span class="truncate flex-1 text-left">
                            {{ $selectedFetch->date_from->format('M d') }} → {{ $selectedFetch->date_to->format('M d') }}
                            <span class="text-neutral-500 dark:text-neutral-400">· {{ $selectedFetch->fetched_for->format('M d, H:i') }}</span>
                        </span>
                    @else
                        <span class="text-neutral-400 flex-1 text-left">{{ __('No snapshots yet') }}</span>
                    @endif
                    <x-ui.icon name="caret-down" class="size-4 text-neutral-400 shrink-0" />
                </button>

                <div
                    x-show="open"
                    x-on:click.away="open = false"
                    x-on:keydown.escape.window="open = false"
                    x-anchor.bottom-end.offset.6="$refs.snapshotButton"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    style="display: none;"
                    class="absolute z-50 w-80 bg-white dark:bg-neutral-900 border border-black/10 dark:border-white/10 rounded-box shadow-lg p-2"
                >
                    <x-clarity-fetch-list
                        :fetches="$fetches"
                        selectable
                        :selected="$fetchKey"
                        wireClick="selectFetch"
                        :emptyMessage="__('No snapshots yet — fetch one to get started.')"
                    />
                </div>
            </div>
            <x-ui.separator class="my-1" vertical />
            <livewire:clarity-fetch-button />
        </div>
    </div>

    <x-clarity-key-required />

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
                    <x-ui.text>
                        @if ($selectedFetch)
                            {{ __('No data available for') }} {{ $selectedFetch->date_from->format('M d') }} → {{ $selectedFetch->date_to->format('M d') }}.
                        @else
                            {{ __('No snapshots yet — fetch Clarity data to get started.') }}
                        @endif
                    </x-ui.text>
                </x-ui.empty.contents>
            </x-ui.empty>
        </x-ui.card>
    @else
        <x-clarity-metrics :insights="$insights" />
    @endif
</div>
