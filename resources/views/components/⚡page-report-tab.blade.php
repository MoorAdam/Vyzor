<?php

use App\Modules\Ai\Contexts\Enums\AiContextType;
use App\Modules\Ai\Contexts\Enums\ContextTag;
use App\Modules\Ai\Contexts\Models\AiContext;
use App\Modules\Analytics\Clarity\Models\ClarityInsight;
use App\Modules\Projects\Models\Project;
use App\Modules\Reports\Enums\ReportStatusEnum;
use App\Modules\Reports\Jobs\GenerateAiReport;
use App\Modules\Reports\Models\Report;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component {

    public string $preset = '';
    public string $pageUrl = '';
    public string $customPrompt = '';
    public string $reportLanguage = 'en';

    public bool $showPresetPreview = false;
    public string $presetPreviewContent = '';
    public string $presetPreviewName = '';

    public function mount(): void
    {
        $this->reportLanguage = session('locale', config('app.locale', 'hu'));
    }

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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <x-ui.card size="full">
            <div class="flex items-center gap-2 mb-4">
                <x-ui.icon name="browser" class="size-5 text-emerald-500" />
                <x-ui.heading level="h3" size="md">{{ __('Page Report') }}</x-ui.heading>
            </div>

            <form wire:submit="requestPageReport" class="space-y-5">
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
                        <div class="p-3 rounded-box border border-dashed border-neutral-200 dark:border-neutral-800 text-center">
                            <x-ui.description>
                                {{ __('There are no pages in the current project. Add a Clarity key and fetch a snapshot to view them.') }}
                            </x-ui.description>
                        </div>
                    @elseif (empty($this->projectPages))
                        <div class="flex items-center justify-between gap-3 p-3 rounded-box border border-dashed border-neutral-200 dark:border-neutral-800">
                            <x-ui.description>{{ __('No pages to display. Fetch data to get a list.') }}</x-ui.description>
                            <livewire:clarity-fetch-button />
                        </div>
                    @else
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

                <x-ui.field required>
                    <x-ui.label>{{ __('Report Preset') }}</x-ui.label>
                    <x-reports.preset-grid
                        :presets="$this->presets"
                        :selected="$preset"
                        :emptyMessage="__('No presets available. Create a preset with the Page Analyser tag.')"
                    />
                    <x-ui.error name="preset" />
                </x-ui.field>

                <x-ui.field>
                    <x-ui.label>{{ __('Report Language') }}</x-ui.label>
                    <x-ui.radio.group wire:model.live="reportLanguage" direction="horizontal" variant="segmented">
                        <x-ui.radio.item value="en" label="English" />
                        <x-ui.radio.item value="hu" label="Magyar" />
                    </x-ui.radio.group>
                    <x-ui.description>{{ __('The AI will generate the report in the selected language.') }}</x-ui.description>
                </x-ui.field>

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

    <div class="lg:col-span-1">
        <x-reports.preset-preview
            :visible="$showPresetPreview"
            :name="$presetPreviewName"
            :content="$presetPreviewContent"
            accent="emerald"
        />
    </div>
</div>
