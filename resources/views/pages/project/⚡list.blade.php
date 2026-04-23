<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Models\Project;
use App\ProjectStatusEnum;

new #[Layout('layouts.app')] class extends Component {

    private function projectQuery()
    {
        return auth()->user()->isAdmin()
            ? Project::query()
            : Project::whereHas('permission', fn($q) => $q->where('owner_id', auth()->id()));
    }

    public function setActiveProject(int $projectId): void
    {
        $this->projectQuery()->findOrFail($projectId);
        session(['current_project_id' => $projectId]);
        $this->js("localStorage.setItem('current_project_" . auth()->id() . "', '" . $projectId . "')");
        $this->js("window.location.reload()");
    }

    #[On('current-project-changed')]
    public function onProjectChanged()
    {
        // Re-renders the component to reflect the new active state
    }

    public function updateStatus(int $projectId, string $status): void
    {
        $project = $this->projectQuery()->findOrFail($projectId);
        $project->update(['status' => ProjectStatusEnum::from($status)]);
    }

    public function deleteProject(int $projectId): void
    {
        $project = $this->projectQuery()->findOrFail($projectId);
        $project->delete();

        if (session('current_project_id') == $projectId) {
            session()->forget('current_project_id');
            $this->dispatch('current-project-changed', projectId: null);
        }
    }

    public function with(): array
    {
        return [
            'projects' => $this->projectQuery()
                ->with('customer')
                ->latest('updated_at')
                ->get(),
            'statuses' => ProjectStatusEnum::cases(),
        ];
    }

};
?>

<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <x-ui.heading level="h1" size="xl">{{ __('Projects') }}</x-ui.heading>
            <x-ui.description class="mt-1">{{ __('All projects assigned to you.') }}</x-ui.description>
        </div>
        <x-ui.button variant="primary" icon="plus" as="a" href="/new-project">{{ __('New Project') }}</x-ui.button>
    </div>

    <x-ui.card class="overflow-hidden p-0! max-w-full!">
        @if ($projects->isEmpty())
            <x-ui.empty>
                <x-ui.empty.contents>
                    <x-ui.icon name="folder-open" class="size-10 text-neutral-300 dark:text-neutral-600" />
                    <x-ui.text>{{ __('No projects found.') }}</x-ui.text>
                    <x-ui.button variant="outline" color="neutral" as="a" href="/new-project" class="mt-2">{{ __('Create your first project') }}</x-ui.button>
                </x-ui.empty.contents>
            </x-ui.empty>
        @else
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-neutral-500 dark:text-neutral-400 border-b border-neutral-200 dark:border-neutral-800">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Domain') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Customer') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Created') }}</th>
                        <th class="px-4 py-3 font-medium w-36">{{ __('Updated') }}</th>
                        <th class="px-4 py-3 font-medium w-0">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                    @foreach ($projects as $project)
                        @php $isActive = session('current_project_id') == $project->id; @endphp
                        <tr class="h-16 {{ $isActive ? 'bg-neutral-50 dark:bg-neutral-900/30 border-l-2 border-l-green-500' : '' }}">
                            <td class="px-4 py-3 align-middle">
                                <div>
                                    <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ $project->name }}</span>
                                    @if ($project->description)
                                        <p class="text-xs text-neutral-400 dark:text-neutral-500 truncate max-w-xs mt-0.5">{{ $project->description }}</p>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">
                                <x-ui.link href="{{ $project->domain ?? '—' }}">{{ $project->domain ?? '—' }}</x-ui.link>
                            </td>
                            <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">
                                {{ $project->customer?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <x-ui.dropdown position="bottom-start" portal>
                                    <x-slot:button>
                                        <x-ui.badge size="sm" class="cursor-pointer" :color="$project->status->color()">
                                            {{ $project->status->label() }}
                                            <x-ui.icon name="caret-down" class="size-3" />
                                        </x-ui.badge>
                                    </x-slot:button>
                                    <x-slot:menu>
                                        @foreach ($statuses as $status)
                                            <x-ui.dropdown.item
                                                wire:click="updateStatus({{ $project->id }}, '{{ $status->value }}')"
                                                :active="$project->status === $status"
                                            >
                                                <span class="flex items-center gap-2">
                                                    <span class="size-2 rounded-full bg-{{ $status->color() }}-500"></span>
                                                    {{ $status->label() }}
                                                </span>
                                            </x-ui.dropdown.item>
                                        @endforeach
                                    </x-slot:menu>
                                </x-ui.dropdown>
                            </td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400 whitespace-nowrap">
                                {{ $project->created_at->format('M d, Y') }}
                            </td>
                            <td class="px-4 py-3 text-neutral-500 dark:text-neutral-400 whitespace-nowrap">
                                {{ $project->updated_at->diffForHumans() }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-1">
                                    <x-ui.button
                                        size="xs"
                                        variant="soft"
                                        icon="arrow-square-in"
                                        wire:click="setActiveProject({{ $project->id }})"
                                        :disabled="$isActive"
                                    >
                                        {{ __('Set Active') }}
                                    </x-ui.button>
                                    <x-ui.button size="xs" variant="soft" icon="pencil-simple" as="a" href="{{ route('project.edit', $project) }}" wire:navigate>{{ __('Edit') }}</x-ui.button>
                                    <x-ui.modal.trigger :id="'delete-project-' . $project->id">
                                        <x-ui.button size="xs" variant="soft" icon="trash">
                                            {{ __('Delete') }}
                                        </x-ui.button>
                                    </x-ui.modal.trigger>
                                    <x-ui.modal :id="'delete-project-' . $project->id" :title="__('Delete Project')" size="sm" centered>
                                        <x-ui.text>{!! __('Are you sure you want to delete <strong>:name</strong>?', ['name' => $project->name]) !!}</x-ui.text>
                                        <x-slot:footer>
                                            <x-ui.button variant="ghost" x-on:click="isOpen = false">{{ __('Cancel') }}</x-ui.button>
                                            <x-ui.button variant="danger" wire:click="deleteProject({{ $project->id }})" x-on:click="isOpen = false">{{ __('Delete') }}</x-ui.button>
                                        </x-slot:footer>
                                    </x-ui.modal>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</div>
