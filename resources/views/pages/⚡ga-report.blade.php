<?php

use App\Modules\Ai\Contexts\Enums\ContextTag;
use App\Modules\Projects\Models\Project;
use App\Modules\Users\Enums\PermissionEnum;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {

    public function mount(): void
    {
        // Two gates: VIEW_GOOGLE_ANALYTICS for the page itself, CREATE_REPORT
        // for actually being able to submit. Both must pass; the form
        // component re-checks CREATE_REPORT at submit time too.
        $project = Project::current();
        abort_unless(auth()->user()->can('permission', [PermissionEnum::VIEW_GOOGLE_ANALYTICS, $project]), 403);
        abort_unless(auth()->user()->can('permission', [PermissionEnum::CREATE_REPORT, $project]), 403);
    }

    public function with(): array
    {
        $project = Project::current();

        return [
            'project'    => $project,
            'configured' => $project?->hasGoogleAnalytics() ?? false,
        ];
    }
};
?>

<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <x-ui.heading level="h1" size="xl">{{ __('Google Analytics Report') }}</x-ui.heading>
            <x-ui.description class="mt-1">{{ __('Request an AI-generated report from Google Analytics 4 data for the current project.') }}</x-ui.description>
        </div>
        <x-ui.button variant="outline" color="neutral" size="sm" icon="gear" href="{{ route('preset.settings') }}">
            {{ __('Manage Presets') }}
        </x-ui.button>
    </div>

    @if (!$project)
        <x-ui.card>
            <x-ui.empty>
                <x-ui.empty.contents>
                    <x-ui.icon name="folder-open" class="size-10 text-neutral-300 dark:text-neutral-600" />
                    <x-ui.text>{{ __('Select a project from the header to view analytics.') }}</x-ui.text>
                </x-ui.empty.contents>
            </x-ui.empty>
        </x-ui.card>
    @elseif (!$configured)
        <x-ui.card>
            <x-ui.empty>
                <x-ui.empty.contents>
                    <x-ui.icon name="plug" class="size-10 text-neutral-300 dark:text-neutral-600" />
                    <x-ui.text>{{ __('No Google Analytics property configured for this project.') }}</x-ui.text>
                    <x-ui.button :href="route('project.edit', $project)" variant="outline" icon="gear" class="mt-2">
                        {{ __('Configure in project settings') }}
                    </x-ui.button>
                </x-ui.empty.contents>
            </x-ui.empty>
        </x-ui.card>
    @else
        @if (session('success'))
            <div class="rounded-lg bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 p-4">
                <div class="flex items-center gap-2">
                    <x-ui.icon name="check-circle" class="size-5 text-green-600 dark:text-green-400" />
                    <span class="text-sm text-green-700 dark:text-green-300">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @error('project')
            <div class="rounded-lg bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 p-4">
                <div class="flex items-center gap-2">
                    <x-ui.icon name="warning" class="size-5 text-red-600 dark:text-red-400" />
                    <span class="text-sm text-red-700 dark:text-red-300">{{ $message }}</span>
                </div>
            </div>
        @enderror

        <livewire:ga-report-tab />

        <livewire:recent-reports
            :tag="ContextTag::GA->value"
            :heading="__('Recent GA Reports')"
            :emptyMessage="__('No GA reports yet. Request an AI report above.')"
        />
    @endif
</div>
