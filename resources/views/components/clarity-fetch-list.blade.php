@props([
    'fetches' => null,
    'selectable' => false,
    'selected' => null,
    'wireClick' => 'selectFetch',
    'emptyMessage' => null,
])

@php
    $fetches = $fetches ?? collect();
    $keyFor = fn ($f) => $f->fetched_for->toIso8601String() . '|' . $f->date_from->toDateString() . '|' . $f->date_to->toDateString();
@endphp

@if ($fetches->isEmpty())
    <div class="p-3 rounded-box border border-dashed border-neutral-200 dark:border-neutral-800 text-center">
        <x-ui.description>{{ $emptyMessage ?? __('No previous fetches for this project.') }}</x-ui.description>
    </div>
@else
    <ul class="divide-y divide-neutral-200 dark:divide-neutral-800 border border-neutral-200 dark:border-neutral-800 rounded-box overflow-hidden">
        @foreach ($fetches as $fetch)
            @php
                $key = $keyFor($fetch);
                $isSelected = $selectable && $selected === $key;
            @endphp
            @if ($selectable)
                <li>
                    <button
                        type="button"
                        wire:click="{{ $wireClick }}('{{ $key }}')"
                        @class([
                            'w-full flex items-center justify-between gap-3 p-2.5 text-sm transition-colors text-left',
                            'bg-emerald-50 dark:bg-emerald-900/20' => $isSelected,
                            'hover:bg-neutral-50 dark:hover:bg-neutral-800/40' => !$isSelected,
                        ])
                    >
                        <div class="flex items-center gap-2 min-w-0">
                            <x-ui.icon
                                :name="$isSelected ? 'check' : 'clock'"
                                @class([
                                    'size-4 shrink-0',
                                    'text-emerald-500' => $isSelected,
                                    'text-neutral-400' => !$isSelected,
                                ])
                            />
                            <span class="text-neutral-700 dark:text-neutral-300 truncate">
                                {{ $fetch->fetched_for->format('M d, Y H:i') }}
                            </span>
                        </div>
                        <span class="text-xs text-neutral-500 dark:text-neutral-400 whitespace-nowrap">
                            {{ $fetch->date_from->format('M d') }} → {{ $fetch->date_to->format('M d') }}
                        </span>
                    </button>
                </li>
            @else
                <li class="flex items-center justify-between gap-3 p-2.5 text-sm">
                    <div class="flex items-center gap-2 min-w-0">
                        <x-ui.icon name="clock" class="size-4 text-neutral-400 shrink-0" />
                        <span class="text-neutral-700 dark:text-neutral-300 truncate">
                            {{ $fetch->fetched_for->format('M d, Y H:i') }}
                        </span>
                    </div>
                    <span class="text-xs text-neutral-500 dark:text-neutral-400 whitespace-nowrap">
                        {{ $fetch->date_from->format('M d') }} → {{ $fetch->date_to->format('M d') }}
                    </span>
                </li>
            @endif
        @endforeach
    </ul>
@endif
