<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use App\Models\User;
use App\Models\Project;
use App\ProjectStatusEnum;

new #[Layout('layouts.app')] class extends Component {
    #[Validate('required|string|max:255')]

    public string $name = '';

    #[Validate('nullable|string')]
    public string $description = '';

    #[Validate('required|exists:users,id')]
    public ?string $customer_id = null;

    #[Validate('required|string')]
    public string $status = 'active';

    #[Validate('required|string|max:255')]
    public string $domain = '';

    public function createProject(): void
    {
        $this->validate();

        Project::create([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'owner_id' => auth()->id(),
            'customer_id' => $this->customer_id,
            'status' => $this->status,
            'domain' => $this->domain,
        ]);

        $this->redirect(route('dashboard'), navigate: true);
    }

    public function with(): array
    {
        return [
            'customers' => User::where('type', 'customer')->get(),
            'statuses' => ProjectStatusEnum::cases(),
        ];
    }
};
?>

<div>
    <div class="flex items-center justify-center p-6">
        <form wire:submit="createProject">
            <x-ui.fieldset label="Create Project" class="w-100">
                <x-ui.field required>
                    <x-ui.label>Project Name</x-ui.label>
                    <x-ui.input wire:model.blur="name" placeholder="Project name..." :invalid="$errors->has('name')" />
                    @error('name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </x-ui.field>

                <x-ui.field>
                    <x-ui.label>Description</x-ui.label>
                    <x-ui.input wire:model.blur="description" placeholder="Project description..." />
                    @error('description') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                    @enderror
                </x-ui.field>

                <x-ui.field required>
                    <x-ui.label>Customer</x-ui.label>
                    <x-ui.select wire:model="customer_id" placeholder="Choose a customer..." searchable>
                        @foreach ($customers as $customer)
                            <x-ui.select.option :value="$customer->id">{{ $customer->name }}</x-ui.select.option>
                        @endforeach
                    </x-ui.select>

                </x-ui.field>
                
                <x-ui.field required>
                    <x-ui.label>Status</x-ui.label>
                    <x-ui.radio.group wire:model.blur="status" direction="horizontal">
                        @foreach ($statuses as $statusOption)
                            <x-ui.radio.item :value="strtolower($statusOption->name)" :label="$statusOption->label()" />
                        @endforeach
                    </x-ui.radio.group>
                    @error('status') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </x-ui.field>

                <x-ui.field required>
                    <x-ui.label>Domain</x-ui.label>
                    <x-ui.input wire:model.blur="domain" placeholder="example.com" :invalid="$errors->has('domain')" />
                    @error('domain') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </x-ui.field>

                <x-ui.field>
                    <x-ui.button type="submit" variant="primary">Create Project</x-ui.button>
                </x-ui.field>
            </x-ui.fieldset>
        </form>
    </div>
</div>