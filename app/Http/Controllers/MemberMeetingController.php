<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Models\Member;
use App\Enums\ClubMemberStatus;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Support\ClubAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Meeting summons and agenda PDF in the member area. Split out of MemberPortalController; the URLs, route names and permissions are unchanged.
 */
class MemberMeetingController extends Controller
{
    /**
     * Display vertical web page view for a meeting summons.
     */
    public function summons(string $slug, int $id): Response
    {
        $user = Auth::user();
        $club = $this->findClub($slug);

        abort_unless(ClubAccess::isActiveMember($user, $club), 403, 'You are not a member of this club.');
        $meeting = Meeting::where('club_id', $club->id)
            ->where('id', $id)
            ->with(['agendaItems', 'officerAssignments.officerRole', 'officerAssignments.user:id,name', 'fraternalVisits'])
            ->firstOrFail();
        abort_if($meeting->status !== 'published' && ! ClubAccess::can($user, $club, 'manage_meetings'), 404);

        $members = $club->users()->wherePivot('role', '!=', 'visitor')->wherePivot('status', ClubMemberStatus::Active->value)->get()->map(function ($u) {
            return [
                'id' => $u->id,
                'name' => $u->name,
                'rank' => $u->pivot->rank ?? '',
                'joined_year' => $u->pivot->joined_year ?? $u->created_at?->format('Y'),
            ];
        });

        $memberPivot = $user ? $user->clubs()->where('clubs.id', $club->id)->first()?->pivot : null;

        $userRsvp = $user ? MeetingRsvp::where('meeting_id', $meeting->id)
            ->where('user_id', $user->id)
            ->with('guests')
            ->first() : null;

        // Only what the summons shows is sent: the lodge's officers are named, and nobody is picked at random to stand in for them.
        $secretary = Member::summonsSecretary($club);
        $master = Member::holdingOffice($club, LodgeOffice::WorshipfulMaster);

        // Only the grants put to the lodge at this meeting, and only what the summons prints (no bank details).
        $charityGrants = CharityGrant::where('club_id', $club->id)
            ->forSummons($meeting)
            ->with(['proposer:id,first_name,last_name', 'seconder:id,first_name,last_name'])
            ->get(['id', 'amount', 'recipient_name', 'purpose', 'approval_status', 'proposer_member_id', 'seconder_member_id']);

        return Inertia::render('Member/Meetings/Summons', [
            'club' => [
                'slug' => $club->slug,
                'name' => $club->name,
                'lodge_number' => $club->lodge_number,
                'email' => $club->email,
                'settings' => ['phone' => $club->settings['phone'] ?? null],
            ],
            'meeting' => $meeting,
            'members' => $members,
            'userRsvp' => $userRsvp,
            'secretaryUser' => $secretary,
            'worshipfulMaster' => $master ? ['name' => $master->formatted_rank_name] : null,
            'memberRole' => $memberPivot->role ?? 'member',
            'isCutoffPassed' => $meeting->rsvp_cutoff_at ? Carbon::now()->isAfter($meeting->rsvp_cutoff_at) : false,
            'charityGrants' => $charityGrants,
        ]);
    }

    /**
     * Download or view PDF version of the meeting summons.
     */
    public function downloadMeetingPdf(string $slug, int $id)
    {
        $user = Auth::user();
        $club = $this->findClub($slug);
        abort_unless(ClubAccess::isActiveMember($user, $club), 403, 'You are not a member of this club.');

        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();
        abort_if($meeting->status !== 'published' && ! ClubAccess::can($user, $club, 'manage_meetings'), 404);

        request()->merge(['download' => 1]);

        return app(MeetingSummonsController::class)->pdf($slug, $id);
    }
}
