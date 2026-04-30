<?php

use Livewire\Component;
use Illuminate\Support\Facades\Artisan;
use App\Modules\Analytics\Clarity\Models\ClarityFetchCounter;
use App\Modules\Analytics\Clarity\Models\ClarityInsight;
use App\Modules\Projects\Models\Project;
use App\Modules\Users\Enums\PermissionEnum;

new class extends Component {

    public ?string $error = null;
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
        abort_unless(auth()->user()->can('permission', [PermissionEnum::FETCH_CLARITY_DATA, $project]), 403);

        if (!$project) {
            $this->error = __('No project selected. Please select a project first.');
            return;
        }

        if (!$project->hasClarityKey()) {
            $this->error = __('No Clarity API key set for this project.');
            return;
        }

        if ($this->remaining <= 0) {
            $this->error = __('Daily fetch limit reached.');
            return;
        }

        $this->error = null;

        // Clarity's API can take well over 30s for multi-day fetches; lift the per-request
        // execution cap so the user gets a real success/error rather than a fatal timeout.
        @set_time_limit((int) config('services.clarity.fetch_max_seconds', 180));

        try {
            $exitCode = Artisan::call('app:fetch-clarity', [
                'project' => $project->id,
            ]);
        } catch (\Throwable $e) {
            $this->error = __('Could not fetch Clarity data: :msg', ['msg' => $e->getMessage()]);
            return;
        }

        if ($exitCode !== 0) {
            $this->error = trim(Artisan::output()) ?: __('Clarity fetch failed. Please try again.');
            return;
        }

        // Close modal and reload so every page reflects the freshly fetched data.
        $this->dispatch('close-modal', id: $this->modalId);
        $this->js('window.location.reload()');
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
            <x-ui.button variant="primary" icon="arrow-clockwise" :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::FETCH_CLARITY_DATA)">
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

            <x-ui.description>
                {{ __('Each fetch pulls Clarity\'s last 24 hours of data so every snapshot represents a single day.') }}
            </x-ui.description>

            {{-- Recent fetches --}}
            <div>
                <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100 mb-2">
                    {{ __('Recent fetches') }}
                </div>
                <x-clarity-fetch-list :fetches="$this->recentFetches" />
            </div>
        </div>

        <x-slot:footer>
            <x-ui.button
                type="button"
                variant="outline"
                color="neutral"
                x-on:click="window.dispatchEvent(new CustomEvent('clarity-fetch-cancel', { detail: { id: @js($this->getId()) } })); $dispatch('close-modal', { id: '{{ $modalId }}' })"
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

@script
<script>
    // Track an AbortController for any in-flight Livewire request originating from this
    // component, so the Cancel button can abort the (potentially slow) Clarity fetch.
    let activeAborter = null;
    const myId = $wire.id;

    Livewire.hook('request', ({ payload, options, succeed, fail }) => {
        const involvesUs = (payload?.components ?? []).some(c => {
            try {
                const snap = typeof c.snapshot === 'string' ? JSON.parse(c.snapshot) : c.snapshot;
                return snap?.memo?.id === myId;
            } catch (_) {
                return false;
            }
        });
        if (!involvesUs) return;

        const controller = new AbortController();
        options.signal = controller.signal;
        let wasAborted = false;
        controller.signal.addEventListener('abort', () => { wasAborted = true; });
        activeAborter = controller;

        succeed(() => { if (activeAborter === controller) activeAborter = null; });
        fail(({ preventDefault }) => {
            if (activeAborter === controller) activeAborter = null;
            // Suppress Livewire's default error overlay when WE deliberately aborted.
            if (wasAborted) preventDefault();
        });
    });

    window.addEventListener('clarity-fetch-cancel', (event) => {
        if (event?.detail?.id && event.detail.id !== myId) return;
        if (activeAborter) {
            activeAborter.abort();
            activeAborter = null;
        }
    });
</script>
@endscript
