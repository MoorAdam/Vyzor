<?php

use App\Modules\Ai\Contexts\Models\AiContext;
use App\Modules\Reports\Enums\ReportStatusEnum;
use App\Modules\Reports\Models\Report;
use Livewire\Component;

/**
 * Recent reports list — self-polling when any visible row is still pending /
 * generating. Filterable by AiContext tag (e.g. 'ga' shows only reports whose
 * preset is GA-tagged); pass null to show every report for the current project.
 */
new class extends Component {

    /** ContextTag value to filter presets by, or null for unfiltered. */
    public ?string $tag = null;

    public int $limit = 5;

    public string $heading = '';

    public string $emptyMessage = '';

    public ?string $viewAllUrl = '/reports';

    public function mount(
        ?string $tag = null,
        int $limit = 5,
        ?string $heading = null,
        ?string $emptyMessage = null,
        ?string $viewAllUrl = '/reports',
    ): void {
        $this->tag          = $tag;
        $this->limit        = $limit;
        $this->heading      = $heading ?? __('Recent Reports');
        $this->emptyMessage = $emptyMessage ?? __('No reports yet.');
        $this->viewAllUrl   = $viewAllUrl;
    }

    public function getReportsProperty()
    {
        $projectId = session('current_project_id');
        if (! $projectId) {
            return collect();
        }

        $query = Report::forProject($projectId)->latest()->limit($this->limit);

        if ($this->tag) {
            $slugs = AiContext::active()
                ->whereJsonContains('tags', $this->tag)
                ->pluck('slug');

            $query->whereIn('preset', $slugs);
        }

        return $query->get();
    }

    public function getHasPendingProperty(): bool
    {
        return $this->reports->contains(
            fn ($r) => in_array($r->status, [ReportStatusEnum::PENDING, ReportStatusEnum::GENERATING], true),
        );
    }
};
?>

<div @if ($this->hasPending) wire:poll.10s @endif>
    <div class="flex items-center justify-between mb-3">
        <x-ui.heading level="h3" size="md">{{ $heading }}</x-ui.heading>
        @if ($viewAllUrl)
            <x-ui.button variant="outline" color="neutral" size="sm" href="{{ $viewAllUrl }}" icon="list">
                {{ __('View All') }}
            </x-ui.button>
        @endif
    </div>

    @if ($this->reports->isEmpty())
        <x-ui.card>
            <x-ui.empty>
                <x-ui.empty.contents>
                    <x-ui.icon name="book-bookmark" class="size-10 text-neutral-300 dark:text-neutral-600" />
                    <x-ui.text>{{ $emptyMessage }}</x-ui.text>
                </x-ui.empty.contents>
            </x-ui.empty>
        </x-ui.card>
    @else
        <div class="space-y-3">
            @foreach ($this->reports as $report)
                <x-reports.report-card :report="$report" />
            @endforeach
        </div>
    @endif
</div>
