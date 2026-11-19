<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\IcsCalendar;
use Illuminate\Http\Response;

/**
 * One event as a calendar file, for "Add to calendar". The link is signed and short-lived rather than behind a
 * sign-in, because the app hands it to the phone's browser, which does not share the app's session.
 */
class EventCalendarController extends Controller
{
    public function show(Event $event): Response
    {
        $event->loadMissing('club');
        $start = $event->club->atLocalTime($event->starts_at);
        $end = $event->ends_at ? $event->club->atLocalTime($event->ends_at) : $start->addHours(3);

        $ics = new IcsCalendar($event->club->name, parse_url(config('app.url'), PHP_URL_HOST) ?: 'clubmanager');
        $ics->add('event-'.$event->id, $event->club->name.': '.$event->title, $start, $end, $event->formatted_location ?: $event->location, null, route('member.events', $event->club->slug));

        return response($ics->render(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.($event->slug ?: 'event').'.ics"',
        ]);
    }
}
