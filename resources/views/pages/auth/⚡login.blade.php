<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app', ['layoutVariant' => 'bare'])] class extends Component {
    #[Validate('required|email')]
    public string $email = '';

    #[Validate('required')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $this->addError('email', __('auth.failed'));
            return;
        }

        session()->regenerate();

        $default = auth()->user()->isCustomer()
            ? route('customer.dashboard')
            : route('clarity.snapshot');

        $this->redirectIntended(default: $default, navigate: true);
    }
};
?>

<div>
    <div class="h-screen flex flex-col items-center justify-center">
        <div class="mb-14 flex flex-col items-center gap-3">
            <img src="/favicon.svg" alt="Vyzor Logo" class="h-16 w-auto pl-2 dark:filter-[brightness(0)_invert(1)]" />
            <div class="text-center">
                <h1 class="text-3xl font-semibold tracking-tight text-neutral-900 dark:text-white">Vyzor</h1>
                <span class="text-[10px] text-neutral-400 dark:text-neutral-500 tracking-widest uppercase">By Morgens</span>
            </div>
        </div>

        <form wire:submit="login">
            <x-ui.fieldset :title="__('Login')" class="w-100">
                <x-ui.field required>
                    <x-ui.label>{{ __('Email') }}</x-ui.label>
                    <x-ui.input :label="__('Email')" :placeholder="__('E-mail...')" type="email" wire:model="email" />
                    <x-ui.error name="email" />
                </x-ui.field>
                <x-ui.field required>
                    <x-ui.label>{{ __('Password') }}</x-ui.label>
                    <x-ui.input :label="__('Password')" :placeholder="__('Password...')" type="password" wire:model="password" />
                    <x-ui.error name="password" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.checkbox size="xs" :label="__('Remember me')" wire:model="remember" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.button type="submit">{{ __('Login') }}</x-ui.button>
                </x-ui.field>
            </x-ui.fieldset>
        </form>


    </div>
</div>