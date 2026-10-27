<?php

namespace App\Http\Controllers;

use App\Http\Requests\EventAdmin\UpdateAttendeeMealRequest;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventRegistration;
use App\Services\Events\EventPricing;
use App\Services\Events\EventRegistrationService;
use App\Support\ClubAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The people booked on an event, as an organiser sees them: the subscriber list, cancelling or promoting a booking and changing a guest's meal. Split out of EventAdminController; the URLs, route names and permissions are unchanged.
 */
class EventRegistrationAdminController extends Controller
{
    /**
     * Everyone booked on an event: one row per person, guests included.
     */
    public function subscribers(string $clubSlug, int $id): Response
    {
        $club = $this->findClub($clubSlug);
        $event = Event::where('club_id', $club->id)
            ->with(['ticketTiers', 'menuItems'])
            ->findOrFail($id);

        $registrations = EventRegistration::with(['attendees.ticketTier', 'attendees.starter', 'attendees.main', 'attendees.dessert', 'user'])
            ->where('event_id', $event->id)
            ->orderBy('contact_name')
            ->get();

        $memberRows = DB::table('club_user')
            ->where('club_id', $club->id)
            ->whereIn('user_id', $registrations->pluck('user_id')->filter()->all())
            ->get()
            ->keyBy('user_id');

        $subscribers = $registrations->flatMap(function (EventRegistration $registration) use ($memberRows) {
            $member = $registration->user_id ? $memberRows->get($registration->user_id) : null;

            return $registration->attendees->map(fn (EventAttendee $attendee) => [
                'attendee_id' => $attendee->id,
                'registration_id' => $registration->id,
                'user_id' => $attendee->user_id,
                'name' => $attendee->name,
                'is_guest' => $attendee->is_guest,
                'booked_by' => $attendee->is_guest ? $registration->contact_name : null,
                'email' => $attendee->email ?? $registration->contact_email ?? $registration->user?->email,
                'rank' => $member?->rank ?? '',
                'role' => $member?->role ?? ($registration->user_id ? 'member' : 'guest'),
                'home_club_lodge' => $member?->home_club_name ?: ($attendee->organisation ?? ''),
                'internal_note' => $registration->internal_note,
                'added_by_organiser' => $registration->created_by !== null,
                'member_number' => $member?->member_number ?? '',
                'attendance_status' => $registration->status,
                'attending_dining' => $attendee->attending_dining,
                'menu_selections' => $attendee->mealSummary(),
                'starter_item_id' => $attendee->starter_item_id,
                'main_item_id' => $attendee->main_item_id,
                'dessert_item_id' => $attendee->dessert_item_id,
                'dietary_requirements' => $attendee->dietary_requirements ?? '',
                'payment_status' => $registration->payment_status ?? 'unpaid',
                'total' => number_format((float) $registration->total, 2, '.', ''),
                'balance' => number_format($registration->balanceDue(), 2, '.', ''),
                'amount_paid' => number_format((float) $registration->amount_paid, 2),
                'checked_in_at' => $attendee->checked_in_at?->format('j M Y, H:i'),
                'registered_at' => $registration->updated_at?->format('j M Y, H:i') ?? '',
                'table_label' => $attendee->table_label,
                'ticket_tier' => $attendee->ticketTier ? [
                    'name' => $attendee->ticketTier->name,
                    'price' => number_format((float) $attendee->ticketTier->price, 2),
                ] : null,
            ]);
        })->values();

        return Inertia::render('Admin/Events/Subscribers', [
            'club' => $club,
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'starts_at' => $event->starts_at->format('j M Y, H:i'),
                'location' => $event->formatted_location ?: $event->location,
                'has_dining' => $event->has_dining,
                'dining_price' => number_format((float) $event->dining_price, 2),
                'price' => number_format((float) $event->price, 2),
                'requires_payment' => $event->requires_payment,
                'capacity' => $event->capacity,
                'places_taken' => $event->placesTaken(),
                'menu' => $event->menuItems()->orderBy('sort_order')->get(['id', 'category', 'name'])->groupBy('category'),
                'tiers' => $event->ticketTiers->map(fn ($tier) => ['id' => $tier->id, 'name' => $tier->name, 'price' => number_format((float) $tier->price, 2, '.', ''), 'audience' => $tier->audience ?: 'all'])->values(),
                'payment_options' => app(EventPricing::class)->enabledMethods($event)->reject(fn ($option) => $option->method->isOnline())->map(fn ($option) => ['id' => $option->id, 'label' => $option->method->label, 'type' => $option->method->type])->values(),
                'is_full' => $event->capacity !== null && $event->placesTaken() >= $event->capacity,
                'waitlist_enabled' => (bool) $event->waitlist_enabled,
                'waitlisted' => $registrations->where('status', 'waitlisted')->sum(fn ($r) => $r->attendees->count()),
            ],
            'subscribers' => $subscribers,
            'canManagePayments' => ClubAccess::can(request()->user(), $club, 'manage_billing'),
        ]);
    }

    /**
     * Cancel someone's booking; the waiting list moves up.
     */
    public function cancelRegistration(string $clubSlug, int $id, int $registrationId, EventRegistrationService $registrations): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $event = Event::where('club_id', $club->id)->findOrFail($id);

        $registrations->cancel(EventRegistration::where('event_id', $event->id)->findOrFail($registrationId));

        return redirect()->back()->with('success', 'Booking cancelled.');
    }

    /**
     * An organiser changes one person's meal choices and dietary notes.
     */
    public function updateAttendeeMeal(UpdateAttendeeMealRequest $request, string $clubSlug, int $id, int $attendeeId, EventRegistrationService $registrations): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $event = Event::where('club_id', $club->id)->findOrFail($id);
        $attendee = EventAttendee::whereHas('registration', fn ($q) => $q->where('event_id', $event->id))->findOrFail($attendeeId);

        $validated = $request->validated();

        $registrations->updateAttendeeMeal($attendee, $validated);

        return redirect()->back()->with('success', 'Meal updated.');
    }

    /**
     * Move a waiting booking onto the list now.
     */
    public function promoteRegistration(string $clubSlug, int $id, int $registrationId, EventRegistrationService $registrations): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $event = Event::where('club_id', $club->id)->findOrFail($id);

        $registrations->promote(EventRegistration::where('event_id', $event->id)->where('status', 'waitlisted')->findOrFail($registrationId));

        return redirect()->back()->with('success', 'Moved off the waiting list.');
    }
}
