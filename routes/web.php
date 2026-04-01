<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    return Auth::user()->isCustomer()
        ? redirect()->route('customer.dashboard')
        : redirect()->route('dashboard');  // admin also goes here
});

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
});

Route::middleware('auth')->group(function () {
    Route::middleware('user_type:web')->group(function () {
        Route::livewire('/dashboard', 'pages::dashboard')->name('dashboard');
        Route::livewire('/new-project', 'pages::project.create')->name('new-project');
        Route::livewire('/projects/{project}/edit', 'pages::project.edit')->name('project.edit');
        Route::livewire('/projects', 'pages::project.list')->name('projects');
        Route::livewire('/clarity/snapshot', 'pages::clarity-snapshot')->name('clarity.snapshot');
        Route::livewire('/clarity/trends', 'pages::clarity-trends')->name('clarity.trends');
        Route::livewire('/ai-reports', 'pages::ai-reports')->name('ai-reports');
        Route::livewire('/reports', 'pages::reports')->name('reports');
        Route::livewire('/reports/{report}', 'pages::report-view')->name('report.view');
        Route::livewire('/users', 'pages::users')->name('users');
        Route::livewire('/register', 'pages::auth.register')->name('register');
    });

    Route::middleware('user_type:customer')->group(function () {
        Route::livewire('/customer/dashboard', 'pages::customer.dashboard')->name('customer.dashboard');
    });

    Route::post('/logout', function () {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});