<?php

use App\Modules\Projects\Models\Project;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nightly Clarity fetch — runs once per day at the configured time so each day produces
// exactly one non-overlapping rolling-24h snapshot, which is what the summary view sums.
Schedule::call(function () {
    $projects = Project::whereNotNull('clarity_api_key')->get();

    foreach ($projects as $project) {
        Artisan::call('app:fetch-clarity', ['project' => $project->id]);
    }
})
    ->dailyAt(config('services.clarity.auto_fetch.time', '23:55'))
    ->when(fn () => (bool) config('services.clarity.auto_fetch.enabled', false))
    ->name('fetch-clarity-all-projects')
    ->withoutOverlapping();
