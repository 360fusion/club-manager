<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRsvp\UpdateMeetingRsvpRequest;
use App\Http\Requests\MemberRsvp\UpdateRsvp2Request;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Services\Events\EventMailer;
use App\Services\Events\EventOnlinePayment;
use App\Services\Events\EventRegistrationService;
use App\Services\Meetings\MeetingDiningPayment;
use App\Services\Meetings\MeetingReplies;
use App\Support\ClubAccess;
use App\Support\NativeApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Member RSVPs to events and meetings. Split out of MemberPortalController; the URLs, route names and permissions are unchanged.
 */
class MemberRsvpController extends Controller
{
    /**
     * Submit or update event RSVP and 3-course dining selections.
     */
    public function updateRsvp(UpdateRsvp2Request $request, string $slug, int $eventId, EventRegistrationService $registrations, EventOnlinePayment $online): RedirectResponse|SymfonyResponse
    {
        $user = Auth::user();
        $club = $this->findClub($slug);
        $event = Event::where('club_id', $club->id)->published()->findOrFail($eventId);

        abort_unless($event->canBeRsvpedBy($user), 403, 'This event is not open to your account.');

        $validated = $request->validated();

        $previousStatus = EventRegistration::where('event_id', $event->id)->where('user_id', $user->id)->value('status');

        $registration = $registrations->register($event, $user, [
            'status' => $validated['attendance_status'],
            'notes' => $validated['notes'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
            'promo_code' => $validated['promo_code'] ?? null,
            'attendees' => $validated['attendees'] ?? [],
        ]);

        $registrations->sendGuestConfirmations($registration);

        // A first booking, or booking again after replying "can't go" or cancelling, is confirmed by email; changing a booking is not.
        $isNewBooking = $registration->wasRecentlyCreated || in_array($previousStatus, ['declined', 'cancelled'], true);
        if ($isNewBooking && in_array($registration->status, ['attending', 'waitlisted'], true)) {
            app(EventMailer::class)->memberBooked($registration);
        }

        // Chose to pay online (card or PayPal): straight on to the payment page for what they owe.
        if ($registration->status === 'attending' && $registration->paymentMethod?->isOnline() && $online->canPay($registration)) {
            $back = route('member.events', ['slug' => $club->slug]);

            return Inertia::location($online->start(
                $registration,
                NativeApp::returnUrl($request, $back.'?payment=success'),
                NativeApp::returnUrl($request, $back.'?payment=cancelled'),
                null,
                NativeApp::returnUrl($request, route('member.events.paypal_return', ['slug' => $club->slug, 'id' => $event->id])),
            ));
        }

        return redirect()->back()->with('success', $validated['attendance_status'] === 'declined' ? 'Your reply has been saved.' : 'Your booking has been saved.');
    }

    /**
     * Record or update meeting RSVP for authenticated member.
     */
    public function updateMeetingRsvp(UpdateMeetingRsvpRequest $request, string $slug, int $id, MeetingReplies $replies): RedirectResponse
    {
        $user = Auth::user();
        $club = $this->findClub($slug);

        abort_unless(ClubAccess::isActiveMember($user, $club), 403, 'You are not a member of this club.');
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();
        abort_if($meeting->status !== 'published' && ! ClubAccess::can($user, $club, 'manage_meetings'), 404);

        $validated = $request->validated();
        $paymentRef = MeetingReplies::paymentReference($meeting, $user);

        // The emailed reply link keeps working: its token is only set when there is no reply row yet.
        $rsvp = MeetingRsvp::firstOrNew(['meeting_id' => $meeting->id, 'user_id' => $user->id]);
        $before = MeetingReplies::before($rsvp->exists ? $rsvp : null);
        $guests = array_key_exists('guests', $validated)
            ? collect($validated['guests'] ?? [])->filter(fn ($g) => ! empty($g['guest_name']))->map(fn ($g) => ['attending_dining' => $g['attending_dining'] ?? true])
            : ($rsvp->exists ? $rsvp->guests : []);
        MeetingReplies::guardDining($meeting, $before, $validated['attendance_status'], $guests);
        if (! $rsvp->exists) {
            $rsvp->fill(['token_hash' => Str::random(40), 'token_expires_at' => Carbon::now()->addDays(30)]);
        }
        $rsvp->fill(
            [
                'attendance_status' => $validated['attendance_status'],
                'apology_reason' => $validated['apology_reason'] ?? null,
                'dietary_requirements' => $validated['dietary_requirements'] ?? null,
                'payment_reference' => $paymentRef,
                'responded_at' => Carbon::now(),
            ]
        )->save();

        if (array_key_exists('guests', $validated)) {
            MeetingReplies::syncGuests($meeting, $rsvp, is_array($validated['guests']) ? $validated['guests'] : []);
        }

        $replies->afterChange($meeting, $rsvp, $before);

        return redirect()->back()->with('success', 'Your RSVP response has been saved.');
    }

    /**
     * Pay for this member's meeting dining by card: off to Stripe, and back to the summons.
     */
    public function payMeetingDining(Request $request, string $slug, int $id, MeetingDiningPayment $payments): SymfonyResponse
    {
        $club = $this->findClub($slug);
        abort_unless(ClubAccess::isActiveMember($request->user(), $club), 403, 'You are not a member of this club.');

        $meeting = Meeting::where('club_id', $club->id)->where('status', 'published')->findOrFail($id);
        $rsvp = MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $request->user()->id)->firstOrFail();
        $back = route('member.meetings.summons', ['slug' => $club->slug, 'id' => $meeting->id]);

        return Inertia::location($payments->start($rsvp, NativeApp::returnUrl($request, $back.'?payment=success'), NativeApp::returnUrl($request, $back.'?payment=cancelled')));
    }
}
