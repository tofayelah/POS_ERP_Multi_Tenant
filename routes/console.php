<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('outbox:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('integrity:check-books')->hourly()->withoutOverlapping();
Schedule::command('pilot:daily-check')
    ->dailyAt('08:00')
    ->timezone('Asia/Dhaka')
    ->emailOutputTo(env('PROJECT_LEAD_EMAIL', 'lead@kcerp.com'))
    ->withoutOverlapping();
