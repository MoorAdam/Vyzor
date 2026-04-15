<?php

use Livewire\Component;
use Illuminate\Support\Facades\Artisan;
use App\Models\ClarityFetchCounter;
use App\Models\ClarityInsight;
use App\Models\Project;

new class extends Component {

    public ?string $error = null;
    public int $days = 1;
    public string $modalId = 'clarity-fetch-modal';

    public function getCounterProperty()
    {
        $projectId = session('current_project_id');
        return ClarityFetchCounter::where('project_id', $projectId)
            ->where('date', now()->toDateString())
            ->first();
    }

    public function getCounterMaxProperty(): int
    {
        return (int) config('services.clarity.fetch_daily_limit');
    }

    public function getUsedProperty(): int
    {
        return (int) ($this->counter->fetch_count ?? 0);
    }

    public function getRemainingProperty(): int
    {
        return max(0, $this->counterMax - $this->used);
    }

    public function getHasClarityKeyProperty(): bool
    {
        return (bool) Project::current()?->hasClarityKey();
    }

    public function getRecentFetchesProperty()
    {
        $projectId = session('current_project_id');
        if (!$projectId) return collect();

        // Each "fetch" creates many ClarityInsight rows sharing the same fetched_for.
        // Pull a generous slice, then dedupe by fetched_for in PHP.
        return ClarityInsight::where('project_id', $projectId)
            ->whereNotNull('fetched_for')
            ->select('fetched_for', 'date_from', 'date_to')
            ->orderByDesc('fetched_for')
            ->limit(60)
            ->get()
            ->unique('fetched_for')
            ->take(5)
            ->values();
    }

    public function fetchInfo(): void
    {
        $project = Project::current();

        if (!$project) {
            $this->error = __('No project selected. Please select a project first.');
            return;
        }

        if (!$project->hasClarityKey()) {
            $this->error = __('No Clarity API key set for this project.');
            return;
        }

        if (!in_array($this->days, [1, 2, 3], true)) {
            $this->error = __('Please select 1, 2, or 3 days.');
            return;
        }

        if ($this->remaining <= 0) {
            $this->error = __('Daily fetch limit reached.');
            return;
        }

        $this->error = null;

        $exitCode = Artisan::call('app:fetch-clarity', [
            'project' => $project->id,
            '--days' => $this->days,
        ]);

        if ($exitCode !== 0) {
            $this->error = trim(Artisan::output());
            return;
        }

        // Close modal and tell listeners (trends / snapshot pages) to refresh.
        $this->dispatch('close-modal', id: $this->modalId);
        $this->dispatch('clarity-fetched');
    }
};
?>

<div class="flex items-center gap-3">
    <span class="text-sm font-medium text-neutral-500 dark:text-neutral-400 whitespace-nowrap">
        {{ $this->used }} / {{ $this->counterMax }}
    </span>

    @if (!$this->hasClarityKey)
        <x-ui.button variant="primary" icon="arrow-clockwise" disabled :title="__('No Clarity API key set for this project.')">
            {{ __('Fetch info') }}
        </x-ui.button>
    @else
    <x-ui.modal
        :id="$modalId"
        :heading="__('Fetch Clarity Data')"
        :description="__('Pull the latest Clarity metrics for this project.')"
        width="md"
    >
        <x-slot:trigger>
            <x-ui.button variant="primary" icon="arrow-clockwise">
                {{ __('Fetch info') }}
            </x-ui.button>
        </x-slot:trigger>

        <div class="space-y-5">
            @if ($error)
                <x-ui.error :messages="[$error]" />
            @endif

            {{-- Daily quota --}}
            <div class="flex items-center justify-between gap-3 p-3 rounded-box bg-neutral-50 dark:bg-neutral-800/40 border border-neutral-200 dark:border-neutral-800">
                <div class="flex items-center gap-3">
                    <div @class([
                        'rounded-full p-2',
                        'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' => $this->remaining > 0,
                        'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400' => $this->remaining <= 0,
                    ])>
                        <x-ui.icon name="gauge" class="size-4" />
                    </div>
                    <div>
                        <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                            {{ __('Daily fetches used') }}
                        </div>
                        <div class="text-xs text-neutral-500 dark:text-neutral-400">
                            @if ($this->remaining > 0)
                                {{ __(':n remaining today', ['n' => $this->remaining]) }}
                            @else
                                {{ __('Limit reached — resets at midnight') }}
                            @endif
                        </div>
                    </div>
                </div>
                <div class="text-sm font-semibold text-neutral-900 dark:text-neutral-100 tabular-nums">
                    {{ $this->used }} / {{ $this->counterMax }}
                </div>
            </div>

            {{-- Days selector --}}
            <x-ui.field>
                <x-ui.label>{{ __('Days to fetch') }}</x-ui.label>
                <x-ui.select wire:model="days">
                    <x-ui.select.option value="1">{{ __('Previous 1 day') }}</x-ui.select.option>
                    <x-ui.select.option value="2">{{ __('Previous 2 days') }}</x-ui.select.option>
                    <x-ui.select.option value="3">{{ __('Previous 3 days') }}</x-ui.select.option>
                </x-ui.select>
            </x-ui.field>

            {{-- Recent fetches --}}
            <div>
                <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100 mb-2">
                    {{ __('Recent fetches') }}
                </div>
                @if ($this->recentFetches->isEmpty())
                    <div class="p-3 rounded-box border border-dashed border-neutral-200 dark:border-neutral-800 text-center">
                        <x-ui.description>{{ __('No previous fetches for this project.') }}</x-ui.description>
                    </div>
                @else
                    <ul class="divide-y divide-neutral-200 dark:divide-neutral-800 border border-neutral-200 dark:border-neutral-800 rounded-box overflow-hidden">
                        @foreach ($this->recentFetches as $fetch)
                            <li class="flex items-center justify-between gap-3 p-2.5 text-sm">
                                <div class="flex items-center gap-2 min-w-0">
                                    <x-ui.icon name="clock" class="size-4 text-neutral-400 shrink-0" />
                                    <span class="text-neutral-700 dark:text-neutral-300 truncate">
                                        {{ $fetch->fetched_for->format('M d, Y H:i') }}
                                    </span>
                                </div>
                                <span class="text-xs text-neutral-500 dark:text-neutral-400 whitespace-nowrap">
                                    {{ $fetch->date_from->format('M d') }} → {{ $fetch->date_to->format('M d') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <x-slot:footer>
            <x-ui.button
                type="button"
                variant="outline"
                color="neutral"
                x-on:click="$dispatch('close-modal', { id: '{{ $modalId }}' })"
            >
                {{ __('Cancel') }}
            </x-ui.button>
            <x-ui.button
                type="button"
                variant="primary"
                icon="arrow-clockwise"
                wire:click="fetchInfo"
                wire:loading.attr="disabled"
                wire:target="fetchInfo"
                :disabled="$this->remaining <= 0"
            >
                <span wire:loading.remove wire:target="fetchInfo">{{ __('Fetch info') }}</span>
                <span wire:loading wire:target="fetchInfo">{{ __('Fetching...') }}</span>
            </x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
    @endif
</div>
