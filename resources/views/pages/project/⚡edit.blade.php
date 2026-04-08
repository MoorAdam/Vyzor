<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Attributes\On;
use App\Models\User;
use App\Models\Project;
use App\ProjectStatusEnum;
use App\UserTypeEnum;

new #[Layout('layouts.app')] class extends Component {
    public Project $project;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string')]
    public string $description = '';

    #[Validate('required|exists:users,id')]
    public ?string $customer_id = null;

    #[Validate('required|string')]
    public string $status = 'active';

    #[Validate('required|url|max:255')]
    public string $domain = '';

    #[Validate('nullable|string')]
    public string $clarity_api_key = '';

    public function mount(Project $project): void
    {
        abort_unless(auth()->user()->isAdmin() || $project->owner_id === auth()->id(), 403);

        $this->project = $project;
        $this->name = $project->name;
        $this->description = $project->description ?? '';
        $this->customer_id = (string) $project->customer_id;
        $this->status = $project->status->value;
        $this->domain = $project->domain;
        $this->clarity_api_key = $project->clarity_api_key ?? '';
    }

    public function updatedDomain(): void
    {
        $trimmed = trim($this->domain);

        if ($trimmed !== '' && !preg_match('#^https?://#i', $trimmed)) {
            $this->domain = 'https://' . $trimmed;
        }
    }

    #[On('customer-created')]
    public function onCustomerCreated(int $id): void
    {
        $this->customer_id = (string) $id;
        $this->dispatch('close-modal', id: 'create-customer-modal');
    }

    public function updateProject(): void
    {
        $this->updatedDomain();
        $this->validate();

        $this->project->update([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'customer_id' => $this->customer_id,
            'status' => $this->status,
            'domain' => $this->domain,
            'clarity_api_key' => $this->clarity_api_key ?: null,
        ]);

        $this->redirect(route('projects'), navigate: true);
    }

    public function with(): array
    {
        return [
            'customers' => User::where('type', UserTypeEnum::CUSTOMER)->get(),
            'statuses' => ProjectStatusEnum::cases(),
        ];
    }
};
?>

<div>
    <div class="flex items-center justify-center p-6">
        <form wire:submit="updateProject">
            <x-ui.fieldset :label="__('Edit Project')" class="w-150">
                <x-ui.field required>
                    <x-ui.label>{{ __('Project Name') }}</x-ui.label>
                    <x-ui.input wire:model.blur="name" :placeholder="__('Project name...')" :invalid="$errors->has('name')" />
                    <x-ui.error name="name" />
                </x-ui.field>

                <x-ui.field>
                    <x-ui.label>{{ __('Description') }}</x-ui.label>
                    <x-ui.input wire:model.blur="description" :placeholder="__('Project description...')" />
                    <x-ui.error name="description" />
                </x-ui.field>

                <x-ui.field required>
                    <x-ui.label>{{ __('Customer') }}</x-ui.label>
                    <div class="flex items-center gap-2">
                        <div class="flex-1" wire:key="customer-select-{{ $customer_id }}">
                            <x-ui.select wire:model="customer_id" :placeholder="__('Choose a customer...')" searchable>
                                @foreach ($customers as $customer)
                                    <x-ui.select.option :value="$customer->id">{{ $customer->name }}</x-ui.select.option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <x-ui.modal.trigger id="create-customer-modal">
                            <x-ui.button type="button" variant="outline" icon="plus" size="sm">{{ __('New') }}</x-ui.button>
                        </x-ui.modal.trigger>
                    </div>
                    <x-ui.error name="customer_id" />
                </x-ui.field>

                <x-ui.field required>
                    <x-ui.label>{{ __('Status') }}</x-ui.label>
                    <x-ui.radio.group wire:model.blur="status" variant="segmented" direction="horizontal">
                        @foreach ($statuses as $statusOption)
                            <x-ui.radio.item :value="strtolower($statusOption->name)" :label="$statusOption->label()"
                                :color="$statusOption->hex()" />
                        @endforeach
                    </x-ui.radio.group>
                    <x-ui.error name="status" />
                </x-ui.field>

                <x-ui.field required>
                    <x-ui.label>{{ __('Domain') }}</x-ui.label>
                    <x-ui.input wire:model.blur="domain" :placeholder="__('example.com')" :invalid="$errors->has('domain')" />
                    <x-ui.error name="domain" />
                </x-ui.field>

                <x-ui.field>
                    <x-ui.label>{{ __('Clarity API Key') }}</x-ui.label>
                    <x-ui.input wire:model.blur="clarity_api_key" :placeholder="__('Paste Clarity API token...')" :invalid="$errors->has('clarity_api_key')" />
                    <x-ui.error name="clarity_api_key" />
                </x-ui.field>

                <x-ui.separator class="my-4" hidden horizontal />

                <x-ui.field class="mt-4">
                    <x-ui.button type="submit" variant="primary" color="blue" icon="floppy-disk">{{ __('Save Changes') }}</x-ui.button>
                </x-ui.field>
            </x-ui.fieldset>
        </form>
    </div>

    <x-ui.modal id="create-customer-modal" :title="__('New Customer')" width="md">
        <livewire:customer-form />
    </x-ui.modal>
</div>
