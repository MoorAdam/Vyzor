<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Models\User;
use App\Modules\Projects\Models\Project;
use App\Modules\Users\Enums\PermissionEnum;
use App\Modules\Projects\Enums\ProjectStatusEnum;

new #[Layout('layouts.app')] class extends Component {

    public string $tab = 'owned';

    public function mount()
    {
        if (auth()->user()->cannot('permission', PermissionEnum::VIEW_PROJECTS)) {
            return redirect()->route('no-access');
        }
    }

    private function projectQuery()
    {
        $user = auth()->user();

        if ($user->isAdmin() || $this->hasAllProjectsAccess($user)) {
            return Project::query();
        }

        return $this->tab === 'collaborating'
            ? Project::collaboratingWith($user)
            : Project::ownedBy($user);
    }

    private function hasAllProjectsAccess(User $user): bool
    {
        $permissions = User::permissionsForRoles($user->roles ?? []);

        return $permissions->contains(PermissionEnum::VIEW_ALL_PROJECTS->value)
            || $permissions->contains(PermissionEnum::EDIT_ALL_PROJECTS->value);
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
        abort_unless(auth()->user()->can('permission', [PermissionEnum::CHANGE_PROJECT_STATUS, $project]), 403);
        $project->update(['status' => ProjectStatusEnum::from($status)]);
    }

    public function deleteProject(int $projectId): void
    {
        $project = $this->projectQuery()->findOrFail($projectId);
        abort_unless(auth()->user()->can('permission', [PermissionEnum::DELETE_PROJECT, $project]), 403);
        $project->delete();

        if (session('current_project_id') == $projectId) {
            session()->forget('current_project_id');
            $this->dispatch('current-project-changed', projectId: null);
        }
    }

    public function with(): array
    {
        $user = auth()->user();
        $unscoped = $user->isAdmin() || $this->hasAllProjectsAccess($user);

        return [
            'projects' => $this->projectQuery()
                ->with('customer', 'permission.owner')
                ->latest('updated_at')
                ->get(),
            'statuses' => ProjectStatusEnum::cases(),
            'isAdmin' => $unscoped,
            'collabCount' => $unscoped ? 0 : Project::collaboratingWith($user)->count(),
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
        <x-ui.button variant="primary" icon="plus" as="a" href="/new-project" :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::CREATE_PROJECT)">{{ __('New Project') }}</x-ui.button>
    </div>

    @if (!$isAdmin)
        <x-ui.radio.group wire:model.live="tab" variant="segmented" direction="horizontal">
            <x-ui.radio.item value="owned" :label="__('My Projects')" />
            <x-ui.radio.item value="collaborating" :label="__('Collaborating')" :badge="$collabCount > 0 ? $collabCount : null" />
        </x-ui.radio.group>
    @endif

    @php
        $user = auth()->user();
        $isCollab = $tab === 'collaborating';
    @endphp

    <x-ui.card class="overflow-hidden p-0! max-w-full!">
        @if ($projects->isEmpty())
            <x-ui.empty>
                <x-ui.empty.contents>
                    <x-ui.icon name="{{ $isCollab ? 'users' : 'folder-open' }}" class="size-10 text-neutral-300 dark:text-neutral-600" />
                    <x-ui.text>{{ $isCollab ? __('No collaborating projects.') : __('No projects found.') }}</x-ui.text>
                    @if (!$isCollab)
                        <x-ui.button variant="outline" color="neutral" as="a" href="/new-project" class="mt-2">{{ __('Create your first project') }}</x-ui.button>
                    @endif
                </x-ui.empty.contents>
            </x-ui.empty>
        @else
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-neutral-500 dark:text-neutral-400 border-b border-neutral-200 dark:border-neutral-800">
                    <tr>
                        <th class="px-4 py-3 font-medium">{{ __('Name') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Domain') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Customer') }}</th>
                        @if ($isCollab)
                            <th class="px-4 py-3 font-medium">{{ __('Owner') }}</th>
                        @endif
                        <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                        <th class="px-4 py-3 font-medium">{{ __('Created') }}</th>
                        <th class="px-4 py-3 font-medium w-36">{{ __('Updated') }}</th>
                        <th class="px-4 py-3 font-medium w-0">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 dark:divide-neutral-800">
                    @foreach ($projects as $project)
                        @php
                            $isActive = session('current_project_id') == $project->id;
                            $canChangeStatus = $user->can('permission', [App\Modules\Users\Enums\PermissionEnum::CHANGE_PROJECT_STATUS, $project]);
                            $canEdit = $user->can('permission', [App\Modules\Users\Enums\PermissionEnum::EDIT_PROJECT_DETAILS, $project]);
                            $canDelete = $user->can('permission', [App\Modules\Users\Enums\PermissionEnum::DELETE_PROJECT, $project]);
                        @endphp
                        <tr class="h-16 {{ $isActive ? 'bg-neutral-50 dark:bg-neutral-900/30 border-l-2 border-l-green-500' : '' }}">
                            <td class="px-4 py-3 align-middle">
                                <div>
                                    <span class="font-medium text-neutral-900 dark:text-neutral-100 flex items-center gap-1.5">
                                        @if ($isCollab)
                                            <x-ui.icon name="users" class="size-4 text-blue-500 shrink-0" />
                                        @endif
                                        {{ $project->name }}
                                    </span>
                                    @if ($project->description)
                                        <p class="text-xs text-neutral-400 dark:text-neutral-500 truncate max-w-xs mt-0.5 {{ $isCollab ? 'pl-5.5' : '' }}">{{ $project->description }}</p>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">
                                <x-ui.link href="{{ $project->domain ?? '—' }}">{{ $project->domain ?? '—' }}</x-ui.link>
                            </td>
                            <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">
                                {{ $project->customer?->name ?? '—' }}
                            </td>
                            @if ($isCollab)
                                <td class="px-4 py-3 text-neutral-600 dark:text-neutral-400">
                                    {{ $project->permission?->owner?->name ?? '—' }}
                                </td>
                            @endif
                            <td class="px-4 py-3">
                                @if ($canChangeStatus)
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
                                @else
                                    <x-ui.badge size="sm" :color="$project->status->color()">
                                        {{ $project->status->label() }}
                                    </x-ui.badge>
                                @endif
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
                                    @if ($canEdit)
                                        <x-ui.button size="xs" variant="soft" icon="pencil-simple" as="a" href="{{ route('project.edit', $project) }}" wire:navigate>{{ __('Edit') }}</x-ui.button>
                                    @else
                                        <x-ui.button size="xs" variant="soft" icon="pencil-simple" disabled>{{ __('Edit') }}</x-ui.button>
                                    @endif
                                    <x-ui.modal.trigger :id="'delete-project-' . $project->id">
                                        <x-ui.button size="xs" variant="soft" icon="trash" :disabled="!$canDelete">
                                            {{ __('Delete') }}
                                        </x-ui.button>
                                    </x-ui.modal.trigger>
                                    @if ($canDelete)
                                        <x-ui.modal :id="'delete-project-' . $project->id" :title="__('Delete Project')" size="sm" centered>
                                            <x-ui.text>{!! __('Are you sure you want to delete <strong>:name</strong>?', ['name' => $project->name]) !!}</x-ui.text>
                                            <x-slot:footer>
                                                <x-ui.button variant="ghost" x-on:click="isOpen = false">{{ __('Cancel') }}</x-ui.button>
                                                <x-ui.button variant="danger" wire:click="deleteProject({{ $project->id }})" x-on:click="isOpen = false">{{ __('Delete') }}</x-ui.button>
                                            </x-slot:footer>
                                        </x-ui.modal>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>
</div>
