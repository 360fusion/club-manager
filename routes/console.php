<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs the server's scheduler running (a cron entry that runs `php artisan schedule:run` every minute).
Schedule::command('app:send-event-payment-reminders')->dailyAt('09:00');
