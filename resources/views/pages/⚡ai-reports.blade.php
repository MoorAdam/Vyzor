<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use App\Models\Project;
use App\Models\Report;
use App\ReportStatusEnum;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] class extends Component {

    // Manual report fields
    public string $manualTitle = '';
    public string $manualContent = '';

    // Top-level tab: ai | manual — persisted in ?tab=
    #[Url(as: 'tab')]
    public string $activeTab = 'ai';

    // Sub-tab inside the AI tab: clarity | page — persisted in ?type=
    #[Url(as: 'type')]
    public string $aiSubTab = 'clarity';

    public function mount(): void
    {
        // Default to "page" sub-tab when the project has no Clarity key
        // and the URL doesn't already specify a type.
        if (!$this->hasClarityKey && !request()->has('type')) {
            $this->aiSubTab = 'page';
        }
    }

    #[On('current-project-changed')]
    public function onProjectChanged(): void
    {
        // Re-evaluate default sub-tab when the project changes.
        $this->aiSubTab = $this->hasClarityKey ? 'clarity' : 'page';
    }

    public function getHasClarityKeyProperty(): bool
    {
        return (bool) Project::current()?->hasClarityKey();
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

    public function with(): array
    {
        $projectId = session('current_project_id');

        $recentReports = $projectId
            ? Report::forProject($projectId)
                ->latest()
                ->limit(5)
                ->get()
            : collect();

        return [
            'recentReports' => $recentReports,
        ];
    }
};
?>

<div
    {{-- Auto-refresh while reports are processing --}}
    @if ($recentReports->contains(fn ($r) => in_array($r->status, [\App\ReportStatusEnum::PENDING, \App\ReportStatusEnum::GENERATING])))
        wire:poll.10s
    @endif
    class="p-6 space-y-6"
>
    <div class="flex items-center justify-between">
        <div>
            <x-ui.heading level="h1" size="xl">{{ __('Reports') }}</x-ui.heading>
            <x-ui.description class="mt-1">{{ __('Request AI-generated reports or create your own notes for the current project.') }}</x-ui.description>
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

    {{-- Top-level tab switcher: AI / Manual --}}
    <div class="flex gap-1 bg-neutral-100 dark:bg-neutral-900 rounded-lg p-1 w-fit">
        <button
            wire:click="$set('activeTab', 'ai')"
            class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $activeTab === 'ai' ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-sm' : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300' }}"
        >
            <span class="flex items-center gap-2">
                <x-ui.icon name="robot" class="size-4" />
                {{ __('Request AI Report') }}
            </span>
        </button>
        <button
            wire:click="$set('activeTab', 'manual')"
            class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $activeTab === 'manual' ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-sm' : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300' }}"
        >
            <span class="flex items-center gap-2">
                <x-ui.icon name="pencil-simple" class="size-4" />
                {{ __('Write Report') }}
            </span>
        </button>
    </div>

    {{-- AI Report Tab --}}
    @if ($activeTab === 'ai')

        {{-- Sub-tab switcher: Clarity / Page --}}
        <div class="flex gap-1 bg-neutral-100 dark:bg-neutral-900 rounded-lg p-1 w-fit">
            {{-- Clarity sub-tab — disabled when project has no Clarity key --}}
            <button
                wire:click="$set('aiSubTab', 'clarity')"
                @disabled(!$this->hasClarityKey)
                @class([
                    'px-4 py-2 text-sm font-medium rounded-md transition-colors',
                    'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-sm' => $aiSubTab === 'clarity',
                    'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300' => $aiSubTab !== 'clarity' && $this->hasClarityKey,
                    'text-neutral-300 dark:text-neutral-600 cursor-not-allowed' => !$this->hasClarityKey,
                ])
            >
                <span class="flex items-center gap-2">
                    <x-ui.icon name="chart-line-up" class="size-4" />
                    {{ __('Clarity') }}
                </span>
            </button>

            <button
                wire:click="$set('aiSubTab', 'page')"
                @class([
                    'px-4 py-2 text-sm font-medium rounded-md transition-colors',
                    'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-sm' => $aiSubTab === 'page',
                    'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300' => $aiSubTab !== 'page',
                ])
            >
                <span class="flex items-center gap-2">
                    <x-ui.icon name="browser" class="size-4" />
                    {{ __('Page') }}
                </span>
            </button>
        </div>

        {{-- Render the active sub-tab component --}}
        @if ($aiSubTab === 'clarity')
            <livewire:clarity-report-tab />
        @else
            <livewire:page-report-tab />
        @endif
    @endif

    {{-- Manual Report Form --}}
    @if ($activeTab === 'manual')
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
    @endif

    {{-- Recent Reports --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <x-ui.heading level="h3" size="md">{{ __('Recent Reports') }}</x-ui.heading>
            <x-ui.button variant="outline" color="neutral" size="sm" href="/reports" icon="list">
                {{ __('View All') }}
            </x-ui.button>
        </div>

        @if ($recentReports->isEmpty())
            <x-ui.card>
                <x-ui.empty>
                    <x-ui.empty.contents>
                        <x-ui.icon name="book-bookmark" class="size-10 text-neutral-300 dark:text-neutral-600" />
                        <x-ui.text>{{ __('No reports yet. Request an AI report or write your own above.') }}</x-ui.text>
                    </x-ui.empty.contents>
                </x-ui.empty>
            </x-ui.card>
        @else
            <div class="space-y-3">
                @foreach ($recentReports as $report)
                    <x-ui.card size="full" class="hover:border-neutral-300 dark:hover:border-neutral-600 transition-colors">
                        <a href="/reports/{{ $report->id }}" class="block">
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        @if ($report->is_ai)
                                            <x-ui.icon name="robot" class="size-4 text-blue-500 shrink-0" />
                                        @else
                                            <x-ui.icon name="user" class="size-4 text-violet-500 shrink-0" />
                                        @endif
                                        <span class="text-sm font-medium text-neutral-900 dark:text-neutral-100 truncate">{{ $report->title }}</span>
                                    </div>
                                    <div class="flex items-center gap-3 text-xs text-neutral-500 dark:text-neutral-400">
                                        @if ($report->preset)
                                            <span class="inline-flex items-center gap-1">
                                                @if ($report->contextPreset)
                                                    <x-ui.icon :name="$report->contextPreset->icon" class="size-3" style="color: {{ $report->contextPreset->label_color }}" />
                                                    {{ $report->contextPreset->localizedName() }}
                                                @else
                                                    <x-ui.icon name="tag" class="size-3" />
                                                    {{ Str::title(str_replace('-', ' ', $report->preset)) }}
                                                @endif
                                            </span>
                                        @endif
                                        @if ($report->aspect_date_from)
                                            <span class="inline-flex items-center gap-1">
                                                <x-ui.icon name="calendar" class="size-3" />
                                                {{ $report->aspect_date_from->format('M d') }} - {{ $report->aspect_date_to->format('M d, Y') }}
                                            </span>
                                        @endif
                                        @if ($report->page_url)
                                            <span class="inline-flex items-center gap-1 truncate max-w-48">
                                                <x-ui.icon name="link" class="size-3 shrink-0" />
                                                {{ $report->page_url }}
                                            </span>
                                        @endif
                                        <span>{{ $report->created_at->diffForHumans() }}</span>
                                    </div>
                                </div>
                                <x-ui.badge size="sm" color="{{ $report->status->color() }}">{{ $report->status->label() }}</x-ui.badge>
                            </div>
                        </a>
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </div>
</div>
