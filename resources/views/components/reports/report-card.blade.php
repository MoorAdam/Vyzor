@props([
    /** App\Modules\Reports\Models\Report */
    'report',
])

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
