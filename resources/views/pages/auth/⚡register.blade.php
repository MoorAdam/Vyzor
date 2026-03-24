<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app', ['layoutVariant' => 'bare'])] class extends Component {
    public string $type = 'user';

    // User fields
    #[Validate('required_if:type,user|string|max:255')]
    public string $name = '';

    // Shared fields
    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    #[Validate('required|string|min:8')]
    public string $password_confirmation = '';

    // Customer fields
    #[Validate('required_if:type,customer|string|max:255')]
    public string $company_name = '';

    #[Validate('nullable|string|max:255')]
    public string $phone = '';

    public function register(): void
    {
        $this->validate();

        $user = DB::transaction(function () {
            $user = User::create([
                'type' => $this->type,
                'name' => $this->type === 'user' ? $this->name : $this->company_name,
                'email' => $this->email,
                'password' => $this->password,
            ]);

            if ($this->type === 'customer') {
                $user->customerProfile()->create([
                    'company_name' => $this->company_name,
                    'phone' => $this->phone ?: null,
                ]);
            } else {
                $user->userProfile()->create();
            }

            return $user;
        });

        Auth::login($user);

        session()->regenerate();

        $this->redirect(route('dashboard'), navigate: true);
    }
};
?>

<div>
    <div class="h-screen flex items-center justify-center">
        <form wire:submit="register">
            <x-ui.fieldset label="Register" class="w-100">
                <x-ui.field>
                    <x-ui.radio.group wire:model.live="type" direction="horizontal">
                        <x-ui.radio.item value="user" label="User" />
                        <x-ui.radio.item value="customer" label="Customer" />
                    </x-ui.radio.group>
                </x-ui.field>

                @if ($type === 'user')
                    <x-ui.field required>
                        <x-ui.label>Name</x-ui.label>
                        <x-ui.input placeholder="Name..." wire:model="name" />
                        @error('name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </x-ui.field>
                @else
                    <x-ui.field required>
                        <x-ui.label>Company Name</x-ui.label>
                        <x-ui.input placeholder="Company name..." wire:model="company_name" />
                        @error('company_name') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.label>Phone</x-ui.label>
                        <x-ui.input placeholder="Phone..." type="tel" wire:model="phone" />
                        @error('phone') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                    </x-ui.field>
                @endif

                <x-ui.field required>
                    <x-ui.label>Email</x-ui.label>
                    <x-ui.input placeholder="E-mail..." type="email" wire:model="email" />
                    @error('email') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </x-ui.field>

                <x-ui.field required>
                    <x-ui.label>Password</x-ui.label>
                    <x-ui.input placeholder="Password..." type="password" wire:model="password" />
                    @error('password') <p class="text-sm text-red-600 dark:text-red-400 mt-1">{{ $message }}</p> @enderror
                </x-ui.field>

                <x-ui.field required>
                    <x-ui.label>Confirm Password</x-ui.label>
                    <x-ui.input placeholder="Confirm password..." type="password" wire:model="password_confirmation" />
                </x-ui.field>

                <x-ui.field>
                    <x-ui.button type="submit">Register</x-ui.button>
                </x-ui.field>
            </x-ui.fieldset>
        </form>
    </div>
</div>
