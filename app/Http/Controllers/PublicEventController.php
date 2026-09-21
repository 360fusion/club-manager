<?php

namespace App\Http\Controllers;

use App\Mail\EventBookingMail;
use App\Models\Club;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Services\Events\EventPayload;
use App\Services\Events\EventRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * An event's public page, booking for outside guests, and the private link they manage a booking with.
 */
class PublicEventController extends Controller
{
    public function show(string $clubSlug, string $eventSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = $this->findEvent($club, $eventSlug);

        return Inertia::render('Public/Event', [
            'club' => $this->clubPayload($club),
            'event' => EventPayload::forMember($event),
            'canBookAsGuest' => $event->allow_public_registration && $event->visibility->value === 'public' && ! in_array($event->status, ['cancelled', 'completed'], true),
            'isSignedIn' => Auth::check(),
        ]);
    }

    public function register(Request $request, string $clubSlug, string $eventSlug, EventRegistrationService $registrations): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = $this->findEvent($club, $eventSlug);

        $validated = $request->validate([
            // A hidden field real people leave empty; bots fill it in.
            'website' => 'nullable|string|max:0',
            'contact_name' => 'required|string|max:150',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'attendance_status' => 'nullable|in:attending,tentative',
            'attendees' => 'required|array|min:1|max:50',
            'attendees.*.name' => 'nullable|string|max:150',
            'attendees.*.is_guest' => 'nullable|boolean',
            'attendees.*.ticket_tier_id' => 'nullable|integer',
            'attendees.*.attending_dining' => 'nullable|boolean',
            'attendees.*.starter_item_id' => 'nullable|integer',
            'attendees.*.main_item_id' => 'nullable|integer',
            'attendees.*.dessert_item_id' => 'nullable|integer',
            'attendees.*.dietary_requirements' => 'nullable|string|max:1000',
        ]);

        $email = Str::lower($validated['contact_email']);

        // The same email booking twice gets its link re-sent rather than a second booking, and the
        // answer looks the same either way so this cannot be used to find out who has booked.
        $existing = EventRegistration::where('event_id', $event->id)->whereNull('user_id')->where('contact_email', $email)->first();

        if ($existing) {
            $token = Str::random(40);
            $existing->update(['token_hash' => hash('sha256', $token)]);
            Mail::to($email)->send(new EventBookingMail($event, $existing, $this->manageUrl($club, $token)));

            return $this->thanks($club, $event);
        }

        $registration = $registrations->register($event, null, [
            'status' => $validated['attendance_status'] ?? 'attending',
            'contact_name' => $validated['contact_name'],
            'contact_email' => $email,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'attendees' => collect($validated['attendees'])->values()->map(fn (array $person, int $index) => [...$person, 'is_guest' => $index > 0])->all(),
        ]);

        Mail::to($email)->send(new EventBookingMail($event, $registration, $this->manageUrl($club, (string) $registration->plainToken)));

        return $this->thanks($club, $event);
    }

    public function booking(string $clubSlug, string $token): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $registration = $this->findRegistration($club, $token);

        if (! $registration) {
            return Inertia::render('Public/EventBooking', ['club' => $this->clubPayload($club), 'expired' => true]);
        }

        $event = $registration->event->load(['menuItems', 'ticketTiers', 'club']);

        return Inertia::render('Public/EventBooking', [
            'club' => $this->clubPayload($club),
            'expired' => false,
            'token' => $token,
            'event' => EventPayload::forMember($event, $registration),
            'contactName' => $registration->contact_name,
        ]);
    }

    public function cancel(string $clubSlug, string $token, EventRegistrationService $registrations): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $registration = $this->findRegistration($club, $token);

        abort_if($registration === null, 404);

        if ($registration->attendees()->whereNotNull('checked_in_at')->exists()) {
            return back()->withErrors(['registration' => 'This booking has already been checked in and can no longer be cancelled.']);
        }

        $registrations->cancel($registration);

        return back()->with('success', 'Your booking has been cancelled.');
    }

    private function findEvent(Club $club, string $eventSlug): Event
    {
        return Event::where('club_id', $club->id)
            ->published()
            ->visibleTo(Auth::user())
            ->with(['menuItems', 'ticketTiers', 'club'])
            ->where('slug', $eventSlug)
            ->firstOrFail();
    }

    private function findRegistration(Club $club, string $token): ?EventRegistration
    {
        if (strlen($token) < 20) {
            return null;
        }

        return EventRegistration::with(['event', 'attendees.starter', 'attendees.main', 'attendees.dessert'])
            ->where('token_hash', hash('sha256', $token))
            ->whereHas('event', fn ($q) => $q->where('club_id', $club->id))
            ->first();
    }

    private function manageUrl(Club $club, string $token): string
    {
        return route('public.event.booking', ['clubSlug' => $club->slug, 'token' => $token]);
    }

    private function thanks(Club $club, Event $event): RedirectResponse
    {
        return redirect()->route('public.event', ['clubSlug' => $club->slug, 'eventSlug' => $event->slug])
            ->with('success', 'Thank you. If the details are right, we have emailed you a private link to view or cancel your booking.');
    }

    /**
     * Only what a public page needs; the club's settings hold private details.
     *
     * @return array{name: string, slug: string, logo_url: ?string}
     */
    private function clubPayload(Club $club): array
    {
        return ['name' => $club->name, 'slug' => $club->slug, 'logo_url' => $club->logo_url];
    }
}
