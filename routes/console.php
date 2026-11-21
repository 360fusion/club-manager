<?php

use App\Jobs\BuildStaticSite;
use App\Models\Club;
use App\Models\PageTiming;
use App\Models\SiteDeployTarget;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Needs the server's scheduler running (a cron entry that runs `php artisan schedule:run` every minute).
Schedule::command('app:send-event-payment-reminders')->dailyAt('09:00')->withoutOverlapping();
// Dues behind, overdue or nearly due, in lodges that have switched it on.
Schedule::command('app:send-dues-reminders')->dailyAt('09:15')->withoutOverlapping();
// The day before an event, a reminder to everyone booked (from 9am lodge time; each booking once).
Schedule::command('app:send-event-day-reminders')->hourly()->withoutOverlapping();
// News saved to go out later: its members are told when it goes live.
Schedule::command('app:announce-scheduled-posts')->everyFiveMinutes()->withoutOverlapping();
// Each meeting's reply schedule from 9am lodge time: ask who has not replied, last call, the Secretary's numbers, the reminder.
Schedule::command('app:send-meeting-replies')->hourly()->withoutOverlapping();
// Reminds people whose account invitation is still unanswered, once, after the number of days each club sets.
// Deletes uploaded member spreadsheets that were never imported, and old import records (personal data).
Schedule::command('app:prune-member-imports')->dailyAt('03:30')->withoutOverlapping();
// Deletes files that have been in the Trash bin for 30 days and keeps only the newest 5 versions of a replaced file.
Schedule::command('app:prune-media')->dailyAt('03:45')->withoutOverlapping();
// Trims each lodge's website page history to its own limits (newest N versions per page, and an age limit).
Schedule::command('app:prune-page-revisions')->dailyAt('04:00')->withoutOverlapping();
// Page timings from members' devices are kept 90 days (PageTiming::prunable).
Schedule::command('model:prune', ['--model' => [PageTiming::class]])->dailyAt('04:15')->withoutOverlapping();
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
// Blanks accounts whose owners asked to close them 30 or more days ago (see App\Support\AccountPurging).
Schedule::command('accounts:purge-closed')->dailyAt('03:15')->withoutOverlapping();
// Rebuilds the published website of every club whose domain shows the files, so things that change with time
// (events passing, articles scheduled to appear) reach it; edits themselves rebuild within minutes (RebuildsStaticSite).
Artisan::command('app:rebuild-static-sites', function () {
    $sending = SiteDeployTarget::all()->filter(fn (SiteDeployTarget $target) => ! empty($target->config['auto_send']))->pluck('club_id');

    Club::where('settings->static_site_serve', true)->orWhereIn('id', $sending)
        ->each(fn (Club $club) => BuildStaticSite::request($club, automatic: true));
})->purpose('Queue a new version of every website served as static files or sent automatically');
Schedule::command('app:rebuild-static-sites')->dailyAt('04:30')->withoutOverlapping();
