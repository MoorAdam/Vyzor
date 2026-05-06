@props([
    /** Collection<AiContext> — presets to render. */
    'presets',
    /** Slug of the currently selected preset (for highlight + eye button). */
    'selected' => '',
    /** Wire model name on the parent component (defaults to "preset"). */
    'model' => 'preset',
    /** Action name on the parent component for the preview button. Empty disables the button. */
    'previewAction' => 'previewPreset',
    /** Optional message shown when $presets is empty. Pass null to render nothing. */
    'emptyMessage' => null,
])

@if ($presets->isEmpty())
    @if ($emptyMessage !== null)
        <div class="p-3 rounded-box border border-dashed border-neutral-200 dark:border-neutral-800 text-center">
            <x-ui.description>{{ $emptyMessage }}</x-ui.description>
        </div>
    @endif
@else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach ($presets as $presetOption)
            <label
                class="relative flex items-center gap-3 p-3 rounded-box border border-black/10 dark:border-white/10 cursor-pointer transition-all hover:border-black/20 dark:hover:border-white/20"
                @if ($selected === $presetOption->slug)
                    style="border-color: {{ $presetOption->label_color }}; background-color: {{ $presetOption->label_color }}10; box-shadow: 0 0 0 1px {{ $presetOption->label_color }}80"
                @endif
            >
                <input type="radio" wire:model.live="{{ $model }}" value="{{ $presetOption->slug }}" class="sr-only" />
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
                @if ($selected === $presetOption->slug && $previewAction)
                    <button
                        type="button"
                        wire:click="{{ $previewAction }}('{{ $presetOption->slug }}')"
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
