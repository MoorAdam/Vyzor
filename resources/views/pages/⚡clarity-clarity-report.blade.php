<?php

use App\Modules\Projects\Models\Project;
use App\Modules\Users\Enums\PermissionEnum;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {

    public function mount(): void
    {
        abort_unless(auth()->user()->can('permission', [PermissionEnum::CREATE_REPORT, Project::current()]), 403);
    }
};
?>

<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <x-ui.heading level="h1" size="xl">{{ __('Clarity Report') }}</x-ui.heading>
            <x-ui.description class="mt-1">{{ __('Request an AI-generated report from Clarity insights for the current project.') }}</x-ui.description>
        </div>
        <x-ui.button variant="outline" color="neutral" size="sm" icon="gear" href="{{ route('preset.settings') }}">
            {{ __('Manage Presets') }}
        </x-ui.button>
    </div>

    <x-clarity-key-required />

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

    <livewire:clarity-report-tab />

    <livewire:recent-reports
        :emptyMessage="__('No reports yet. Request an AI report above.')"
    />
</div>
