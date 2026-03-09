<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\AppSettingsService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$nightlySettings = app(AppSettingsService::class)->getResearchSettings();
$nightlyTime = (string) ($nightlySettings['nightly_time'] ?? '01:00');

Schedule::command('procurement:run-research --build --limit=500')
    ->when(fn () => (bool) ($nightlySettings['nightly_enabled'] ?? false))
    ->dailyAt($nightlyTime)
    ->withoutOverlapping()
    ->name('procurement-nightly-run');
