<?php

namespace App\Modules\Users\Livewire;

use App\Modules\Users\Enums\PermissionEnum;
use App\Modules\Users\Enums\UserRoleEnum;
use App\Models\User;
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

    public function mount(): void
    {
        abort_unless(auth()->user()->can('permission', PermissionEnum::CREATE_USER), 403);
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('permission', PermissionEnum::CREATE_USER), 403);
        $this->validate();

        DB::transaction(function () {
            $user = User::create([
                'roles' => [UserRoleEnum::WEB->value],
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
