<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use App\Models\User;
use App\Models\CustomerProfile;

new #[Layout('layouts.app')] class extends Component {
    public ?int $editingId = null;

    #[Validate('required|string|max:255')]
    public string $editName = '';

    #[Validate('required|email|max:255')]
    public string $editEmail = '';

    #[Validate('nullable|string|max:255')]
    public string $editCompanyName = '';

    #[Validate('nullable|string|max:255')]
    public string $editPhone = '';

    public function startEditing(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->editingId = $user->id;
        $this->editName = $user->name;
        $this->editEmail = $user->email;

        if ($user->isCustomer()) {
            $this->editCompanyName = $user->customerProfile?->company_name ?? '';
            $this->editPhone = $user->customerProfile?->phone ?? '';
        }
    }

    public function cancelEditing(): void
    {
        $this->editingId = null;
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        $this->validate();

        $user = User::findOrFail($this->editingId);
        $user->update([
            'name' => $this->editName,
            'email' => $this->editEmail,
        ]);

        if ($user->isCustomer() && $user->customerProfile) {
            $user->customerProfile->update([
                'company_name' => $this->editCompanyName,
                'phone' => $this->editPhone ?: null,
            ]);
        }

        $this->editingId = null;
        $this->resetValidation();
    }

    public function with(): array
    {
        return [
            'users' => User::where('type', 'user')->get(),
            'customers' => User::where('type', 'customer')->with('customerProfile')->get(),
        ];
    }
};
?>

<div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 p-6">
        {{-- Users Column --}}
        <x-ui.card size="2xl">
            <div class="flex items-center gap-2 mb-4">
                <x-ui.icon name="users" />
                <span class="font-semibold text-neutral-900 dark:text-neutral-100">Users</span>
                <x-ui.badge variant="solid" color="blue" size="sm">{{ $users->count() }}</x-ui.badge>
            </div>

            @forelse ($users as $user)
                <div class="flex items-center justify-between py-3 {{ !$loop->last ? 'border-b border-neutral-200 dark:border-neutral-800' : '' }}">
                    @if ($editingId === $user->id)
                        <form wire:submit="saveEdit" class="flex-1 space-y-3">
                            <x-ui.field>
                                <x-ui.label>Name</x-ui.label>
                                <x-ui.input wire:model="editName" placeholder="Name..." :invalid="$errors->has('editName')" />
                                @error('editName') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                            </x-ui.field>
                            <x-ui.field>
                                <x-ui.label>Email</x-ui.label>
                                <x-ui.input wire:model="editEmail" type="email" placeholder="Email..." :invalid="$errors->has('editEmail')" />
                                @error('editEmail') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                            </x-ui.field>
                            <div class="flex gap-2">
                                <x-ui.button type="submit" size="xs" variant="primary">Save</x-ui.button>
                                <x-ui.button type="button" size="xs" variant="ghost" wire:click="cancelEditing">Cancel</x-ui.button>
                            </div>
                        </form>
                    @else
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$user->name" size="sm" />
                            <div>
                                <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ $user->name }}</p>
                                <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $user->email }}</p>
                            </div>
                        </div>
                        <x-ui.button size="xs" variant="ghost" icon="pencil" wire:click="startEditing({{ $user->id }})">Edit</x-ui.button>
                    @endif
                </div>
            @empty
                <p class="text-neutral-500 dark:text-neutral-400 py-4 text-center">No users found.</p>
            @endforelse
        </x-ui.card>

        {{-- Customers Column --}}
        <x-ui.card size="2xl">
            <div class="flex items-center gap-2 mb-4">
                <x-ui.icon name="ps:buildings" />
                <span class="font-semibold text-neutral-900 dark:text-neutral-100">Customers</span>
                <x-ui.badge variant="solid" color="emerald" size="sm">{{ $customers->count() }}</x-ui.badge>
            </div>

            @forelse ($customers as $customer)
                <div class="flex items-center justify-between py-3 {{ !$loop->last ? 'border-b border-neutral-200 dark:border-neutral-800' : '' }}">
                    @if ($editingId === $customer->id)
                        <form wire:submit="saveEdit" class="flex-1 space-y-3">
                            <x-ui.field>
                                <x-ui.label>Company Name</x-ui.label>
                                <x-ui.input wire:model="editCompanyName" placeholder="Company name..." :invalid="$errors->has('editCompanyName')" />
                                @error('editCompanyName') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                            </x-ui.field>
                            <x-ui.field>
                                <x-ui.label>Email</x-ui.label>
                                <x-ui.input wire:model="editEmail" type="email" placeholder="Email..." :invalid="$errors->has('editEmail')" />
                                @error('editEmail') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                            </x-ui.field>
                            <x-ui.field>
                                <x-ui.label>Phone</x-ui.label>
                                <x-ui.input wire:model="editPhone" type="tel" placeholder="Phone..." />
                                @error('editPhone') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                            </x-ui.field>
                            <div class="flex gap-2">
                                <x-ui.button type="submit" size="xs" variant="primary">Save</x-ui.button>
                                <x-ui.button type="button" size="xs" variant="ghost" wire:click="cancelEditing">Cancel</x-ui.button>
                            </div>
                        </form>
                    @else
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$customer->name" size="sm" color="emerald" />
                            <div>
                                <p class="font-medium text-neutral-900 dark:text-neutral-100">{{ $customer->customerProfile?->company_name ?? $customer->name }}</p>
                                <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $customer->email }}</p>
                                @if ($customer->customerProfile?->phone)
                                    <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $customer->customerProfile->phone }}</p>
                                @endif
                            </div>
                        </div>
                        <x-ui.button size="xs" variant="ghost" icon="pencil" wire:click="startEditing({{ $customer->id }})">Edit</x-ui.button>
                    @endif
                </div>
            @empty
                <p class="text-neutral-500 dark:text-neutral-400 py-4 text-center">No customers found.</p>
            @endforelse
        </x-ui.card>
    </div>
</div>
