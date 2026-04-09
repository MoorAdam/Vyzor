<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Report;
use App\ReportStatusEnum;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] class extends Component {

    public Report $report;
    public bool $editing = false;
    public string $editTitle = '';
    public string $editContent = '';

    public function mount(Report $report): void
    {
        $this->report = $report;
        $this->editTitle = $report->title;
        $this->editContent = $report->content ?? '';
    }

    public function startEditing(): void
    {
        $this->editTitle = $this->report->title;
        $this->editContent = $this->report->content ?? '';
        $this->editing = true;
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
    }

    public function save(): void
    {
        $this->validate([
            'editTitle' => 'required|string|max:255',
            'editContent' => 'required|string',
        ]);

        $this->report->update([
            'title' => $this->editTitle,
            'content' => $this->editContent,
            'status' => ReportStatusEnum::COMPLETED,
        ]);

        $this->editing = false;
    }

    public function deleteReport(): void
    {
        $this->report->delete();
        $this->redirect('/reports', navigate: true);
    }
};
?>

<div
    @if ($report->status === ReportStatusEnum::PENDING || $report->status === ReportStatusEnum::GENERATING)
        wire:poll.5s
    @endif
    class="p-6 space-y-6"
>
    {{-- Header --}}
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="/reports" class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300 transition-colors">
                <x-ui.icon name="arrow-left" class="size-5" />
            </a>
            <div>
                @if (!$editing)
                    <div class="flex items-center gap-2 mb-1">
                        @if ($report->is_ai)
                            <x-ui.icon name="robot" class="size-5 text-blue-500" />
                        @else
                            <x-ui.icon name="user" class="size-5 text-violet-500" />
                        @endif
                        <x-ui.heading level="h1" size="xl">{{ $report->title }}</x-ui.heading>
                    </div>
                @else
                    <x-ui.field>
                        <x-ui.input wire:model="editTitle" :placeholder="__('Report title...')" :invalid="$errors->has('editTitle')" />
                        <x-ui.error name="editTitle" />
                    </x-ui.field>
                @endif

                <div class="flex flex-wrap items-center gap-3 text-sm text-neutral-500 dark:text-neutral-400 mt-1">
                    <x-ui.badge size="sm" color="{{ $report->status->color() }}">{{ $report->status->label() }}</x-ui.badge>
                    @if ($report->preset)
                        <span class="inline-flex items-center gap-1">
                            @if ($report->contextPreset)
                                <x-ui.icon :name="$report->contextPreset->icon" class="size-3.5" style="color: {{ $report->contextPreset->label_color }}" />
                                {{ $report->contextPreset->localizedName() }}
                            @else
                                <x-ui.icon name="tag" class="size-3.5" />
                                {{ \Illuminate\Support\Str::title(str_replace('-', ' ', $report->preset)) }}
                            @endif
                        </span>
                    @endif
                    @if ($report->aspect_date_from && $report->aspect_date_to)
                        <span class="inline-flex items-center gap-1">
                            <x-ui.icon name="calendar" class="size-3.5" />
                            {{ $report->aspect_date_from->format('M d') }} - {{ $report->aspect_date_to->format('M d, Y') }}
                        </span>
                    @endif
                    @if ($report->ai_model_name)
                        <span class="inline-flex items-center gap-1">
                            <x-ui.icon name="cpu" class="size-3.5" />
                            {{ $report->ai_model_name }}
                        </span>
                    @endif
                    <span class="inline-flex items-center gap-1">
                        <x-ui.icon name="user" class="size-3.5" />
                        {{ $report->user->name ?? __('Unknown') }}
                    </span>
                    <span>{{ $report->created_at->format('M d, Y \a\t H:i') }}</span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            @if ($editing)
                <x-ui.button color="blue" icon="floppy-disk" wire:click="save">
                    {{ __('Save') }}
                </x-ui.button>
                <x-ui.button variant="outline" color="neutral" wire:click="cancelEditing">
                    {{ __('Cancel') }}
                </x-ui.button>
            @else
                <x-ui.button variant="outline" color="neutral" icon="pencil-simple" wire:click="startEditing">
                    {{ __('Edit') }}
                </x-ui.button>
                <x-ui.modal.trigger id="delete-report-modal">
                    <button class="p-2 text-neutral-400 hover:text-red-500 transition-colors rounded-lg hover:bg-red-50 dark:hover:bg-red-950/20">
                        <x-ui.icon name="trash" class="size-4" />
                    </button>
                </x-ui.modal.trigger>
            @endif
        </div>
    </div>

    <x-ui.modal id="delete-report-modal" :title="__('Delete Report')" size="sm" centered>
        <x-ui.text>{{ __('Are you sure you want to delete') }} <strong>{{ $report->title }}</strong>? {{ __('This cannot be undone.') }}</x-ui.text>
        <x-slot:footer>
            <x-ui.button variant="ghost" x-on:click="isOpen = false">{{ __('Cancel') }}</x-ui.button>
            <x-ui.button variant="danger" wire:click="deleteReport" x-on:click="isOpen = false">{{ __('Delete') }}</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- Report Meta Info --}}
    @if ($report->is_ai && ($report->custom_prompt || $report->status === ReportStatusEnum::PENDING || $report->status === ReportStatusEnum::GENERATING))
        <x-ui.card size="full" class="border-l-4 border-l-blue-500">
            @if ($report->status === ReportStatusEnum::PENDING || $report->status === ReportStatusEnum::GENERATING)
                <div class="flex items-center gap-3 mb-3">
                    <div class="animate-spin">
                        <x-ui.icon name="spinner" class="size-5 text-blue-500" />
                    </div>
                    <span class="text-sm font-medium text-blue-600 dark:text-blue-400">
                        {{ $report->status === ReportStatusEnum::PENDING ? __('Waiting to be processed...') : __('AI is generating this report...') }}
                    </span>
                </div>
            @endif
            @if ($report->custom_prompt)
                <div>
                    <span class="text-xs font-medium text-neutral-500 dark:text-neutral-400 uppercase tracking-wide">{{ __('Additional Instructions') }}</span>
                    <p class="text-sm text-neutral-700 dark:text-neutral-300 mt-1">{{ $report->custom_prompt }}</p>
                </div>
            @endif
        </x-ui.card>
    @endif

    @if ($report->status === ReportStatusEnum::FAILED)
        <div class="rounded-lg bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800 p-4">
            <div class="flex items-center gap-2">
                <x-ui.icon name="warning" class="size-5 text-red-600 dark:text-red-400" />
                <span class="text-sm text-red-700 dark:text-red-300">{{ __('This report failed to generate. You can try requesting a new one.') }}</span>
            </div>
        </div>
    @endif

    {{-- Report Content --}}
    <x-ui.card size="full">
        @if ($editing)
            <x-ui.field>
                <textarea
                    wire:model="editContent"
                    rows="20"
                    @class([
                        'w-full rounded-box px-4 py-3 text-sm text-neutral-800 dark:text-neutral-300 bg-white dark:bg-neutral-900 focus:ring-2 focus:outline-none shadow-xs resize-y font-mono',
                        'border border-black/10 dark:border-white/15 focus:ring-neutral-900/15 dark:focus:ring-neutral-100/15 focus:border-black/15 dark:focus:border-white/20' => !$errors->has('editContent'),
                        'border-2 border-red-600/30 focus:border-red-600/30 focus:ring-red-600/20 dark:border-red-400/30 dark:focus:border-red-400/30 dark:focus:ring-red-400/20' => $errors->has('editContent'),
                    ])
                ></textarea>
                <x-ui.error name="editContent" />
            </x-ui.field>
        @elseif ($report->content)
            <div wire:key="markdown-{{ $report->id }}-{{ $report->updated_at->timestamp }}" x-data="markdownRenderer" class="prose prose-sm dark:prose-invert max-w-none">
                <div x-ref="source" class="hidden">{{ $report->content }}</div>
                <div x-html="rendered"></div>
            </div>
        @else
            <x-ui.empty>
                <x-ui.empty.contents>
                    <x-ui.icon name="article" class="size-10 text-neutral-300 dark:text-neutral-600" />
                    <x-ui.text>{{ __('No content yet. This report is') }} {{ $report->status->label() }}.</x-ui.text>
                </x-ui.empty.contents>
            </x-ui.empty>
        @endif
    </x-ui.card>
</div>
