<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use App\Modules\Users\Enums\PermissionEnum;

new #[Layout('layouts.app')] class extends Component {
    public string $type = 'web';

    public function mount(): void
    {
        $canCreateUser = auth()->user()->can('permission', PermissionEnum::CREATE_USER);
        $canCreateCustomer = auth()->user()->can('permission', PermissionEnum::CREATE_CUSTOMER);
        abort_unless($canCreateUser || $canCreateCustomer, 403);

        $requested = request()->query('type');
        if ($requested === 'customer' && $canCreateCustomer) {
            $this->type = 'customer';
        } elseif ($requested === 'web' && $canCreateUser) {
            $this->type = 'web';
        } elseif (!$canCreateUser) {
            $this->type = 'customer';
        }
    }

    #[On('user-created')]
    #[On('customer-created')]
    public function redirectToUsers(): void
    {
        $this->redirect(route('users'), navigate: true);
    }
};
?>

<div>
    <div class="h-screen flex items-center justify-center">
        <x-ui.fieldset :label="__('Register')" class="w-100">
            <x-ui.field>
                <x-ui.radio.group wire:model.live="type" direction="horizontal">
                    <x-ui.radio.item value="web" :label="__('User')" :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::CREATE_USER)" />
                    <x-ui.radio.item value="customer" :label="__('Customer')" :disabled="auth()->user()->cannot('permission', App\Modules\Users\Enums\PermissionEnum::CREATE_CUSTOMER)" />
                </x-ui.radio.group>
            </x-ui.field>

            @if ($type === 'web')
                <livewire:user-form />
            @else
                <livewire:customer-form />
            @endif
        </x-ui.fieldset>
    </div>
</div>
