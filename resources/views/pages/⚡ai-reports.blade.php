<?php

use App\Modules\Projects\Models\Project;
use App\Modules\Reports\Enums\ReportStatusEnum;
use App\Modules\Reports\Models\Report;
use App\Modules\Users\Enums\PermissionEnum;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {

    public string $manualTitle = '';
    public string $manualContent = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('permission', [PermissionEnum::CREATE_REPORT, Project::current()]), 403);
    }

    public function createManualReport(): void
    {
        $this->validate([
            'manualTitle' => 'required|string|max:255',
            'manualContent' => 'required|string',
        ]);

        $projectId = session('current_project_id');
        if (!$projectId) {
            $this->addError('project', __('Please select a project first.'));
            return;
        }

        $report = Report::create([
            'project_id' => $projectId,
            'user_id' => auth()->id(),
            'title' => $this->manualTitle,
            'content' => $this->manualContent,
            'is_ai' => false,
            'preset' => null,
            'custom_prompt' => null,
            'aspect_date_from' => null,
            'aspect_date_to' => null,
            'ai_model_name' => null,
            'status' => ReportStatusEnum::COMPLETED,
        ]);

        $this->redirectRoute('report.view', $report);
    }
};
?>

<div class="p-6 space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <x-ui.heading level="h1" size="xl">{{ __('Write Report') }}</x-ui.heading>
            <x-ui.description class="mt-1">{{ __('Write your own report or notes for the current project.') }}</x-ui.description>
        </div>
    </div>

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

    <x-ui.card size="full">
        <div class="flex items-center gap-2 mb-4">
            <x-ui.icon name="pencil-simple" class="size-5 text-violet-500" />
            <x-ui.heading level="h3" size="md">{{ __('Write a Report') }}</x-ui.heading>
        </div>

        <form wire:submit="createManualReport" class="space-y-5">
            <x-ui.field required>
                <x-ui.label>{{ __('Title') }}</x-ui.label>
                <x-ui.input wire:model="manualTitle" :placeholder="__('Report title...')" :invalid="$errors->has('manualTitle')" />
                <x-ui.error name="manualTitle" />
            </x-ui.field>

            <x-ui.field required>
                <x-ui.label>{{ __('Content') }}</x-ui.label>
                <textarea
                    wire:model="manualContent"
                    rows="12"
                    placeholder="{{ __('Write your report here...') }}"
                    @class([
                        'w-full rounded-box px-3 py-2 text-sm text-neutral-800 dark:text-neutral-300 placeholder-neutral-400 bg-white dark:bg-neutral-900 focus:ring-2 focus:outline-none shadow-xs resize-y',
                        'border border-black/10 dark:border-white/15 focus:ring-neutral-900/15 dark:focus:ring-neutral-100/15 focus:border-black/15 dark:focus:border-white/20' => !$errors->has('manualContent'),
                        'border-2 border-red-600/30 focus:border-red-600/30 focus:ring-red-600/20 dark:border-red-400/30 dark:focus:border-red-400/30 dark:focus:ring-red-400/20' => $errors->has('manualContent'),
                    ])
                ></textarea>
                <x-ui.error name="manualContent" />
            </x-ui.field>

            <div class="flex items-center justify-end pt-2">
                <x-ui.button type="submit" color="violet" icon="floppy-disk">
                    {{ __('Save Report') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <livewire:recent-reports
        :emptyMessage="__('No reports yet. Write your own above or request an AI report from the Clarity menu.')"
    />
</div>
