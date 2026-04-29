<?php

use Livewire\Component;
use App\Modules\Ai\Contexts\Enums\AiContextType;
use App\Modules\Ai\Contexts\Enums\ContextTag;
use App\Modules\Ai\Contexts\Models\AiContext;
use App\Modules\Analytics\Clarity\Models\ClarityInsight;
use App\Modules\Projects\Models\Project;
use App\Modules\Reports\Models\Report;
use App\Modules\Reports\Enums\ReportStatusEnum;
use App\Modules\Reports\Jobs\GenerateAiReport;
use Illuminate\Support\Str;

new class extends Component {

    public string $preset = '';
    public string $pageUrl = '';
    public string $customPrompt = '';
    public string $reportLanguage = 'en';

    // Preset preview state
    public bool $showPresetPreview = false;
    public string $presetPreviewContent = '';
    public string $presetPreviewName = '';

    public function mount(): void
    {
        $this->reportLanguage = session('locale', config('app.locale', 'hu'));
    }

    /** Only presets tagged with "page_analyser". */
    public function getPresetsProperty()
    {
        return AiContext::active()
            ->ofType(AiContextType::PRESET)
            ->whereJsonContains('tags', ContextTag::PAGE_ANALYSER->value)
            ->ordered()
            ->get();
    }

    /** Unique page URLs from the latest PopularPages fetch for this project. */
    public function getProjectPagesProperty(): array
    {
        $projectId = session('current_project_id');
        if (!$projectId) return [];

        // Grab the most recent PopularPages insight for this project.
        $insight = ClarityInsight::where('project_id', $projectId)
            ->where('metric_name', 'PopularPages')
            ->orderByDesc('fetched_for')
            ->first();

        if (!$insight || empty($insight->data)) return [];

        return collect($insight->data)
            ->pluck('url')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function getHasClarityKeyProperty(): bool
    {
        return (bool) Project::current()?->hasClarityKey();
    }

    /** Fill the URL field when a page is clicked. */
    public function selectPage(string $url): void
    {
        $this->pageUrl = $url;
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

    public function requestPageReport(): void
    {
        $this->validate([
            'preset' => 'required|string',
            'pageUrl' => 'required|url',
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
            'title' => $presetTitle . ' - ' . parse_url($this->pageUrl, PHP_URL_HOST) . parse_url($this->pageUrl, PHP_URL_PATH),
            'content' => null,
            'is_ai' => true,
            'preset' => $this->preset,
            'custom_prompt' => $this->customPrompt ?: null,
            'page_url' => $this->pageUrl,
            'ai_model_name' => null,
            'status' => ReportStatusEnum::PENDING,
            'language' => $this->reportLanguage,
        ]);

        GenerateAiReport::dispatch($report);

        $this->redirectRoute('report.view', $report);
    }
};
?>

{{-- Page analysis report form — uses page_analyser-tagged presets + URL input + project page list --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <x-ui.card size="full">
            <div class="flex items-center gap-2 mb-4">
                <x-ui.icon name="browser" class="size-5 text-emerald-500" />
                <x-ui.heading level="h3" size="md">{{ __('Page Report') }}</x-ui.heading>
            </div>

            <form wire:submit="requestPageReport" class="space-y-5">
                {{-- URL Input --}}
                <x-ui.field required>
                    <x-ui.label>{{ __('Page URL') }}</x-ui.label>
                    <x-ui.input
                        type="url"
                        wire:model="pageUrl"
                        placeholder="https://example.com/page"
                        :invalid="$errors->has('pageUrl')"
                    />
                    <x-ui.error name="pageUrl" />
                </x-ui.field>

                {{-- Project Pages — populated from the latest PopularPages Clarity fetch --}}
                <div>
                    <x-ui.label class="mb-2">{{ __('Project Pages') }}</x-ui.label>

                    @if (!$this->hasClarityKey)
                        {{-- No Clarity key at all — cannot fetch pages --}}
                        <div class="p-3 rounded-box border border-dashed border-neutral-200 dark:border-neutral-800 text-center">
                            <x-ui.description>
                                {{ __('There are no pages in the current project. Add a Clarity key and fetch a snapshot to view them.') }}
                            </x-ui.description>
                        </div>
                    @elseif (empty($this->projectPages))
                        {{-- Clarity key exists but no pages fetched yet --}}
                        <div class="flex items-center justify-between gap-3 p-3 rounded-box border border-dashed border-neutral-200 dark:border-neutral-800">
                            <x-ui.description>{{ __('No pages to display. Fetch data to get a list.') }}</x-ui.description>
                            <livewire:clarity-fetch-button />
                        </div>
                    @else
                        {{-- Clickable page list --}}
                        <div class="max-h-48 overflow-y-auto rounded-box border border-neutral-200 dark:border-neutral-800 divide-y divide-neutral-200 dark:divide-neutral-800">
                            @foreach ($this->projectPages as $url)
                                <button
                                    type="button"
                                    wire:click="selectPage('{{ $url }}')"
                                    @class([
                                        'w-full text-left px-3 py-2 text-sm transition-colors truncate',
                                        'bg-blue-50 dark:bg-blue-950/30 text-blue-700 dark:text-blue-300' => $pageUrl === $url,
                                        'text-neutral-600 dark:text-neutral-400 hover:bg-neutral-50 dark:hover:bg-neutral-900/50' => $pageUrl !== $url,
                                    ])
                                    title="{{ $url }}"
                                >
                                    {{ $url }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Preset Selection --}}
                <x-ui.field required>
                    <x-ui.label>{{ __('Report Preset') }}</x-ui.label>
                    @if ($this->presets->isEmpty())
                        <div class="p-3 rounded-box border border-dashed border-neutral-200 dark:border-neutral-800 text-center">
                            <x-ui.description>{{ __('No presets available. Create a preset with the Page Analyser tag.') }}</x-ui.description>
                        </div>
                    @else
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
                    @endif
                    <x-ui.error name="preset" />
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
                    <x-ui.button type="submit" color="emerald" icon="paper-plane-tilt">
                        {{ __('Request Report') }}
                    </x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>

    {{-- Preset Preview Sidebar --}}
    <div class="lg:col-span-1">
        @if ($showPresetPreview)
            <x-ui.card size="full" class="border-l-4 border-l-emerald-500 sticky top-6">
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
