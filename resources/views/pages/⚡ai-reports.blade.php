<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\AiContextType;
use App\Models\AiContext;
use App\Models\Report;
use App\Models\Heatmap;
use App\ReportStatusEnum;
use App\Jobs\GenerateAiReport;
use Illuminate\Support\Str;

new #[Layout('layouts.app')] class extends Component {

    public string $preset = '';
    public string $customPrompt = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public bool $includeHeatmaps = false;
    public string $reportLanguage = 'en';

    // For manual report creation
    public string $manualTitle = '';
    public string $manualContent = '';

    // UI state
    public string $activeTab = 'ai';
    public bool $showPresetPreview = false;
    public string $presetPreviewContent = '';
    public string $presetPreviewName = '';

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(7)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->reportLanguage = session('locale', config('app.locale', 'en'));
    }

    #[On('current-project-changed')]
    public function onProjectChanged() {}

    public function getPresetsProperty()
    {
        return AiContext::active()->ofType(AiContextType::PRESET)->ordered()->get();
    }

    public function getAvailableHeatmapCountProperty(): int
    {
        $projectId = session('current_project_id');
        if (!$projectId) return 0;

        return Heatmap::where('project_id', $projectId)
            ->when($this->dateFrom, fn($q) => $q->where('date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->where('date', '<=', $this->dateTo))
            ->count();
    }

    public function previewPreset(string $slug): void
    {
        $preset = AiContext::where('slug', $slug)->first();
        if ($preset) {
            $this->presetPreviewContent = $preset->context;
            $this->presetPreviewName = $preset->localizedName();
            $this->showPresetPreview = true;
        }
    }

    public function closePreview(): void
    {
        $this->showPresetPreview = false;
        $this->presetPreviewContent = '';
    }

    public function requestAiReport(): void
    {
        $this->validate([
            'preset' => 'required|string',
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date|after_or_equal:dateFrom',
        ]);

        $projectId = session('current_project_id');
        if (!$projectId) {
            $this->addError('project', __('Please select a project first.'));
            return;
        }

        $presetModel = AiContext::where('slug', $this->preset)->first();
        $presetTitle = $presetModel?->name ?? Str::title(str_replace('-', ' ', $this->preset));

        $report = Report::create([
            'project_id' => $projectId,
            'user_id' => auth()->id(),
            'title' => $presetTitle . ' - ' . \Carbon\Carbon::parse($this->dateFrom)->format('M d') . ' to ' . \Carbon\Carbon::parse($this->dateTo)->format('M d, Y'),
            'content' => null,
            'is_ai' => true,
            'preset' => $this->preset,
            'custom_prompt' => $this->customPrompt ?: null,
            'include_heatmaps' => $this->includeHeatmaps,
            'aspect_date_from' => $this->dateFrom,
            'aspect_date_to' => $this->dateTo,
            'ai_model_name' => null,
            'status' => ReportStatusEnum::PENDING,
            'language' => $this->reportLanguage,
        ]);

        GenerateAiReport::dispatch($report);

        session()->flash('success', __('AI report requested. It will appear in the reports list once generated.'));

        $this->reset(['preset', 'customPrompt', 'includeHeatmaps']);
        $this->dateFrom = now()->subDays(7)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
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

        Report::create([
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

        session()->flash('success', __('Report created successfully.'));

        $this->reset(['manualTitle', 'manualContent']);
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

    {{-- Tab Switcher --}}
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

    {{-- AI Report Request Form --}}
    @if ($activeTab === 'ai')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <x-ui.card size="full">
                    <div class="flex items-center gap-2 mb-4">
                        <x-ui.icon name="robot" class="size-5 text-blue-500" />
                        <x-ui.heading level="h3" size="md">{{ __('Request AI Report') }}</x-ui.heading>
                    </div>

                    <form wire:submit="requestAiReport" class="space-y-5">
                        {{-- Preset Selection --}}
                        <x-ui.field required>
                            <x-ui.label>{{ __('Report Preset') }}</x-ui.label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach ($this->presets as $presetOption)
                                    <label
                                        class="relative flex items-center gap-3 p-3 rounded-box border border-black/10 dark:border-white/10 cursor-pointer transition-all hover:border-black/20 dark:hover:border-white/20"
                                        @if ($preset === $presetOption->slug)
                                            style="border-color: {{ $presetOption->label_color }}; background-color: {{ $presetOption->label_color }}10; box-shadow: 0 0 0 1px {{ $presetOption->label_color }}80"
                                        @endif
                                    >
                                        <input type="radio" wire:model.live="preset" value="{{ $presetOption->slug }}" class="sr-only" />
                                        <div
                                            class="shrink-0 flex items-center justify-center size-9 rounded-field"
                                            style="background-color: {{ $presetOption->label_color }}15; color: {{ $presetOption->label_color }}"
                                        >
                                            <x-ui.icon :name="$presetOption->icon" class="size-5" />
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <span class="block text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ $presetOption->localizedName() }}</span>
                                            <span class="block text-xs text-neutral-400 mt-0.5">{{ $presetOption->localizedDescription() }}</span>
                                        </div>
                                        @if ($preset === $presetOption->slug)
                                            <button
                                                type="button"
                                                wire:click="previewPreset('{{ $presetOption->slug }}')"
                                                class="shrink-0 text-neutral-400 hover:opacity-80 transition-colors"
                                                style="color: {{ $presetOption->label_color }}"
                                                :title="__('Preview preset')"
                                            >
                                                <x-ui.icon name="eye" class="size-4" />
                                            </button>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                            <x-ui.error name="preset" />
                        </x-ui.field>

                        {{-- Date Range --}}
                        <div class="grid grid-cols-2 gap-4">
                            <x-ui.field required>
                                <x-ui.label>{{ __('Date From') }}</x-ui.label>
                                <x-ui.input type="date" wire:model="dateFrom" :invalid="$errors->has('dateFrom')" />
                                <x-ui.error name="dateFrom" />
                            </x-ui.field>
                            <x-ui.field required>
                                <x-ui.label>{{ __('Date To') }}</x-ui.label>
                                <x-ui.input type="date" wire:model="dateTo" :invalid="$errors->has('dateTo')" />
                                <x-ui.error name="dateTo" />
                            </x-ui.field>
                        </div>

                        {{-- Include Heatmaps --}}
                        <x-ui.field>
                            <x-ui.checkbox
                                wire:model.live="includeHeatmaps"
                                :label="__('Include Heatmap Data') . ' ' . ($this->availableHeatmapCount > 0 ? '(' . $this->availableHeatmapCount . ' ' . __('available in date range') . ')' : '(' . __('none available in date range') . ')')"
                                :description="__('When enabled, click/tap heatmap data from Microsoft Clarity will be included for the AI to analyse user interaction patterns.')"
                            />
                        </x-ui.field>

                        {{-- Report Language --}}
                        <x-ui.field>
                            <x-ui.label>{{ __('Report Language') }}</x-ui.label>
                            <x-ui.radio.group wire:model.live="reportLanguage" direction="horizontal" variant="segmented">
                                <x-ui.radio.item value="en" label="English" />
                                <x-ui.radio.item value="hu" label="Magyar" />
                            </x-ui.radio.group>
                            <x-ui.description>{{ __('The AI will generate the report in the selected language.') }}</x-ui.description>
                        </x-ui.field>

                        {{-- Custom Prompt --}}
                        <x-ui.field>
                            <x-ui.label>{{ __('Additional Instructions') }} <span class="text-neutral-400 font-normal">({{ __('optional') }})</span></x-ui.label>
                            <textarea
                                wire:model="customPrompt"
                                rows="3"
                                placeholder="{{ __("Add specific topics or questions you'd like the AI to address...") }}"
                                class="w-full rounded-box border border-black/10 dark:border-white/15 bg-white dark:bg-neutral-900 px-3 py-2 text-sm text-neutral-800 dark:text-neutral-300 placeholder-neutral-400 focus:ring-2 focus:ring-neutral-900/15 dark:focus:ring-neutral-100/15 focus:border-black/15 dark:focus:border-white/20 focus:outline-none shadow-xs resize-y"
                            ></textarea>
                        </x-ui.field>

                        <div class="flex items-center justify-end pt-2">
                            <x-ui.button type="submit" color="blue" icon="paper-plane-tilt">
                                {{ __('Request Report') }}
                            </x-ui.button>
                        </div>
                    </form>
                </x-ui.card>
            </div>

            {{-- Preset Preview Sidebar --}}
            <div class="lg:col-span-1">
                @if ($showPresetPreview)
                    <x-ui.card size="full" class="border-l-4 border-l-blue-500 sticky top-6">
                        <div class="flex items-center justify-between mb-3">
                            <x-ui.heading level="h4" size="sm">{{ $presetPreviewName }}</x-ui.heading>
                            <button wire:click="closePreview" class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300">
                                <x-ui.icon name="x" class="size-4" />
                            </button>
                        </div>
                        <div class="prose prose-sm dark:prose-invert max-w-none text-neutral-600 dark:text-neutral-400">
                            <pre class="whitespace-pre-wrap text-xs bg-neutral-50 dark:bg-neutral-900 rounded-lg p-3 overflow-auto max-h-96">{{ $presetPreviewContent }}</pre>
                        </div>
                    </x-ui.card>
                @else
                    <x-ui.card size="full" class="border-l-4 border-l-neutral-300 dark:border-l-neutral-700">
                        <div class="flex flex-col items-center justify-center py-8 text-center">
                            <x-ui.icon name="eye" class="size-8 text-neutral-300 dark:text-neutral-600 mb-3" />
                            <x-ui.description>{{ __('Click the eye icon on a preset to preview its instructions.') }}</x-ui.description>
                        </div>
                    </x-ui.card>
                @endif
            </div>
        </div>
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
