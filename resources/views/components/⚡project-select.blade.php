<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Modules\Projects\Models\Project;

new class extends Component {

    public $selectedProject = '';

    public function mount()
    {
        $this->selectedProject = (string) session('current_project_id', '');
    }

    #[Computed]
    public function projects()
    {
        $query = Project::with('customer')->accessibleBy(auth()->user());

        return $query->get()->groupBy(fn($project) => $project->customer?->name ?? __('No Customer'));
    }

    public function updatedSelectedProject($value)
    {
        if (blank($value)) {
            $this->selectedProject = (string) session('current_project_id', '');
            return;
        }

        Project::setCurrent((int) $value);
        $this->js("window.location.reload()");
    }

    #[On('current-project-changed')]
    public function refreshSelectedProject($projectId)
    {
        $this->selectedProject = (string) $projectId;
    }

};
?>

<div class="w-72">
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
