<?php

use App\Modules\Ai\Contexts\Enums\AiContextType;
use App\Modules\Ai\Contexts\Enums\ContextTag;
use App\Modules\Ai\Contexts\Models\AiContext;
use App\Modules\Projects\Models\Project;
use App\Modules\Reports\Enums\ReportStatusEnum;
use App\Modules\Reports\Jobs\GenerateAiReport;
use App\Modules\Reports\Models\Report;
use App\Modules\Users\Enums\PermissionEnum;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component {

    public string $preset = '';
    public string $customPrompt = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $reportLanguage = 'hu';

    public bool $showPresetPreview = false;
    public string $presetPreviewContent = '';
    public string $presetPreviewName = '';

    public function mount(): void
    {
        // GA data is more meaningful over longer windows than Clarity's typical 1-7 day scope.
        $this->dateFrom = now()->subDays(29)->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->reportLanguage = session('locale', config('app.locale', 'hu'));
    }

    public function getPresetsProperty()
    {
        return AiContext::active()
            ->ofType(AiContextType::PRESET)
            ->whereJsonContains('tags', ContextTag::GA->value)
            ->ordered()
            ->get();
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

        $project = Project::current();
        if (!$project) {
            $this->addError('project', __('Please select a project first.'));
            return;
        }

        // Re-check the action-level permission at submit time. Page mount
        // already gated VIEW_GOOGLE_ANALYTICS; this protects against direct
        // wire:click attempts that bypass the page mount.
        abort_unless(auth()->user()->can('permission', [PermissionEnum::CREATE_REPORT, $project]), 403);

        if (!$project->hasGoogleAnalytics()) {
            $this->addError('project', __('No Google Analytics property configured for this project.'));
            return;
        }

        $presetModel = AiContext::where('slug', $this->preset)->first();
        $presetTitle = $presetModel?->name ?? Str::title(str_replace('-', ' ', $this->preset));

        $report = Report::create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'title' => $presetTitle . ' - ' . \Carbon\Carbon::parse($this->dateFrom)->format('M d') . ' to ' . \Carbon\Carbon::parse($this->dateTo)->format('M d, Y'),
            'content' => null,
            'is_ai' => true,
            'preset' => $this->preset,
            'custom_prompt' => $this->customPrompt ?: null,
            'include_heatmaps' => false,
            'include_ga' => true,
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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <x-ui.card size="full">
            <div class="flex items-center gap-2 mb-4">
                <x-ui.icon name="chart-bar" class="size-5 text-amber-500" />
                <x-ui.heading level="h3" size="md">{{ __('Google Analytics Report') }}</x-ui.heading>
            </div>

            <form wire:submit="requestAiReport" class="space-y-5">
                <x-ui.field required>
                    <x-ui.label>{{ __('Report Preset') }}</x-ui.label>
                    <x-reports.preset-grid
                        :presets="$this->presets"
                        :selected="$preset"
                        :emptyMessage="__('No GA presets available. Create a preset with the Google Analytics tag in settings.')"
                    />
                    <x-ui.error name="preset" />
                </x-ui.field>

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
                    <x-ui.button type="submit" color="amber" icon="paper-plane-tilt">
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
            accent="amber"
        />
    </div>
</div>
