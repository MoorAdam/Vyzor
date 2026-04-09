<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;

new #[Layout('layouts.app')] class extends Component {
    public string $type = 'web';

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
                    <x-ui.radio.item value="web" :label="__('User')" />
                    <x-ui.radio.item value="customer" :label="__('Customer')" />
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
