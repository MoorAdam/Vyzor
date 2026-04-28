<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Modules\Reports\Models\Report;
use App\Modules\Users\Enums\PermissionEnum;

new #[Layout('layouts.app')] class extends Component {

    public function mount(): void
    {
        abort_unless(auth()->user()->can('permission', [PermissionEnum::CREATE_REPORT, \App\Modules\Projects\Models\Project::current()]), 403);
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
    @if ($recentReports->contains(fn ($r) => in_array($r->status, [\App\Modules\Reports\Enums\ReportStatusEnum::PENDING, \App\Modules\Reports\Enums\ReportStatusEnum::GENERATING])))
        wire:poll.10s
    @endif
    class="p-6 space-y-6"
>
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
                        <x-ui.text>{{ __('No reports yet. Request an AI report above.') }}</x-ui.text>
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
                                                    {{ $report->contextPreset->name }}
                                                @else
                                                    <x-ui.icon name="tag" class="size-3" />
                                                    {{ \Illuminate\Support\Str::title(str_replace('-', ' ', $report->preset)) }}
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
