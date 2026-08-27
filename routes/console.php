<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('backup:database')
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->when(fn () => (bool) config('backup.database.schedule_enabled'));

Schedule::command('board:analyze-images --limit='.(int) config('services.board_image_analysis.scheduled_limit', 2))
    ->everyMinute()
    ->withoutOverlapping(10)
    ->when(fn () => (bool) config('services.board_image_analysis.enabled', true)
        && trim((string) config('services.openai.api_key')) !== '');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
