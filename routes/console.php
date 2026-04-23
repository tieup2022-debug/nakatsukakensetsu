<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:database')
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->when(fn () => (bool) config('backup.database.schedule_enabled'));

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
