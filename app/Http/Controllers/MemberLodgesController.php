<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberStatus;
use App\Models\Club;
use App\Models\Lodge;
use App\Models\LodgeClaim;
use App\Models\LodgeCorrection;
use App\Models\LodgeSchedule;
use App\Models\MemberVisit;
use App\Services\PassedOn\LodgeLinks;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My lodges": the member's own lodges, lodges to follow (from their hall and their lodges' lists), the lodges they follow, whether each one is in their calendar, and the lodges they have visited.
 */
class MemberLodgesController extends Controller
{
    public function index(Request $request, LodgeLinks $links): Response
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

        // The member's own lodges, and lodges to follow: those sharing their hall and the ones each lodge lists as
        // sister or reciprocal visit lodges, less any they already follow or belong to.
        $ownClubs = $user->clubs()->wherePivot('status', ClubMemberStatus::Active->value)->with('lodge.masonicHall:id,name,slug,town')->orderBy('name')->get();
        $own = $ownClubs->map(fn (Club $club) => [
            'name' => $club->lodge?->displayName() ?? $club->name,
            'slug' => $club->slug,
            'lodge_slug' => $club->lodge?->slug,
            'role' => $club->pivot->role,
            'hall' => $club->lodge?->masonicHall ? ['name' => $club->lodge->masonicHall->name, 'town' => $club->lodge->masonicHall->town] : null,
        ]);

        $skip = $user->followedLodges()->pluck('lodges.id')->merge($ownClubs->pluck('lodge.id')->filter())->all();
        $suggested = $links->suggestionsFor($user);

        // Find a lodge to follow by its name, number, hall or town, like the directory's own search.
        $search = trim((string) $request->query('q', ''));
        $results = mb_strlen($search) < 2 ? collect() : Lodge::listed()
            ->where(fn ($match) => $match->whereLike('name', '%'.$search.'%')->orWhere('number', $search)
                ->orWhereHas('masonicHall', fn ($hall) => $hall->whereLike('name', '%'.$search.'%')->orWhereLike('town', '%'.$search.'%')))
            // Lodges already followed are listed under Following, and their own lodges above it.
            ->whereNotIn('id', $skip)
            ->with(['clubType:id,code,name', 'masonicHall:id,name,town'])->orderBy('name')->limit(20)->get()
            ->map(fn (Lodge $lodge) => [
                'slug' => $lodge->slug,
                'name' => $lodge->displayName(),
                // Many lodges share a name: the number and town tell them apart.
                'detail' => implode(' · ', array_filter([$lodge->clubType?->name, $lodge->number ? 'No. '.$lodge->number : null, $lodge->masonicHall?->town])),
            ]);

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
                'created_at' => $claim->created_at->format('j M Y'),
            ]);

        $corrections = LodgeCorrection::where('user_id', $user->id)
            ->with(['lodge:id,name,number,slug,status,club_type_id', 'lodge.clubType:id,code,name'])
            ->latest('id')->limit(30)->get()
            ->map(fn (LodgeCorrection $correction) => [
                'id' => $correction->id,
                'lodge' => ['name' => $correction->lodge->displayName(), 'slug' => $correction->lodge->status === 'active' ? $correction->lodge->slug : null],
                'status' => $correction->status,
                'what' => array_map(fn (string $field) => LodgeCorrection::FIELDS[$field] ?? $field, array_keys($correction->changes)),
                'decision_note' => $correction->status === LodgeCorrection::REJECTED ? $correction->decision_note : null,
                'created_at' => $correction->created_at->format('j M Y'),
            ]);

        $provinces = $request->user()->administeredProvinces()->get(['provinces.id', 'provinces.name']);

        $visits = MemberVisit::where('user_id', $user->id)->with('lodge:id,slug')->orderByDesc('visited_on')->orderByDesc('id')->limit(100)->get()
            ->map(fn (MemberVisit $visit) => [
                'id' => $visit->id,
                'lodge' => $visit->lodge_name,
                'lodge_slug' => $visit->lodge?->slug,
                'visited_on' => $visit->visited_on->format('j M Y'),
                'note' => $visit->note,
            ]);

        return Inertia::render('Members/Lodges', [
            'visits' => $visits->values(),
            'claims' => $claims->values(),
            'corrections' => $corrections->values(),
            'lodges' => $lodges->values(),
            'own' => $own->values(),
            'suggested' => $suggested,
            'search' => $search,
            'results' => $results->values(),
            'limit' => LodgeFollowController::MAX_FOLLOWS,
            // For a provincial admin: their provinces and how many suggested fixes wait for them.
            'province' => $provinces->isEmpty() ? null : [
                'names' => $provinces->pluck('name')->all(),
                'waiting' => LodgeCorrection::open()->whereHas('lodge', fn ($lodge) => $lodge->whereIn('province_id', $provinces->pluck('id')))->count(),
            ],
        ]);
    }
}
