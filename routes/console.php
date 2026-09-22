<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs the server's scheduler running (a cron entry that runs `php artisan schedule:run` every minute).
Schedule::command('app:send-event-payment-reminders')->dailyAt('09:00');
// Sends each club's automated digest when its channel's day and hour come round.
Schedule::command('app:send-weekly-digest --scheduled')->hourly()->withoutOverlapping();
// Turns due recurring bill templates (insurance, hall hire, etc.) into real vendor bills.
Schedule::command('app:generate-recurring-bills')->dailyAt('06:00')->withoutOverlapping();
