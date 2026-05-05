<?php

use Livewire\Component;
use App\Modules\Ai\Contexts\Enums\AiContextType;
use App\Modules\Ai\Contexts\Enums\ContextTag;
use App\Modules\Ai\Contexts\Models\AiContext;
use App\Modules\Analytics\Clarity\Heatmaps\Models\Heatmap;
use App\Modules\Reports\Models\Report;
use App\Modules\Reports\Enums\ReportStatusEnum;
use App\Modules\Reports\Jobs\GenerateAiReport;
use Illuminate\Support\Str;

new class extends Component {

    public string $preset = '';
    public string $customPrompt = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public bool $includeHeatmaps = false;
    public string $reportLanguage = 'en';

    // Preset preview state
    public bool $showPresetPreview = false;
    public string $presetPreviewContent = '';
    public string $presetPreviewName = '';

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(7)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->reportLanguage = session('locale', config('app.locale', 'hu'));
    }

    /** Only presets tagged with "clarity". */
    public function getPresetsProperty()
    {
        return AiContext::active()
            ->ofType(AiContextType::PRESET)
            ->whereJsonContains('tags', ContextTag::CLARITY->value)
            ->ordered()
            ->get();
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
            $this->presetPreviewName = $preset->name;
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

        $this->redirectRoute('report.view', $report);
    }
};
?>

{{-- Clarity report request form — uses clarity-tagged presets + date range + heatmap toggle --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <x-ui.card size="full">
            <div class="flex items-center gap-2 mb-4">
                <x-ui.icon name="robot" class="size-5 text-blue-500" />
                <x-ui.heading level="h3" size="md">{{ __('Clarity Report') }}</x-ui.heading>
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
                                    <span class="block text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ $presetOption->name }}</span>
                                    <span class="block text-xs text-neutral-400 mt-0.5">{{ $presetOption->description }}</span>
                                </div>
                                @if ($preset === $presetOption->slug)
                                    <button
                                        type="button"
                                        wire:click="previewPreset('{{ $presetOption->slug }}')"
                                        class="shrink-0 text-neutral-400 hover:opacity-80 transition-colors"
                                        style="color: {{ $presetOption->label_color }}"
                                        title="{{ __('Preview preset') }}"
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
                <pre class="whitespace-pre-wrap text-xs text-neutral-700 dark:text-neutral-300 bg-neutral-50 dark:bg-neutral-900 rounded-lg p-3 overflow-auto max-h-96">{{ $presetPreviewContent }}</pre>
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
