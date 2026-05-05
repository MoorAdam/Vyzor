<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\WithPagination;
use App\Modules\Analytics\Clarity\Heatmaps\Models\Heatmap;
use App\Modules\Users\Enums\PermissionEnum;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $filterDateFrom = '';
    public string $filterDateTo = '';
    public string $search = '';

    public ?int $editingId = null;
    public string $editDate = '';
    public string $editFilename = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('permission', [PermissionEnum::VIEW_HEATMAPS, \App\Modules\Projects\Models\Project::current()]), 403);
    }

    #[On('current-project-changed')]
    public function onProjectChanged()
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['filterDateFrom', 'filterDateTo', 'search']);
        $this->resetPage();
    }

    public function startEdit(int $id): void
    {
        $heatmap = Heatmap::find($id);
        if ($heatmap && $heatmap->project_id == session('current_project_id')) {
            $this->editingId = $id;
            $this->editDate = $heatmap->date->format('Y-m-d');
            $this->editFilename = $heatmap->filename;
        }
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->editDate = '';
        $this->editFilename = '';
    }

    public function saveEdit(): void
    {
        abort_unless(auth()->user()->can('permission', [PermissionEnum::EDIT_HEATMAPS, \App\Modules\Projects\Models\Project::current()]), 403);
        $heatmap = Heatmap::find($this->editingId);
        if ($heatmap && $heatmap->project_id == session('current_project_id')) {
            $heatmap->update([
                'date' => $this->editDate,
                'filename' => $this->editFilename,
            ]);
        }
        $this->cancelEdit();
    }

    public function deleteHeatmap(int $id): void
    {
        abort_unless(auth()->user()->can('permission', [PermissionEnum::DELETE_HEATMAPS, \App\Modules\Projects\Models\Project::current()]), 403);
        $heatmap = Heatmap::find($id);
        if ($heatmap && $heatmap->project_id == session('current_project_id')) {
            $heatmap->delete();
        }
    }

    public function downloadCsv(int $id): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $heatmap = Heatmap::find($id);
        if (!$heatmap || $heatmap->project_id != session('current_project_id')) {
            abort(404);
        }

        return response()->streamDownload(function () use ($heatmap) {
            echo $heatmap->heatmap;
        }, $heatmap->filename, ['Content-Type' => 'text/csv']);
    }

    public function with(): array
    {
        $projectId = session('current_project_id');

        if (!$projectId) {
            return ['heatmaps' => collect(), 'hasProject' => false];
        }

        $query = Heatmap::forProject($projectId)->with('user')->latest('date');

        if ($this->filterDateFrom) {
            $query->where('date', '>=', $this->filterDateFrom);
        }

        if ($this->filterDateTo) {
            $query->where('date', '<=', $this->filterDateTo);
        }

        if ($this->search) {
            $query->where('filename', 'ilike', "%{$this->search}%");
        }

        return [
            'heatmaps' => $query->paginate(15),
            'hasProject' => true,
        ];
    }
};
?>

<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <x-ui.heading level="h1" size="xl">{{ __('Heatmaps') }}</x-ui.heading>
            <x-ui.description class="mt-1">{{ __('Browse and manage uploaded heatmap CSVs for the current project.') }}</x-ui.description>
        </div>
        <x-ui.button color="blue" icon="upload-simple" href="/heatmaps/upload">
            {{ __('Upload Heatmap') }}
        </x-ui.button>
    </div>

    @if (!$hasProject)
        <x-ui.card>
            <x-ui.empty>
                <x-ui.empty.contents>
                    <x-ui.icon name="fire" class="size-10 text-neutral-300 dark:text-neutral-600" />
                    <x-ui.text>{{ __('No project selected. Please select a project first.') }}</x-ui.text>
                </x-ui.empty.contents>
            </x-ui.empty>
        </x-ui.card>
    @else
        {{-- Filters --}}
        <x-ui.card size="full">
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-48">
                    <x-ui.field>
                        <x-ui.label>{{ __('Search') }}</x-ui.label>
                        <x-ui.input wire:model.live.debounce.300ms="search" :placeholder="__('Search by filename...')" leftIcon="magnifying-glass" />
                    </x-ui.field>
                </div>

                <div class="w-52">
                    <x-ui.field>
                        <x-ui.label>{{ __('Date From') }}</x-ui.label>
                        <x-ui.date-picker wire:model.live="filterDateFrom" class="w-full" />
                    </x-ui.field>
                </div>

                <div class="w-52">
                    <x-ui.field>
                        <x-ui.label>{{ __('Date To') }}</x-ui.label>
                        <x-ui.date-picker wire:model.live="filterDateTo" class="w-full" />
                    </x-ui.field>
                </div>

                @if ($filterDateFrom || $filterDateTo || $search)
                    <x-ui.button variant="outline" color="neutral" size="sm" wire:click="clearFilters">
                        {{ __('Clear filters') }}
                    </x-ui.button>
                @endif
            </div>
        </x-ui.card>

        {{-- Heatmaps List --}}
        @if ($heatmaps->isEmpty())
            <x-ui.card>
                <x-ui.empty>
                    <x-ui.empty.contents>
                        <x-ui.icon name="fire" class="size-10 text-neutral-300 dark:text-neutral-600" />
                        <x-ui.text>{{ __('No heatmaps found. Upload one to get started.') }}</x-ui.text>
                    </x-ui.empty.contents>
                </x-ui.empty>
            </x-ui.card>
        @else
            <div class="space-y-3">
                @foreach ($heatmaps as $heatmap)
                    <x-ui.card size="full" class="hover:border-neutral-300 dark:hover:border-neutral-600 transition-colors">
                        @if ($editingId === $heatmap->id)
                            {{-- Edit Mode --}}
                            <div class="flex flex-wrap items-end gap-4">
                                <div class="flex-1 min-w-48">
                                    <x-ui.field>
                                        <x-ui.label>{{ __('Filename') }}</x-ui.label>
                                        <x-ui.input wire:model="editFilename" />
                                    </x-ui.field>
                                </div>
                                <div class="w-52">
                                    <x-ui.field>
                                        <x-ui.label>{{ __('Date') }}</x-ui.label>
                                        <x-ui.date-picker wire:model="editDate" class="w-full" />
                                    </x-ui.field>
                                </div>
                                <div class="flex gap-2">
                                    <x-ui.button size="sm" color="green" wire:click="saveEdit">{{ __('Save') }}</x-ui.button>
                                    <x-ui.button size="sm" variant="outline" color="neutral" wire:click="cancelEdit">{{ __('Cancel') }}</x-ui.button>
                                </div>
                            </div>
                        @else
                            {{-- View Mode --}}
                            <div class="flex items-center justify-between">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-3">
                                        <x-ui.icon name="file-csv" class="size-5 text-neutral-400 shrink-0" />
                                        <div>
                                            <div class="font-medium text-neutral-900 dark:text-neutral-100 truncate">
                                                {{ $heatmap->filename }}
                                            </div>
                                            <div class="text-sm text-neutral-500 dark:text-neutral-400 mt-0.5">
                                                {{ $heatmap->date->format('M d, Y') }}
                                                &middot;
                                                {{ __('Uploaded by') }} {{ $heatmap->user->name ?? __('Unknown') }}
                                                &middot;
                                                {{ $heatmap->created_at->diffForHumans() }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 ml-4 shrink-0">
                                    <x-ui.button size="sm" variant="outline" color="blue" wire:click="downloadCsv({{ $heatmap->id }})">
                                        {{ __('Download') }}
                                    </x-ui.button>
                                    <x-ui.button size="sm" variant="outline" color="neutral" wire:click="startEdit({{ $heatmap->id }})" :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::EDIT_HEATMAPS)">
                                        {{ __('Edit') }}
                                    </x-ui.button>
                                    <x-ui.button size="sm" variant="outline" color="red"
                                        wire:click="deleteHeatmap({{ $heatmap->id }})"
                                        wire:confirm="{{ __('Are you sure you want to delete this heatmap?') }}"
                                        :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::DELETE_HEATMAPS)">
                                        {{ __('Delete') }}
                                    </x-ui.button>
                                </div>
                            </div>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $heatmaps->links() }}
            </div>
        @endif
    @endif
</div>
