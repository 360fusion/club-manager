<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Services\Events\EventRegistrationService;
use App\Support\MemberScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One-tap replies from the dashboard, calendar and lists. Unlike the full forms,
 * these keep any dietary notes already given, and fall back to the ones saved on
 * the member's club profile.
 */
class QuickRsvpController extends Controller
{
    public function meeting(Request $request, string $slug, int $id): RedirectResponse
    {
        $validated = $request->validate(['attendance_status' => 'required|in:attending_dining,attending_meeting_only,apologies']);

        $scope = MemberScope::for($request->user(), $slug);
        $meeting = Meeting::where('club_id', $scope->club->id)->where('status', 'published')->findOrFail($id);

        if ($meeting->rsvp_cutoff_at && now()->isAfter($meeting->rsvp_cutoff_at)) {
            return back()->withErrors(['cutoff' => 'Replies for this meeting have closed. Please contact the Secretary.']);
        }

        $user = $request->user();
        $rsvp = MeetingRsvp::firstOrNew(['meeting_id' => $meeting->id, 'user_id' => $user->id]);

        if (! $rsvp->exists) {
            $rsvp->forceFill([
                'token_hash' => Str::random(40),
                'token_expires_at' => now()->addDays(30),
                'payment_reference' => ($meeting->payment_reference_prefix ?: 'SUMMONS').'-'.$meeting->id.'-'.strtoupper(last(explode(' ', $user->name))),
            ]);
        }

        $rsvp->forceFill([
            'attendance_status' => $validated['attendance_status'],
            'dietary_requirements' => $rsvp->dietary_requirements ?? $scope->memberClubs->firstWhere('id', $scope->club->id)?->pivot?->dietary_notes,
            'responded_at' => now(),
        ])->save();

        return back()->with('success', 'Your reply to '.$meeting->title.' has been saved.');
    }

    public function event(Request $request, string $slug, int $id, EventRegistrationService $registrations): RedirectResponse
    {
        $validated = $request->validate(['attendance_status' => 'required|in:attending,declined,tentative']);

        $scope = MemberScope::for($request->user(), $slug);
        $event = Event::where('club_id', $scope->club->id)->published()->visibleTo($request->user())->findOrFail($id);

        abort_unless($event->canBeRsvpedBy($request->user()), 403, 'This event is not open to your account.');

        if ($validated['attendance_status'] !== 'declined' && ! $event->allowsQuickReply()) {
            return back()->withErrors(['rsvp' => 'This event needs a few choices first. Open it to finish your reply.']);
        }

        $registrations->quickReply(
            $event,
            $request->user(),
            $validated['attendance_status'],
            $scope->memberClubs->firstWhere('id', $scope->club->id)?->pivot?->dietary_notes,
        );

        return back()->with('success', 'Your reply to '.$event->title.' has been saved.');
    }
}
