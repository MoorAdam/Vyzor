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

        $this->redirectIntended(default: route('dashboard'), navigate: true);
    }
};
?>

<div>
    <div class="h-screen flex items-center justify-center">
        <form wire:submit="login">
            <x-ui.fieldset title="Login" class="w-100">
                <x-ui.field required>
                    <x-ui.label>Email</x-ui.label>
                    <x-ui.input label="Email" placeholder="E-mail..." type="email" wire:model="email" />
                    @error('email') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </x-ui.field>
                <x-ui.field required>
                    <x-ui.label>Password</x-ui.label>
                    <x-ui.input label="Password" placeholder="Password..." type="password" wire:model="password" />
                    @error('password') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                    @enderror
                </x-ui.field>
                <x-ui.field>
                    <x-ui.checkbox size="xs" label="Remember me" wire:model="remember" />
                </x-ui.field>
                <x-ui.field>
                    <x-ui.button type="submit">Login</x-ui.button>
                </x-ui.field>
            </x-ui.fieldset>
        </form>
    </div>
</div>