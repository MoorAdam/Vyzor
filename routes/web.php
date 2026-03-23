<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::livewire('/login', 'pages::auth.login')->name('login');

Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard');