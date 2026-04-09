<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use App\Models\User;
use App\Models\CustomerProfile;
use App\UserTypeEnum;

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

    #[Validate('nullable|string|min:8|confirmed')]
    public string $editPassword = '';

    public string $editPassword_confirmation = '';

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
        $this->editPassword = '';
        $this->editPassword_confirmation = '';
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        $this->validate();

        $user = User::findOrFail($this->editingId);

        $data = [
            'name' => $this->editName,
            'email' => $this->editEmail,
        ];

        if (filled($this->editPassword)) {
            $data['password'] = $this->editPassword;
        }

        $user->update($data);

        if ($user->isCustomer() && $user->customerProfile) {
            $user->customerProfile->update([
                'company_name' => $this->editCompanyName,
                'phone' => $this->editPhone ?: null,
            ]);
        }

        $this->editingId = null;
        $this->editPassword = '';
        $this->editPassword_confirmation = '';
        $this->resetValidation();
    }

    public function deleteUser(int $userId): void
    {
        $user = User::findOrFail($userId);
        $user->delete();
    }

    public function with(): array
    {
        return [
            'users' => User::where('type', UserTypeEnum::WEB)->get(),
            'customers' => User::where('type', UserTypeEnum::CUSTOMER)->with('customerProfile')->get(),
        ];
    }
};
?>

<div class="p-6">
    <x-ui.link href="{{ route('register') }}" variant="outline">{{ __('Add new user / customer') }}</x-ui.link>

    <x-ui.separator class="my-4"/>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Users Column --}}
        <x-ui.card size="2xl">
            <div class="flex items-center gap-2 mb-4">
                <x-ui.icon name="users" />
                <x-ui.heading level="h2" size="sm">{{ __('Users') }}</x-ui.heading>
                <x-ui.badge variant="solid" color="blue" size="sm">{{ $users->count() }}</x-ui.badge>
            </div>

            @forelse ($users as $user)
                <div
                    class="flex items-center justify-between py-3 {{ !$loop->last ? 'border-b border-neutral-200 dark:border-neutral-800' : '' }}">
                    @if ($editingId === $user->id)
                        <form wire:submit="saveEdit" class="flex-1 space-y-3">
                            <x-ui.field>
                                <x-ui.label>{{ __('Name') }}</x-ui.label>
                                <x-ui.input wire:model="editName" :placeholder="__('Name...')" :invalid="$errors->has('editName')" />
                                <x-ui.error name="editName" />
                            </x-ui.field>
                            <x-ui.field>
                                <x-ui.label>{{ __('Email') }}</x-ui.label>
                                <x-ui.input wire:model="editEmail" type="email" :placeholder="__('Email...')"
                                    :invalid="$errors->has('editEmail')" />
                                <x-ui.error name="editEmail" />
                            </x-ui.field>
                            <x-ui.field>
                                <x-ui.label>{{ __('New Password') }}</x-ui.label>
                                <x-ui.input wire:model="editPassword" type="password" :placeholder="__('Leave blank to keep current...')"
                                    :invalid="$errors->has('editPassword')" />
                                <x-ui.error name="editPassword" />
                            </x-ui.field>
                            <x-ui.field>
                                <x-ui.label>{{ __('Confirm Password') }}</x-ui.label>
                                <x-ui.input wire:model="editPassword_confirmation" type="password" :placeholder="__('Confirm password...')" />
                            </x-ui.field>
                            <div class="flex gap-2">
                                <x-ui.button type="submit" size="xs" variant="primary">{{ __('Save') }}</x-ui.button>
                                <x-ui.button type="button" size="xs" variant="ghost"
                                    wire:click="cancelEditing">{{ __('Cancel') }}</x-ui.button>
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
                        <div class="flex shrink-0 gap-1">
                            <x-ui.button size="xs" variant="ghost" icon="pencil"
                                wire:click="startEditing({{ $user->id }})">{{ __('Edit') }}</x-ui.button>
                            <x-ui.modal.trigger :id="'delete-user-' . $user->id">
                                <x-ui.button size="xs" variant="ghost" icon="trash" color="red">{{ __('Delete') }}</x-ui.button>
                            </x-ui.modal.trigger>
                        </div>
                    @endif
                </div>

                <x-ui.modal :id="'delete-user-' . $user->id" :title="__('Delete User')" size="sm" centered>
                    <x-ui.text>{!! __('Are you sure you want to delete <strong>:name</strong>?', ['name' => $user->name]) !!}</x-ui.text>
                    <x-slot:footer>
                        <x-ui.button variant="ghost" x-on:click="isOpen = false">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button variant="danger" wire:click="deleteUser({{ $user->id }})" x-on:click="isOpen = false">{{ __('Delete') }}</x-ui.button>
                    </x-slot:footer>
                </x-ui.modal>
            @empty
                <x-ui.empty>
                    <x-ui.empty.contents>
                        <x-ui.text>{{ __('No users found.') }}</x-ui.text>
                    </x-ui.empty.contents>
                </x-ui.empty>
            @endforelse
        </x-ui.card>

        {{-- Customers Column --}}
        <x-ui.card size="2xl">
            <div class="flex items-center gap-2 mb-4">
                <x-ui.icon name="ps:buildings" />
                <x-ui.heading level="h2" size="sm">{{ __('Customers') }}</x-ui.heading>
                <x-ui.badge variant="solid" color="emerald" size="sm">{{ $customers->count() }}</x-ui.badge>
            </div>

            @forelse ($customers as $customer)
                <div
                    class="flex items-center justify-between py-3 {{ !$loop->last ? 'border-b border-neutral-200 dark:border-neutral-800' : '' }}">
                    @if ($editingId === $customer->id)
                        <form wire:submit="saveEdit" class="flex-1 space-y-3">
                            <x-ui.field>
                                <x-ui.label>{{ __('Company Name') }}</x-ui.label>
                                <x-ui.input wire:model="editCompanyName" :placeholder="__('Company name...')"
                                    :invalid="$errors->has('editCompanyName')" />
                                <x-ui.error name="editCompanyName" />
                            </x-ui.field>
                            <x-ui.field>
                                <x-ui.label>{{ __('Email') }}</x-ui.label>
                                <x-ui.input wire:model="editEmail" type="email" :placeholder="__('Email...')"
                                    :invalid="$errors->has('editEmail')" />
                                <x-ui.error name="editEmail" />
                            </x-ui.field>
                            <x-ui.field>
                                <x-ui.label>{{ __('Phone') }}</x-ui.label>
                                <x-ui.input wire:model="editPhone" type="tel" :placeholder="__('Phone...')" />
                                <x-ui.error name="editPhone" />
                            </x-ui.field>
                            <x-ui.field>
                                <x-ui.label>{{ __('New Password') }}</x-ui.label>
                                <x-ui.input wire:model="editPassword" type="password" :placeholder="__('Leave blank to keep current...')"
                                    :invalid="$errors->has('editPassword')" />
                                <x-ui.error name="editPassword" />
                            </x-ui.field>
                            <x-ui.field>
                                <x-ui.label>{{ __('Confirm Password') }}</x-ui.label>
                                <x-ui.input wire:model="editPassword_confirmation" type="password" :placeholder="__('Confirm password...')" />
                            </x-ui.field>
                            <div class="flex gap-2">
                                <x-ui.button type="submit" size="xs" variant="primary">{{ __('Save') }}</x-ui.button>
                                <x-ui.button type="button" size="xs" variant="ghost"
                                    wire:click="cancelEditing">{{ __('Cancel') }}</x-ui.button>
                            </div>
                        </form>
                    @else
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$customer->name" size="sm" color="emerald" />
                            <div>
                                <p class="font-medium text-neutral-900 dark:text-neutral-100">
                                    {{ $customer->customerProfile?->company_name ?? $customer->name }}</p>
                                <p class="text-sm text-neutral-500 dark:text-neutral-400">{{ $customer->email }}</p>
                                @if ($customer->customerProfile?->phone)
                                    <p class="text-sm text-neutral-500 dark:text-neutral-400">
                                        {{ $customer->customerProfile->phone }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <x-ui.button size="xs" variant="ghost" icon="pencil"
                                wire:click="startEditing({{ $customer->id }})">{{ __('Edit') }}</x-ui.button>
                            <x-ui.modal.trigger :id="'delete-customer-' . $customer->id">
                                <x-ui.button size="xs" variant="ghost" icon="trash" color="red">{{ __('Delete') }}</x-ui.button>
                            </x-ui.modal.trigger>
                        </div>
                    @endif
                </div>

                <x-ui.modal :id="'delete-customer-' . $customer->id" :title="__('Delete Customer')" size="sm" centered>
                    <x-ui.text>{!! __('Are you sure you want to delete <strong>:name</strong>?', ['name' => $customer->customerProfile?->company_name ?? $customer->name]) !!}</x-ui.text>
                    <x-slot:footer>
                        <x-ui.button variant="ghost" x-on:click="isOpen = false">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button variant="danger" wire:click="deleteUser({{ $customer->id }})" x-on:click="isOpen = false">{{ __('Delete') }}</x-ui.button>
                    </x-slot:footer>
                </x-ui.modal>
            @empty
                <x-ui.empty>
                    <x-ui.empty.contents>
                        <x-ui.text>{{ __('No customers found.') }}</x-ui.text>
                    </x-ui.empty.contents>
                </x-ui.empty>
            @endforelse
        </x-ui.card>
    </div>
</div>