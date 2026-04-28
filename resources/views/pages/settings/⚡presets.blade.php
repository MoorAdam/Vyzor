<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use App\Modules\Ai\Contexts\Enums\AiContextType;
use App\Modules\Ai\Contexts\Enums\ContextTag;
use App\Modules\Ai\Contexts\Models\AiContext;
use App\Modules\Users\Enums\PermissionEnum;

new #[Layout('layouts.app')] class extends Component {

    // Form fields for create/edit
    public ?int $editingId = null;
    public string $formName = '';
    public string $formDescription = '';
    public string $formType = 'preset';
    public array $formModels = ['all'];
    public array $formTags = [];
    public string $formLabelColor = '#3b82f6';
    public string $formIcon = 'file-text';
    public string $formContext = '';
    public int $formSortOrder = 0;
    public bool $formIsActive = true;

    public bool $showForm = false;
    public string $filterType = '';

    // When true, hides delete and disable buttons for non-preset contexts (system, instruction).
    // Simplified safeguard to prevent accidental modification of core contexts — will be reworked later.
    public bool $protectNonPresets = true;

    public function mount(): void
    {
        abort_unless(auth()->user()->can('permission', PermissionEnum::VIEW_CONTEXTS), 403);
    }

    public function openCreateForm(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function editPreset(int $id): void
    {
        $context = AiContext::findOrFail($id);
        $this->editingId = $context->id;
        $this->formName = $context->name;
        $this->formDescription = $context->description ?? '';
        $this->formType = $context->type->value;
        $this->formModels = $context->models ?? ['all'];
        $this->formTags = $context->tags ?? [];
        $this->formLabelColor = $context->label_color ?? '#3b82f6';
        $this->formIcon = $context->icon ?? 'file-text';
        $this->formContext = $context->context;
        $this->formSortOrder = $context->sort_order;
        $this->formIsActive = $context->is_active;
        $this->showForm = true;
    }

    public function savePreset(): void
    {
        abort_unless(auth()->user()->can('permission', $this->editingId ? PermissionEnum::EDIT_CONTEXTS : PermissionEnum::ADD_CONTEXTS), 403);
        $this->validate([
            'formName' => 'required|string|max:255',
            'formDescription' => 'nullable|string|max:500',
            'formType' => 'required|string|in:preset,system,instruction',
            'formModels' => 'required|array|min:1',
            'formTags' => 'nullable|array',
            'formLabelColor' => 'required|string|max:7',
            'formIcon' => 'required|string|max:100',
            'formContext' => 'required|string',
            'formSortOrder' => 'integer|min:0',
        ]);

        $data = [
            'name' => $this->formName,
            'description' => $this->formDescription ?: null,
            'type' => $this->formType,
            'models' => $this->formModels,
            'tags' => $this->formTags ?: null,
            'label_color' => $this->formLabelColor,
            'icon' => $this->formIcon,
            'context' => $this->formContext,
            'sort_order' => $this->formSortOrder,
            'is_active' => $this->formIsActive,
        ];

        if ($this->editingId) {
            AiContext::findOrFail($this->editingId)->update($data);
            session()->flash('success', __('Context updated successfully.'));
        } else {
            AiContext::create($data);
            session()->flash('success', __('Context created successfully.'));
        }

        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $context = AiContext::findOrFail($id);
        $context->update(['is_active' => !$context->is_active]);
    }

    public function deletePreset(int $id): void
    {
        abort_unless(auth()->user()->can('permission', PermissionEnum::DELETE_CONTEXTS), 403);
        AiContext::findOrFail($id)->delete();
        session()->flash('success', __('Context deleted.'));
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function toggleTag(string $tag): void
    {
        if (in_array($tag, $this->formTags)) {
            $this->formTags = array_values(array_diff($this->formTags, [$tag]));
        } else {
            $this->formTags[] = $tag;
        }
    }

    public function toggleModel(string $model): void
    {
        if ($model === 'all') {
            $this->formModels = ['all'];
            return;
        }

        $this->formModels = array_values(array_diff($this->formModels, ['all']));

        if (in_array($model, $this->formModels)) {
            $this->formModels = array_values(array_diff($this->formModels, [$model]));
        } else {
            $this->formModels[] = $model;
        }

        if (empty($this->formModels)) {
            $this->formModels = ['all'];
        }
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->formName = '';
        $this->formDescription = '';
        $this->formType = 'preset';
        $this->formModels = ['all'];
        $this->formTags = [];
        $this->formLabelColor = '#3b82f6';
        $this->formIcon = 'file-text';
        $this->formContext = '';
        $this->formSortOrder = 0;
        $this->formIsActive = true;
        $this->showForm = false;
        $this->resetValidation();
    }

    public function with(): array
    {
        $query = AiContext::ordered();

        if ($this->filterType) {
            $query->ofType(AiContextType::from($this->filterType));
        }

        return [
            'contexts' => $query->get(),
            'types' => AiContextType::cases(),
            'tags' => ContextTag::cases(),
            'providers' => collect(config('ai.providers', []))
                ->filter(fn($provider) => !empty($provider['key']))
                ->keys()
                ->all(),
        ];
    }
};
?>

<div class="p-6 space-y-6">

    @if (session('success'))
        <div class="rounded-lg bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 p-4">
            <div class="flex items-center gap-2">
                <x-ui.icon name="check-circle" class="size-5 text-green-600 dark:text-green-400" />
                <span class="text-sm text-green-700 dark:text-green-300">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    {{-- Context Manager Section --}}
    <div>
        <div class="flex items-center justify-between mb-4">
            <div>
                <x-ui.heading level="h2" size="lg">{{ __('AI Contexts') }}</x-ui.heading>
                <x-ui.description class="mt-1">{{ __('Configure the contexts and instructions that shape AI behaviour in reports.') }}</x-ui.description>
            </div>
            @if (!$showForm)
                <x-ui.button color="blue" icon="plus" wire:click="openCreateForm" :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::ADD_CONTEXTS)">
                    {{ __('New Context') }}
                </x-ui.button>
            @endif
        </div>

        {{-- Type Filter --}}
        @if (!$showForm)
            <div class="flex gap-1 bg-neutral-100 dark:bg-neutral-900 rounded-lg p-1 w-fit mb-4">
                <button
                    wire:click="$set('filterType', '')"
                    class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors {{ $filterType === '' ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-sm' : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300' }}"
                >
                    {{ __('All') }}
                </button>
                @foreach ($types as $type)
                    <button
                        wire:click="$set('filterType', '{{ $type->value }}')"
                        class="px-3 py-1.5 text-xs font-medium rounded-md transition-colors {{ $filterType === $type->value ? 'bg-white dark:bg-neutral-800 text-neutral-900 dark:text-neutral-100 shadow-sm' : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300' }}"
                    >
                        {{ $type->label() }}
                    </button>
                @endforeach
            </div>
        @endif

        {{-- Create / Edit Form --}}
        @if ($showForm)
            <x-ui.card size="full" class="mb-6">
                <div class="flex items-center gap-2 mb-5">
                    <x-ui.icon name="{{ $editingId ? 'pencil-simple' : 'plus-circle' }}" class="size-5 text-blue-500" />
                    <x-ui.heading level="h3" size="md">{{ $editingId ? __('Edit Context') : __('New Context') }}</x-ui.heading>
                </div>

                <form wire:submit="savePreset" class="space-y-5">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                        {{-- Left column --}}
                        <div class="space-y-5">
                            <x-ui.field required>
                                <x-ui.label>{{ __('Name') }}</x-ui.label>
                                <x-ui.input wire:model="formName" :placeholder="__('e.g. Traffic Overview')" :invalid="$errors->has('formName')" />
                                <x-ui.error name="formName" />
                            </x-ui.field>

                            <x-ui.field>
                                <x-ui.label>{{ __('Description') }}</x-ui.label>
                                <x-ui.input wire:model="formDescription" :placeholder="__('Short description of what this context does...')" :invalid="$errors->has('formDescription')" />
                                <x-ui.error name="formDescription" />
                            </x-ui.field>

                            <div class="grid grid-cols-2 gap-4">
                                <x-ui.field required>
                                    <x-ui.label>{{ __('Type') }}</x-ui.label>
                                    <select
                                        wire:model.live="formType"
                                        class="w-full rounded-box border border-black/10 dark:border-white/15 bg-white dark:bg-neutral-900 px-3 py-2 text-sm text-neutral-800 dark:text-neutral-300 focus:ring-2 focus:ring-neutral-900/15 dark:focus:ring-neutral-100/15 focus:outline-none shadow-xs"
                                    >
                                        @foreach ($types as $type)
                                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                        @endforeach
                                    </select>
                                    <x-ui.description class="mt-1">
                                        @if ($formType === 'preset')
                                            {{ __('Selectable report templates shown when creating reports.') }}
                                        @elseif ($formType === 'system')
                                            {{ __("Core instructions that define the AI agent's role and behaviour.") }}
                                        @else
                                            {{ __('Additional instructions injected based on report conditions.') }}
                                        @endif
                                    </x-ui.description>
                                </x-ui.field>

                                <x-ui.field>
                                    <x-ui.label>{{ __('Sort Order') }}</x-ui.label>
                                    <x-ui.input type="number" wire:model="formSortOrder" min="0" />
                                </x-ui.field>
                            </div>

                            {{-- Models --}}
                            <x-ui.field required>
                                <x-ui.label>{{ __('AI Models') }}</x-ui.label>
                                <x-ui.description class="mb-2">{{ __('Which AI providers should use this context.') }}</x-ui.description>
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        wire:click="toggleModel('all')"
                                        @class([
                                            'px-3 py-1.5 text-xs font-medium rounded-full border transition-colors',
                                            'bg-blue-50 dark:bg-blue-950/30 border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-300' => in_array('all', $formModels),
                                            'border-black/10 dark:border-white/15 text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300' => !in_array('all', $formModels),
                                        ])
                                    >
                                        {{ __('All Models') }}
                                    </button>
                                    @foreach ($providers as $provider)
                                        <button
                                            type="button"
                                            wire:click="toggleModel('{{ $provider }}')"
                                            @class([
                                                'px-3 py-1.5 text-xs font-medium rounded-full border transition-colors',
                                                'bg-blue-50 dark:bg-blue-950/30 border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-300' => !in_array('all', $formModels) && in_array($provider, $formModels),
                                                'border-black/10 dark:border-white/15 text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300' => in_array('all', $formModels) || !in_array($provider, $formModels),
                                            ])
                                        >
                                            {{ ucfirst($provider) }}
                                        </button>
                                    @endforeach
                                </div>
                                <x-ui.error name="formModels" />
                            </x-ui.field>

                            {{-- Tags --}}
                            <x-ui.field>
                                <x-ui.label>{{ __('Tags') }}</x-ui.label>
                                <x-ui.description class="mb-2">{{ __('Which features should use this context.') }}</x-ui.description>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($tags as $tag)
                                        <button
                                            type="button"
                                            wire:click="toggleTag('{{ $tag->value }}')"
                                            @class([
                                                'px-3 py-1.5 text-xs font-medium rounded-full border transition-colors',
                                                'bg-blue-50 dark:bg-blue-950/30 border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-300' => in_array($tag->value, $formTags),
                                                'border-black/10 dark:border-white/15 text-neutral-500 hover:text-neutral-700 dark:hover:text-neutral-300' => !in_array($tag->value, $formTags),
                                            ])
                                        >
                                            {{ $tag->label() }}
                                        </button>
                                    @endforeach
                                </div>
                            </x-ui.field>

                            <div class="grid grid-cols-3 gap-4">
                                <x-ui.field required>
                                    <x-ui.label>{{ __('Color') }}</x-ui.label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            type="color"
                                            wire:model.live="formLabelColor"
                                            class="size-10 rounded-field border border-black/10 dark:border-white/15 cursor-pointer bg-transparent p-0.5"
                                        />
                                        <x-ui.input wire:model.live="formLabelColor" class="font-mono" />
                                    </div>
                                </x-ui.field>

                                <x-ui.field required>
                                    <x-ui.label>{{ __('Icon') }}</x-ui.label>
                                    <x-ui.input wire:model="formIcon" :placeholder="__('e.g. chart-line-up')" :invalid="$errors->has('formIcon')" />
                                    <x-ui.error name="formIcon" />
                                </x-ui.field>
                            </div>

                            {{-- Preview card --}}
                            <div>
                                <x-ui.label class="mb-2">{{ __('Preview') }}</x-ui.label>
                                <div
                                    class="flex items-center gap-3 p-3 rounded-box border border-black/10 dark:border-white/10"
                                    style="border-color: {{ $formLabelColor }}; background-color: {{ $formLabelColor }}10; box-shadow: 0 0 0 1px {{ $formLabelColor }}80"
                                >
                                    <div
                                        class="shrink-0 flex items-center justify-center size-9 rounded-field"
                                        style="background-color: {{ $formLabelColor }}15; color: {{ $formLabelColor }}"
                                    >
                                        <x-ui.icon :name="$formIcon" class="size-5" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <span class="block text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ $formName ?: __('Context Name') }}</span>
                                        <span class="block text-xs text-neutral-400 mt-0.5">{{ $formDescription ?: __('Description goes here...') }}</span>
                                    </div>
                                </div>
                            </div>

                            <x-ui.field>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <x-ui.checkbox wire:model="formIsActive" />
                                    <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __('Active') }}</span>
                                    <span class="text-xs text-neutral-400">{{ __('(inactive contexts won\'t be used in reports)') }}</span>
                                </label>
                            </x-ui.field>
                        </div>

                        {{-- Right column: Context --}}
                        <div>
                            <x-ui.field required class="h-full flex! flex-col!">
                                <x-ui.label>{{ __('Context / Prompt') }}</x-ui.label>
                                <x-ui.description class="mb-2">{{ __('The instructions sent to the AI. Supports markdown.') }}</x-ui.description>
                                <textarea
                                    wire:model="formContext"
                                    placeholder="# Report Title&#10;&#10;Describe what the AI should analyse...&#10;&#10;## Focus Areas&#10;- Area 1&#10;- Area 2&#10;&#10;## Expected Output&#10;Describe the expected format..."
                                    @class([
                                        'w-full flex-1 min-h-64 rounded-box px-3 py-2 text-sm font-mono text-neutral-800 dark:text-neutral-300 placeholder-neutral-400 bg-white dark:bg-neutral-900 focus:ring-2 focus:outline-none shadow-xs resize-y',
                                        'border border-black/10 dark:border-white/15 focus:ring-neutral-900/15 dark:focus:ring-neutral-100/15 focus:border-black/15 dark:focus:border-white/20' => !$errors->has('formContext'),
                                        'border-2 border-red-600/30 focus:border-red-600/30 focus:ring-red-600/20 dark:border-red-400/30 dark:focus:border-red-400/30 dark:focus:ring-red-400/20' => $errors->has('formContext'),
                                    ])
                                ></textarea>
                                <x-ui.error name="formContext" />
                            </x-ui.field>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <x-ui.button type="button" variant="outline" color="neutral" wire:click="cancelForm">
                            {{ __('Cancel') }}
                        </x-ui.button>
                        <x-ui.button type="submit" color="blue" icon="floppy-disk">
                            {{ $editingId ? __('Update Context') : __('Create Context') }}
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @endif

        {{-- Contexts List --}}
        @if ($contexts->isEmpty())
            <x-ui.card>
                <x-ui.empty>
                    <x-ui.empty.contents>
                        <x-ui.icon name="file-text" class="size-10 text-neutral-300 dark:text-neutral-600" />
                        <x-ui.text>{{ __('No contexts yet. Create your first one to get started.') }}</x-ui.text>
                        <x-ui.button variant="outline" color="neutral" wire:click="openCreateForm" class="mt-2" icon="plus">
                            {{ __('Create Context') }}
                        </x-ui.button>
                    </x-ui.empty.contents>
                </x-ui.empty>
            </x-ui.card>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach ($contexts as $context)
                    <x-ui.card size="full" @class(['opacity-50' => !$context->is_active])>
                        <div class="flex items-start gap-3">
                            <div
                                class="shrink-0 flex items-center justify-center size-10 rounded-field mt-0.5"
                                style="background-color: {{ $context->label_color ?? '#6b7280' }}15; color: {{ $context->label_color ?? '#6b7280' }}"
                            >
                                <x-ui.icon :name="$context->icon ?? 'file-text'" class="size-5" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-neutral-900 dark:text-neutral-100 truncate">{{ $context->name }}</span>
                                    <x-ui.badge size="sm" color="{{ $context->type->color() }}">{{ $context->type->label() }}</x-ui.badge>
                                    @foreach ($context->tags ?? [] as $tagValue)
                                        @if ($tagEnum = \App\Modules\Ai\Contexts\Enums\ContextTag::tryFrom($tagValue))
                                            <x-ui.badge size="sm" color="{{ $tagEnum->color() }}">{{ $tagEnum->label() }}</x-ui.badge>
                                        @endif
                                    @endforeach
                                    @if (!$context->is_active)
                                        <x-ui.badge size="sm" color="neutral">{{ __('Inactive') }}</x-ui.badge>
                                    @endif
                                </div>
                                @if ($context->description)
                                    <x-ui.description class="mt-0.5 line-clamp-2">{{ $context->description }}</x-ui.description>
                                @endif
                                <div class="flex items-center gap-1 mt-2 text-xs text-neutral-400">
                                    <x-ui.icon name="sort-ascending" class="size-3" />
                                    <span>{{ $context->sort_order }}</span>
                                    <span class="mx-1">-</span>
                                    <span class="font-mono">{{ $context->slug }}</span>
                                    @if ($context->models && !in_array('all', $context->models))
                                        <span class="mx-1">-</span>
                                        <span>{{ implode(', ', $context->models) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <x-ui.separator class="my-3" />

                        <div class="flex items-center justify-between">
                            @php $isProtected = $protectNonPresets && $context->type !== App\Modules\Ai\Contexts\Enums\AiContextType::PRESET; @endphp
                            <div class="flex items-center gap-1">
                                <x-ui.button size="xs" variant="outline" color="neutral" icon="pencil-simple" wire:click="editPreset({{ $context->id }})" :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::EDIT_CONTEXTS)">
                                    {{ __('Edit') }}
                                </x-ui.button>
                                @unless ($isProtected)
                                    <x-ui.button
                                        size="xs"
                                        variant="outline"
                                        :color="$context->is_active ? 'neutral' : 'blue'"
                                        :icon="$context->is_active ? 'eye-slash' : 'eye'"
                                        wire:click="toggleActive({{ $context->id }})"
                                    >
                                        {{ $context->is_active ? __('Disable') : __('Enable') }}
                                    </x-ui.button>
                                @endunless
                            </div>
                            @unless ($isProtected)
                                <button
                                    wire:click="deletePreset({{ $context->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete \':name\'? This cannot be undone.', ['name' => $context->name]) }}"
                                    class="text-neutral-400 hover:text-red-500 transition-colors p-1"
                                    :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::DELETE_CONTEXTS)"
                                >
                                    <x-ui.icon name="trash" class="size-4" />
                                </button>
                            @endunless
                        </div>
                    </x-ui.card>
                @endforeach
            </div>
        @endif
    </div>
</div>
