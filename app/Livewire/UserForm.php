<?php

namespace App\Livewire;

use App\Models\User;
use App\UserRoleEnum;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Validate;
use Livewire\Component;

class UserForm extends Component
{
    #[Validate('required|string|max:255|unique:users,name')]
    public string $name = '';

    #[Validate('required|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function save(): void
    {
        $this->validate();

        DB::transaction(function () {
            $user = User::create([
                'role' => UserRoleEnum::WEB,
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
            ]);

            $user->userProfile()->create();

            $this->dispatch('user-created', id: $user->id, name: $user->name);
        });

        $this->reset();
    }

    public function render()
    {
        return view('livewire.user-form');
    }
}
