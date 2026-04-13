@php
    $project = \App\Models\Project::current();
    $show = $project && !$project->hasClarityKey();
@endphp

@if ($show)
    <div class="theme-danger flex items-center justify-between gap-3 p-3 rounded-box border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-950/20">
        <div class="flex items-center gap-2 text-sm text-red-700 dark:text-red-300">
            <x-ui.icon name="warning-circle" class="size-4 shrink-0" />
            <span>{{ __('No Clarity API key set for this project. Fetching is disabled.') }}</span>
        </div>
        <x-ui.button
            size="sm"
            variant="primary"
            icon="key"
            as="a"
            href="{{ route('project.edit', $project) }}#clarity_api_key"
            wire:navigate
        >
            {{ __('Add Clarity Key') }}
        </x-ui.button>
    </div>
@endif
