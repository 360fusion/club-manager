<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs the server's scheduler running (a cron entry that runs `php artisan schedule:run` every minute).
Schedule::command('app:send-event-payment-reminders')->dailyAt('09:00');
// Reminds people whose account invitation is still unanswered, once, after the number of days each club sets.
// Deletes uploaded member spreadsheets that were never imported, and old import records (personal data).
Schedule::command('app:prune-member-imports')->dailyAt('03:30');
// Deletes files that have been in the Trash bin for 30 days and keeps only the newest 5 versions of a replaced file.
Schedule::command('app:prune-media')->dailyAt('03:45');
// Trims each lodge's website page history to its own limits (newest N versions per page, and an age limit).
Schedule::command('app:prune-page-revisions')->dailyAt('04:00');
Schedule::command('app:send-invitation-reminders')->dailyAt('10:00')->withoutOverlapping();
// Sends each club's automated digest when its channel's day and hour come round.
Schedule::command('app:send-weekly-digest --scheduled')->hourly()->withoutOverlapping();
// Turns due recurring bill templates (insurance, hall hire, etc.) into real vendor bills.
Schedule::command('app:generate-recurring-bills')->dailyAt('06:00')->withoutOverlapping();
// Re-fetches the ~3,900 pages lodge directory details came from (one a second, about an hour) and flags changed ones;
// it never edits a lodge. A shared province list flags every lodge on it when any part of the page changes.
Schedule::command('lodges:check-sources')->weeklyOn(0, '02:00')->withoutOverlapping()->runInBackground();
// Asks each lodge that manages its directory listing, once a year, whether the listing is still right.
Schedule::command('lodges:remind-secretaries')->dailyAt('09:30')->withoutOverlapping();
