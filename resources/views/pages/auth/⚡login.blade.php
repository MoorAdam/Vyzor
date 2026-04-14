<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;

new #[Layout('layouts.app', ['layoutVariant' => 'bare'])] class extends Component {
    #[Validate('required|string')]
    public string $name = '';

    #[Validate('required')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        if (!Auth::attempt(['name' => $this->name, 'password' => $this->password], $this->remember)) {
            $this->addError('name', __('auth.failed'));
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
                    <x-ui.label>{{ __('Name') }}</x-ui.label>
                    <x-ui.input :label="__('Name')" :placeholder="__('Name...')" type="text" wire:model="name" />
                    <x-ui.error name="name" />
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
                    <x-ui.button variant="" type="submit">{{ __('Login') }}</x-ui.button>
                </x-ui.field>
            </x-ui.fieldset>
        </form>

        <div class="flex items-center gap-1 mt-6">
            <form method="POST" action="{{ route('locale.switch', 'en') }}">
                @csrf
                <button type="submit" class="px-2 py-1 text-xs font-semibold rounded transition-colors {{ app()->getLocale() === 'en' ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900' : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300' }}">
                    EN
                </button>
            </form>
            <form method="POST" action="{{ route('locale.switch', 'hu') }}">
                @csrf
                <button type="submit" class="px-2 py-1 text-xs font-semibold rounded transition-colors {{ app()->getLocale() === 'hu' ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900' : 'text-neutral-500 dark:text-neutral-400 hover:text-neutral-700 dark:hover:text-neutral-300' }}">
                    HU
                </button>
            </form>
        </div>

    </div>
</div>