<?php

use Livewire\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app', ['layoutVariant' => 'bare'])] class extends Component {
    //
};
?>

<div>
    <div class="h-screen flex items-center justify-center">
        <x-ui.fieldset title="Login" class="w-100">
            <x-ui.field required>
                <x-ui.label>Email</x-ui.label>
                <x-ui.input label="Email" placeholder="E-mail..." type="email" wire:model="email" />
            </x-ui.field>
            <x-ui.field required>
                <x-ui.label>Password</x-ui.label>
                <x-ui.input label="Password" placeholder="Password..." type="password" wire:model="password" />
            </x-ui.field>
            <x-ui.button type="submit">Login</x-ui.button>
        </x-ui.fieldset>
        {{-- I have not failed. I've just found 10,000 ways that won't work. - Thomas Edison --}}
    </div>
</div>