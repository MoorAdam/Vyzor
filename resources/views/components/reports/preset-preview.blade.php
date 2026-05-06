@props([
    /** Whether the preview is open (true) or showing the placeholder (false). */
    'visible' => false,
    /** Preset name shown as the heading when visible. */
    'name' => '',
    /** Preset content (markdown source) shown in the <pre>. */
    'content' => '',
    /**
     * Accent color for the left border. One of: blue, amber, emerald, violet, rose, neutral.
     * Use a literal name so Tailwind can statically pick the class up.
     */
    'accent' => 'blue',
    /** Action name on the parent component to call when the user closes the preview. */
    'closeAction' => 'closePreview',
])

@php
    $accentClass = match ($accent) {
        'amber'   => 'border-l-amber-500',
        'emerald' => 'border-l-emerald-500',
        'violet'  => 'border-l-violet-500',
        'rose'    => 'border-l-rose-500',
        'neutral' => 'border-l-neutral-500',
        default   => 'border-l-blue-500',
    };
@endphp

@if ($visible)
    <x-ui.card size="full" :class="$accentClass . ' border-l-4 sticky top-6'">
        <div class="flex items-center justify-between mb-3">
            <x-ui.heading level="h4" size="sm">{{ $name }}</x-ui.heading>
            <button wire:click="{{ $closeAction }}" class="text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300">
                <x-ui.icon name="x" class="size-4" />
            </button>
        </div>
        <pre class="whitespace-pre-wrap text-xs text-neutral-700 dark:text-neutral-300 bg-neutral-50 dark:bg-neutral-900 rounded-lg p-3 overflow-auto max-h-96">{{ $content }}</pre>
    </x-ui.card>
@else
    <x-ui.card size="full" class="border-l-4 border-l-neutral-300 dark:border-l-neutral-700">
        <div class="flex flex-col items-center justify-center py-8 text-center">
            <x-ui.icon name="eye" class="size-8 text-neutral-300 dark:text-neutral-600 mb-3" />
            <x-ui.description>{{ __('Click the eye icon on a preset to preview its instructions.') }}</x-ui.description>
        </div>
    </x-ui.card>
@endif
