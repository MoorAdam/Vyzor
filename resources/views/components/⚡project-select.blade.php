<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Project;

new class extends Component {

    #[Computed]
    public function projects()
    {
        return Project::with('customer')->get()->groupBy(fn($project) => $project->customer?->name ?? 'No Customer');
    }

    public function setCurrentProject($projectId)
    {
        // Logic to set the current project, e.g., store in session or emit an event
        session(['current_project_id' => $projectId]);
        $this->emit('projectChanged', $projectId);
    }

};
?>

<div class="w-72">
    <x-ui.select placeholder="Select a project..." {{ $attributes }}
        wire:change="setCurrentProject($event.target.value)">
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
    {{-- People find pleasure in different ways. I find it in keeping my mind clear. - Marcus Aurelius --}}
</div>