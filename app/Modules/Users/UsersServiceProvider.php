<?php

namespace App\Modules\Users;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Livewire::component('user-form', \App\Modules\Users\Livewire\UserForm::class);
        Livewire::component('customer-form', \App\Modules\Users\Livewire\CustomerForm::class);
    }
}
