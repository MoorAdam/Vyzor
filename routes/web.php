<?php

use App\Modules\Users\Enums\PermissionEnum;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }

    $user = Auth::user();

    if ($user->isCustomer()) {
        return redirect()->route('customer.dashboard');
    }

    if (User::permissionsForRoles($user->roles ?? [])->contains(PermissionEnum::VIEW_PROJECTS->value) || $user->isAdmin()) {
        return redirect()->route('projects');
    }

    return redirect()->route('no-access');
});

Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.login')->name('login');
});

Route::middleware('auth')->group(function () {
    Route::livewire('/no-access', 'pages::no-access')->name('no-access');

    Route::middleware('user_role:web')->group(function () {
        Route::livewire('/new-project', 'pages::project.create')->name('new-project');
        Route::livewire('/projects/{project}/edit', 'pages::project.edit')->name('project.edit');
        Route::livewire('/projects', 'pages::project.list')->name('projects');
        Route::livewire('/clarity/snapshot', 'pages::clarity-snapshot')->name('clarity.snapshot');
        Route::livewire('/clarity/trends', 'pages::clarity-trends')->name('clarity.trends');
        Route::livewire('/clarity/clarity-report', 'pages::clarity-clarity-report')->name('clarity.clarity-report');
        Route::livewire('/clarity/page-report', 'pages::clarity-page-report')->name('clarity.page-report');
        Route::livewire('/google-analytics/overview', 'pages::ga-overview')->name('ga.overview');
        Route::livewire('/google-analytics/pages', 'pages::ga-pages')->name('ga.pages');
        Route::livewire('/google-analytics/audience', 'pages::ga-audience')->name('ga.audience');
        Route::livewire('/google-analytics/report', 'pages::ga-report')->name('ga.report');
        Route::livewire('/google-analytics/realtime', 'pages::ga-realtime')->name('ga.realtime');
        Route::livewire('/ai-reports', 'pages::ai-reports')->name('ai-reports');
        Route::livewire('/reports', 'pages::reports')->name('reports');
        Route::livewire('/reports/{report}', 'pages::report-view')->name('report.view');
        Route::livewire('/heatmaps', 'pages::heatmaps')->name('heatmaps');
        Route::livewire('/heatmaps/upload', 'pages::heatmap-upload')->name('heatmaps.upload');
        Route::livewire('/users', 'pages::users')->name('users');
        Route::livewire('/settings/contexts', 'pages::settings.presets')->name('preset.settings');
        Route::livewire('/register', 'pages::auth.register')->name('register');
    });

    Route::middleware('user_role:customer')->group(function () {
        Route::livewire('/customer/dashboard', 'pages::customer.dashboard')->name('customer.dashboard');
    });

    Route::post('/locale/{locale}', function (string $locale) {
        if (in_array($locale, ['en', 'hu'])) {
            session(['locale' => $locale]);
        }
        return back();
    })->name('locale.switch');

    Route::post('/logout', function () {
        Auth::guard('web')->logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});