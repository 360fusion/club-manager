<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use App\Models\LodgeSchedule;
use App\Support\IcsCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

/**
 * A public calendar for one lodge, so a visitor can subscribe without an account.
 */
class LodgeCalendarController extends Controller
{
    public function show(string $slug): Response
    {
        $lodge = Lodge::listed()->with(['schedules', 'masonicHall'])->where('slug', $slug)->firstOrFail();

        $ics = new IcsCalendar($lodge->displayName(), parse_url(config('app.url'), PHP_URL_HOST) ?: 'clubmanager');
        $from = CarbonImmutable::today();
        $to = $from->addMonths(12);
        $where = $lodge->masonicHall ? trim($lodge->masonicHall->name.', '.$lodge->masonicHall->fullAddress(), ', ') : null;
        $note = 'Expected date from the lodge\'s published meeting pattern. Confirm with the lodge before you travel.';

        foreach ($lodge->schedules as $schedule) {
            /** @var LodgeSchedule $schedule */
            foreach ($schedule->datesBetween($from, $to) as $date) {
                $uid = 'lodge-'.$lodge->id.'-'.$date->toDateString();
                $summary = $lodge->displayName().($lodge->isInstallationOn($date) ? ' installation meeting (expected)' : ' meeting (expected)');

                if ($start = $schedule->startsOn($date)) {
                    $ics->add($uid, $summary, $start, $start->addHours(3), $where, $note, route('lodges.show', $lodge->slug));
                } else {
                    $ics->addAllDay($uid, $summary, $date, $where, $note, route('lodges.show', $lodge->slug));
                }
            }
        }

        return response($ics->render(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$lodge->slug.'.ics"',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
