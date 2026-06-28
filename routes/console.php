<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Weekly "new arrivals" digest to storefront subscribers — Mondays at 09:00.
// Requires the scheduler to be running (cron: * * * * * php artisan schedule:run).
Schedule::command('subscribers:weekly-digest')->weeklyOn(1, '09:00');
