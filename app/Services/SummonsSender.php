<?php

namespace App\Services;

use App\Enums\ClubMemberStatus;
use App\Mail\MeetingSummonsMail;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Emails a published summons, each person with their own RSVP link, to every active member and registered visitor
 * of the lodge who has not had it yet. Run on publishing, and again when someone becomes active (an accepted invite,
 * an approved visitor or join request), so nobody who joins after the summons went out is left without it.
 */
class SummonsSender
{
    public function __construct(private readonly RsvpTokenService $tokens) {}

    /**
     * @return int how many were emailed
     */
    public function sendToNewRecipients(Club $club, Meeting $meeting): int
    {
        $alreadySent = MeetingRsvp::where('meeting_id', $meeting->id)->whereNotNull('token_hash')->pluck('user_id');

        $people = $club->users()
            ->wherePivot('status', ClubMemberStatus::Active->value)
            ->whereNotIn('users.id', $alreadySent)
            ->get();

        foreach ($people as $person) {
            $this->send($club, $meeting, $person);
        }

        return $people->count();
    }

    /**
     * The summonses already out for meetings still to come, for someone who has just become active.
     */
    public function sendUpcomingTo(Club $club, User $user): void
    {
        Meeting::where('club_id', $club->id)
            ->where('status', 'published')
            ->whereDate('meeting_date', '>=', now()->toDateString())
            ->whereDoesntHave('rsvps', fn ($q) => $q->where('user_id', $user->id)->whereNotNull('token_hash'))
            ->get()
            ->each(fn (Meeting $meeting) => $this->send($club, $meeting, $user));
    }

    private function send(Club $club, Meeting $meeting, User $person): void
    {
        $token = $this->tokens->createTokenForUser($meeting, $person, Carbon::parse($meeting->meeting_date)->endOfDay());

        Mail::to($person->email)->send(new MeetingSummonsMail($club, $meeting, $person, $token));
    }
}
