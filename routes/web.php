<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
    Route::livewire('/register', 'pages::auth.register')->name('register');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('/new-project', 'pages::project.create')->name('new-project');
    Route::livewire('/users', 'pages::users')->name('users');

    Route::post('/logout', function () {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});