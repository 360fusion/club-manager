<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberRsvp\UpdateMeetingRsvpRequest;
use App\Http\Requests\MemberRsvp\UpdateRsvp2Request;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Services\Events\EventMailer;
use App\Services\Events\EventOnlinePayment;
use App\Services\Events\EventRegistrationService;
use App\Support\ClubAccess;
use Illuminate\Http\RedirectResponse;
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

        $registration = $registrations->register($event, $user, [
            'status' => $validated['attendance_status'],
            'notes' => $validated['notes'] ?? null,
            'payment_method' => $validated['payment_method'] ?? null,
            'promo_code' => $validated['promo_code'] ?? null,
            'attendees' => $validated['attendees'] ?? [],
        ]);

        $registrations->sendGuestConfirmations($registration);

        if ($registration->wasRecentlyCreated && in_array($registration->status, ['attending', 'waitlisted'], true)) {
            app(EventMailer::class)->memberBooked($registration);
        }

        // Chose to pay online (card or PayPal): straight on to the payment page for what they owe.
        if ($registration->status === 'attending' && $registration->paymentMethod?->isOnline() && $online->canPay($registration)) {
            $back = route('member.events', ['slug' => $club->slug]);

            return Inertia::location($online->start($registration, $back.'?payment=success', $back.'?payment=cancelled', null, route('member.events.paypal_return', ['slug' => $club->slug, 'id' => $event->id])));
        }

        return redirect()->back()->with('success', $validated['attendance_status'] === 'declined' ? 'Your reply has been saved.' : 'Your booking has been saved.');
    }

    /**
     * Record or update meeting RSVP for authenticated member.
     */
    public function updateMeetingRsvp(UpdateMeetingRsvpRequest $request, string $slug, int $id): RedirectResponse
    {
        $user = Auth::user();
        $club = $this->findClub($slug);

        abort_unless(ClubAccess::isActiveMember($user, $club), 403, 'You are not a member of this club.');
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();
        abort_if($meeting->status !== 'published' && ! ClubAccess::can($user, $club, 'manage_meetings'), 404);

        if ($meeting->rsvp_cutoff_at && Carbon::now()->isAfter($meeting->rsvp_cutoff_at)) {
            return redirect()->back()->withErrors(['cutoff' => 'The dining deadline for this meeting has passed. Please contact the Secretary directly.']);
        }

        $validated = $request->validated();

        $surname = strtoupper(last(explode(' ', $user->name)));
        $paymentRef = ($meeting->payment_reference_prefix ?: 'SUMMONS').'-'.$meeting->id.'-'.$surname;

        $rsvp = MeetingRsvp::updateOrCreate(
            ['meeting_id' => $meeting->id, 'user_id' => $user->id],
            [
                'token_hash' => Str::random(40),
                'token_expires_at' => Carbon::now()->addDays(30),
                'attendance_status' => $validated['attendance_status'],
                'apology_reason' => $validated['apology_reason'] ?? null,
                'dietary_requirements' => $validated['dietary_requirements'] ?? null,
                'payment_reference' => $paymentRef,
                'responded_at' => Carbon::now(),
            ]
        );

        if (array_key_exists('guests', $validated)) {
            $rsvp->guests()->delete();
            if (is_array($validated['guests'])) {
                foreach ($validated['guests'] as $g) {
                    if (! empty($g['guest_name'])) {
                        $rsvp->guests()->create([
                            'guest_name' => $g['guest_name'],
                            'dietary_requirements' => $g['dietary_requirements'] ?? null,
                            'attending_dining' => $g['attending_dining'] ?? true,
                            'dining_fee' => $meeting->dining_cost_guest,
                        ]);
                    }
                }
            }
        }

        return redirect()->back()->with('success', 'Your RSVP response has been saved.');
    }
}
