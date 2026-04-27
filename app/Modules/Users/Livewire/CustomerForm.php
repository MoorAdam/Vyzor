<?php

namespace App\Modules\Users\Livewire;

use App\Modules\Users\Enums\UserRoleEnum;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CustomerForm extends Component
{
    #[Validate('required|string|max:255|unique:users,name')]
    public string $company_name = '';

    #[Validate('required|email|max:255|unique:users,email')]
    public string $email = '';

    #[Validate('nullable|string|max:255')]
    public string $phone = '';

    #[Validate('required|string|min:8|confirmed')]
    public string $password = '';

    public string $password_confirmation = '';

    public function save(): void
    {
        $this->validate();

        DB::transaction(function () {
            $user = User::create([
                'roles' => [UserRoleEnum::CUSTOMER->value],
                'name' => $this->company_name,
                'email' => $this->email,
                'password' => $this->password,
            ]);

            $user->customerProfile()->create([
                'company_name' => $this->company_name,
                'phone' => $this->phone ?: null,
            ]);

            $this->dispatch('customer-created', id: $user->id, name: $user->name);
        });

        $this->reset();
    }

    public function render()
    {
        return view('livewire.customer-form');
    }
}
