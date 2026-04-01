<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use App\Models\Report;
use App\ReportStatusEnum;
use App\ReportPresetEnum;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $filterPreset = '';
    public string $filterType = '';
    public string $filterStatus = '';
    public string $filterDateFrom = '';
    public string $filterDateTo = '';
    public string $search = '';

    #[On('current-project-changed')]
    public function onProjectChanged()
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilterPreset(): void
    {
        $this->resetPage();
    }

    public function updatedFilterType(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['filterPreset', 'filterType', 'filterStatus', 'filterDateFrom', 'filterDateTo', 'search']);
        $this->resetPage();
    }

    public function deleteReport(int $reportId): void
    {
        $report = Report::find($reportId);
        if ($report && $report->project_id == session('current_project_id')) {
            $report->delete();
        }
    }

    public function getPresetsProperty(): array
    {
        $presets = [];
        foreach (ReportPresetEnum::cases() as $enum) {
            $presets[$enum->value] = $enum->label();
        }
        return $presets;
    }

    public function with(): array
    {
        $projectId = session('current_project_id');

        if (!$projectId) {
            return ['reports' => collect(), 'hasProject' => false];
        }

        $query = Report::forProject($projectId)->latest();

        if ($this->filterPreset) {
            $query->where('preset', $this->filterPreset);
        }

        if ($this->filterType === 'ai') {
            $query->aiReports();
        } elseif ($this->filterType === 'manual') {
            $query->userReports();
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterDateFrom) {
            $query->where('aspect_date_from', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo) {
            $query->where('aspect_date_to', '<=', $this->filterDateTo);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'ilike', "%{$this->search}%")
                  ->orWhere('content', 'ilike', "%{$this->search}%");
            });
        }

        return [
            'reports' => $query->paginate(15),
            'hasProject' => true,
        ];
    }
};
?>

<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <x-ui.heading level="h1" size="xl">All Reports</x-ui.heading>
            <x-ui.description class="mt-1">Browse and manage all reports for the current project.</x-ui.description>
        </div>
        <x-ui.button color="blue" icon="plus" href="/ai-reports">
            New Report
        </x-ui.button>
    </div>

    @if (!$hasProject)
        <x-ui.card>
            <x-ui.empty>
                <x-ui.empty.contents>
                    <x-ui.icon name="book-bookmark" class="size-10 text-neutral-300 dark:text-neutral-600" />
                    <x-ui.text>No project selected. Please select a project first.</x-ui.text>
                </x-ui.empty.contents>
            </x-ui.empty>
        </x-ui.card>
    @else
        {{-- Filters --}}
        <x-ui.card size="full">
            <div class="flex flex-wrap items-end gap-4">
                {{-- Search --}}
                <div class="flex-1 min-w-48">
                    <x-ui.field>
                        <x-ui.label>Search</x-ui.label>
                        <x-ui.input wire:model.live.debounce.300ms="search" placeholder="Search reports..." leftIcon="magnifying-glass" />
                    </x-ui.field>
                </div>

                {{-- Type Filter --}}
                <div class="w-36">
                    <x-ui.field>
                        <x-ui.label>Type</x-ui.label>
                        <x-ui.select wire:model.live="filterType" placeholder="All">
                            <x-ui.select.option value="">All</x-ui.select.option>
                            <x-ui.select.option value="ai">AI Reports</x-ui.select.option>
                            <x-ui.select.option value="manual">Manual</x-ui.select.option>
                        </x-ui.select>
                    </x-ui.field>
                </div>

                {{-- Preset Filter --}}
                <div class="w-64">
                    <x-ui.field>
                        <x-ui.label>Preset</x-ui.label>
                        <x-ui.select wire:model.live="filterPreset" placeholder="All Presets">
                            <x-ui.select.option value="">All Presets</x-ui.select.option>
                            @foreach ($this->presets as $slug => $title)
                                <x-ui.select.option :value="$slug">{{ $title }}</x-ui.select.option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>

                {{-- Status Filter --}}
                <div class="w-36">
                    <x-ui.field>
                        <x-ui.label>Status</x-ui.label>
                        <x-ui.select wire:model.live="filterStatus" placeholder="All">
                            <x-ui.select.option value="">All</x-ui.select.option>
                            @foreach (App\ReportStatusEnum::cases() as $status)
                                <x-ui.select.option :value="$status->value">{{ $status->label() }}</x-ui.select.option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>
                </div>

                {{-- Date Range --}}
                <div class="w-36">
                    <x-ui.field>
                        <x-ui.label>Aspect From</x-ui.label>
                        <x-ui.input type="date" wire:model.live="filterDateFrom" />
                    </x-ui.field>
                </div>
                <div class="w-36">
                    <x-ui.field>
                        <x-ui.label>Aspect To</x-ui.label>
                        <x-ui.input type="date" wire:model.live="filterDateTo" />
                    </x-ui.field>
                </div>

                {{-- Clear --}}
                @if ($filterPreset || $filterType || $filterStatus || $filterDateFrom || $filterDateTo || $search)
                    <x-ui.button variant="outline" color="neutral" size="sm" wire:click="clearFilters">
                        Clear filters
                    </x-ui.button>
                @endif
            </div>
        </x-ui.card>

        {{-- Reports List --}}
        @if ($reports->isEmpty())
            <x-ui.card>
                <x-ui.empty>
                    <x-ui.empty.contents>
                        <x-ui.icon name="book-bookmark" class="size-10 text-neutral-300 dark:text-neutral-600" />
                        <x-ui.text>No reports found matching your filters.</x-ui.text>
                    </x-ui.empty.contents>
                </x-ui.empty>
            </x-ui.card>
        @else
            <div class="space-y-3">
                @foreach ($reports as $report)
                    <x-ui.card size="full" class="hover:border-neutral-300 dark:hover:border-neutral-600 transition-colors">
                        <div class="flex items-start justify-between gap-4">
                            <a href="/reports/{{ $report->id }}" class="flex-1 min-w-0 block">
                                <div class="flex items-center gap-2 mb-1">
                                    @if ($report->is_ai)
                                        <x-ui.icon name="robot" class="size-4 text-blue-500 shrink-0" />
                                    @else
                                        <x-ui.icon name="user" class="size-4 text-violet-500 shrink-0" />
                                    @endif
                                    <span class="text-sm font-medium text-neutral-900 dark:text-neutral-100 truncate">{{ $report->title }}</span>
                                </div>

                                @if ($report->content)
                                    <p class="text-sm text-neutral-500 dark:text-neutral-400 line-clamp-2 mb-2">
                                        {{ Str::limit(strip_tags($report->content), 200) }}
                                    </p>
                                @endif

                                <div class="flex flex-wrap items-center gap-3 text-xs text-neutral-500 dark:text-neutral-400">
                                    @if ($report->preset)
                                        @php $presetEnum = App\ReportPresetEnum::tryFrom($report->preset) @endphp
                                        <span class="inline-flex items-center gap-1">
                                            @if ($presetEnum)
                                                <x-ui.icon :name="$presetEnum->icon()" class="size-3" style="color: {{ $presetEnum->color() }}" />
                                                {{ $presetEnum->label() }}
                                            @else
                                                <x-ui.icon name="tag" class="size-3" />
                                                {{ Str::title(str_replace('-', ' ', $report->preset)) }}
                                            @endif
                                        </span>
                                    @endif
                                    @if ($report->aspect_date_from && $report->aspect_date_to)
                                        <span class="inline-flex items-center gap-1">
                                            <x-ui.icon name="calendar" class="size-3" />
                                            {{ $report->aspect_date_from->format('M d') }} - {{ $report->aspect_date_to->format('M d, Y') }}
                                        </span>
                                    @endif
                                    @if ($report->ai_model_name)
                                        <span class="inline-flex items-center gap-1">
                                            <x-ui.icon name="cpu" class="size-3" />
                                            {{ $report->ai_model_name }}
                                        </span>
                                    @endif
                                    <span class="inline-flex items-center gap-1">
                                        <x-ui.icon name="user" class="size-3" />
                                        {{ $report->user->name ?? 'Unknown' }}
                                    </span>
                                    <span>{{ $report->created_at->diffForHumans() }}</span>
                                </div>
                            </a>

                            <div class="flex items-center gap-2 shrink-0">
                                <x-ui.badge size="sm" color="{{ $report->status->color() }}">{{ $report->status->label() }}</x-ui.badge>
                                <button
                                    wire:click="deleteReport({{ $report->id }})"
                                    wire:confirm="Are you sure you want to delete this report?"
                                    class="text-neutral-400 hover:text-red-500 transition-colors"
                                >
                                    <x-ui.icon name="trash" class="size-4" />
                                </button>
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $reports->links() }}
            </div>
        @endif
    @endif
</div>
