<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Notifications\ClubNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * The day before an event, everyone booked on it with an account gets a reminder in their inbox (and so on their
 * phone, if they have the app): when, where, and their ticket. "Tomorrow" is the lodge's own date.
 */
class SendEventDayRemindersCommand extends Command
{
    protected $signature = 'app:send-event-day-reminders';

    protected $description = 'Remind people booked on an event the day before it';

    public function handle(): int
    {
        $sent = 0;

        // Event times are the lodge's wall clock, so take a wide window and check each lodge's own tomorrow below.
        Event::with('club')
            ->whereBetween('starts_at', [now()->subDay(), now()->addDays(3)])
            ->whereNotIn('status', ['cancelled', 'completed', 'draft'])
            ->get()
            // From 9am where the lodge is (the schedule runs hourly), so nobody's phone goes off in the night.
            ->filter(fn (Event $event) => now($event->club->timezone())->hour >= 9
                && $event->starts_at->toDateString() === now($event->club->timezone())->addDay()->toDateString())
            ->each(function (Event $event) use (&$sent) {
                EventRegistration::with(['user', 'event'])
                    ->where('event_id', $event->id)
                    ->whereIn('status', EventRegistration::HOLDING_PLACE)
                    ->whereNotNull('user_id')
                    ->get()
                    // Once per booking, however often this runs.
                    ->filter(fn (EventRegistration $booking) => $booking->user && Cache::add("event-day-reminder:{$booking->id}", true, now()->addDays(3)))
                    ->each(function (EventRegistration $booking) use ($event, &$sent) {
                        $booking->user->notify(ClubNotification::eventTomorrow($booking, $event->club));
                        $sent++;
                    });
            });

        $this->info("Sent {$sent} event ".($sent === 1 ? 'reminder' : 'reminders').'.');

        return self::SUCCESS;
    }
}
