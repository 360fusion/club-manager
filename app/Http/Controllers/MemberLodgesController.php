<?php

namespace App\Http\Controllers;

use App\Models\Lodge;
use App\Models\LodgeClaim;
use App\Models\LodgeSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My lodges": the lodges a member follows, and whether each one is in their calendar.
 */
class MemberLodgesController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::today();

        $lodges = $user->followedLodges()
            ->withPivot('notify_summons')
            ->with(['clubType:id,code,name', 'province:id,name', 'masonicHall:id,name,slug,town,postcode', 'schedules'])
            ->orderBy('lodges.name')
            ->get()
            ->map(function (Lodge $lodge) use ($today) {
                $next = $lodge->schedules
                    ->flatMap(fn (LodgeSchedule $schedule) => array_map(
                        fn (CarbonImmutable $date) => ['date' => $date->toDateString(), 'time' => $schedule->start_time, 'installation' => $lodge->isInstallationOn($date)],
                        $schedule->nextDates($today, 2),
                    ))
                    ->sortBy('date')->take(2)->values()->all();

                return [
                    'slug' => $lodge->slug,
                    'name' => $lodge->displayName(),
                    'number' => $lodge->number,
                    'order' => $lodge->clubType?->name,
                    'province' => $lodge->province?->name,
                    'status' => $lodge->status,
                    'meets_text' => $lodge->meets_text,
                    'next' => $next,
                    'in_calendar' => (bool) $lodge->pivot->in_calendar,
                    'notify_summons' => (bool) $lodge->pivot->notify_summons,
                    'is_managed' => $lodge->isManaged(),
                    'hall' => $lodge->masonicHall ? [
                        'slug' => $lodge->masonicHall->slug,
                        'name' => $lodge->masonicHall->name,
                        'town' => $lodge->masonicHall->town,
                    ] : null,
                ];
            });

        $claims = LodgeClaim::where('user_id', $user->id)
            ->with(['lodge:id,name,number,slug,club_type_id', 'lodge.clubType:id,code,name', 'club:id,name,slug', 'events'])
            ->latest('id')->limit(30)->get()
            ->map(fn (LodgeClaim $claim) => [
                'id' => $claim->id,
                'lodge' => ['name' => $claim->lodge->displayName(), 'slug' => $claim->lodge->slug],
                'status' => $claim->status,
                'open' => $claim->isOpen(),
                'decision_note' => $claim->decision_note,
                // What the team last asked, while the claim is waiting for an answer.
                'question' => $claim->status === LodgeClaim::MORE_INFO ? $claim->events->where('type', 'more_info_requested')->last()?->note : null,
                'club' => $claim->club ? ['name' => $claim->club->name, 'slug' => $claim->club->slug] : null,
                'created_at' => $claim->created_at->toDateString(),
            ]);

        return Inertia::render('Members/Lodges', [
            'claims' => $claims->values(),
            'lodges' => $lodges->values(),
            'limit' => LodgeFollowController::MAX_FOLLOWS,
        ]);
    }
}
