<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\Project;

new class extends Component {

    public $selectedProject = '';

    private function storageKey(): string
    {
        return 'current_project_' . auth()->id();
    }

    public function mount()
    {
        // Initialized from browser localStorage via Alpine x-init
        $this->selectedProject = '';
    }

    public function initFromStorage(?int $projectId): void
    {
        if (!$projectId) return;

        $accessible = auth()->user()->isAdmin()
            ? Project::where('id', $projectId)->exists()
            : Project::whereHas('permission', fn($q) => $q->where('owner_id', auth()->id()))->where('id', $projectId)->exists();

        if ($accessible) {
            $this->selectedProject = (string) $projectId;
            session(['current_project_id' => $projectId]);
            // Notify other components (snapshot, trends, etc.) that mounted before
            // localStorage was read, so they re-render with the restored project.
            $this->dispatch('current-project-changed', projectId: $projectId);
        } else {
            $this->js("localStorage.removeItem('" . $this->storageKey() . "')");
        }
    }

    #[Computed]
    public function projects()
    {
        $query = auth()->user()->isAdmin()
            ? Project::with('customer')
            : Project::with('customer')->whereHas('permission', fn($q) => $q->where('owner_id', auth()->id()));

        return $query->get()->groupBy(fn($project) => $project->customer?->name ?? __('No Customer'));
    }

    public function updatedSelectedProject($value)
    {
        if (blank($value)) {
            $this->selectedProject = (string) session('current_project_id', '');
            return;
        }

        $id = (int) $value;
        session(['current_project_id' => $id]);
        $this->js("localStorage.setItem('" . $this->storageKey() . "', '" . $id . "')");
        $this->js("window.location.reload()");
    }

    #[On('current-project-changed')]
    public function refreshSelectedProject($projectId)
    {
        $this->selectedProject = (string) $projectId;

        if ($projectId) {
            session(['current_project_id' => (int) $projectId]);
            $this->js("localStorage.setItem('" . $this->storageKey() . "', '" . (int) $projectId . "')");
        } else {
            session()->forget('current_project_id');
            $this->js("localStorage.removeItem('" . $this->storageKey() . "')");
        }
    }

};
?>

<div
    class="w-72"
    x-data
    x-init="
        let stored = localStorage.getItem('current_project_{{ auth()->id() }}');
        if (stored) $wire.initFromStorage(parseInt(stored));
    "
>
    <x-ui.select :placeholder="__('Select a project...')" searchable wire:model.live="selectedProject">
        @foreach ($this->projects as $customerName => $customerProjects)
            <x-ui.select.group :label="$customerName">
                @foreach ($customerProjects as $project)
                    <x-ui.select.option :value="$project->id" :label="$project->name" allowCustomSlots>
                        <div class="flex flex-col py-1">
                            <span class="text-neutral-950 dark:text-neutral-50">{{ $project->name }}</span>
                            <span class="text-xs text-neutral-400 dark:text-neutral-500">{{ $customerName }}</span>
                        </div>
                    </x-ui.select.option>
                @endforeach
            </x-ui.select.group>
        @endforeach
    </x-ui.select>
</div>
