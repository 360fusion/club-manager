<?php

namespace App\Http\Controllers;

use App\Http\Requests\Attendance\CheckInRequest;
use App\Http\Requests\Attendance\WalkInRequest;
use App\Models\ClubPaymentMethod;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventRegistration;
use App\Services\Events\EventPricing;
use App\Services\Events\EventRegistrationService;
use App\Support\ClubAccess;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * Live check-in roster: everyone holding a place, guests included.
     */
    public function show(string $clubSlug, int $eventId, EventPricing $pricing): Response
    {
        $club = $this->findClub($clubSlug);
        $event = Event::where('club_id', $club->id)->with(['ticketTiers', 'menuItems'])->findOrFail($eventId);

        $attendees = EventRegistration::with(['attendees.starter', 'attendees.main', 'attendees.dessert', 'user', 'paymentMethod'])
            ->where('event_id', $event->id)
            ->whereIn('status', EventRegistration::HOLDING_PLACE)
            ->orderBy('contact_name')
            ->get()
            ->flatMap(fn (EventRegistration $registration) => $registration->attendees->map(fn (EventAttendee $attendee) => [
                'id' => $attendee->id,
                'registration_id' => $registration->id,
                'name' => $attendee->name,
                'is_booker' => $attendee->id === $registration->booker()?->id,
                'is_visitor' => ! $attendee->is_guest && $registration->user_id === null,
                'organisation' => $attendee->organisation,
                'is_guest' => $attendee->is_guest,
                'booked_by' => $attendee->is_guest ? $registration->contact_name : null,
                'email' => $registration->contact_email ?? $registration->user?->email ?? '',
                'attendance_status' => $registration->status,
                'attending_dining' => $attendee->attending_dining,
                'meal' => $attendee->mealSummary(),
                'dietary_requirements' => $attendee->dietary_requirements,
                'payment_status' => $registration->payment_status,
                'balance' => number_format($registration->balanceDue(), 2, '.', ''),
                'pays_at_door' => $registration->paymentMethod?->type === ClubPaymentMethod::DOOR,
                'checked_in_at' => $attendee->checked_in_at?->format('H:i:s'),
            ]))
            ->values();

        return Inertia::render('Admin/Events/CheckIn', [
            'club' => $club,
            // The fields the add-a-booking popup needs, as the registrations page sends them.
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'starts_at' => $event->starts_at,
                'location' => $event->formatted_location ?: $event->location,
                'has_dining' => $event->has_dining,
                'dining_price' => number_format((float) $event->dining_price, 2),
                'capacity' => $event->capacity,
                'places_taken' => $event->placesTaken(),
                'menu' => $event->menuItems->sortBy('sort_order')->groupBy('category')->map(fn ($items) => $items->map->only(['id', 'category', 'name'])->values()),
                'tiers' => $event->ticketTiers->map(fn ($tier) => ['id' => $tier->id, 'name' => $tier->name, 'price' => number_format((float) $tier->price, 2, '.', ''), 'audience' => $tier->audience ?: 'all'])->values(),
                'payment_options' => $pricing->enabledMethods($event)->reject(fn ($option) => $option->method->isOnline())->map(fn ($option) => ['id' => $option->id, 'label' => $option->method->label, 'type' => $option->method->type])->values(),
            ],
            'attendees' => $attendees,
            'canManagePayments' => ClubAccess::can(request()->user(), $club, 'manage_billing'),
        ]);
    }

    /**
     * Mark one person, or everyone in a booking, as checked in or undo it.
     */
    public function checkIn(CheckInRequest $request, string $clubSlug, int $eventId): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $event = Event::where('club_id', $club->id)->findOrFail($eventId);

        $validated = $request->validated();

        $inEvent = EventAttendee::whereHas('registration', fn ($q) => $q->where('event_id', $event->id));
        $attendees = isset($validated['attendee_id'])
            ? collect([$inEvent->findOrFail($validated['attendee_id'])])
            : $inEvent->where('registration_id', $validated['registration_id'])->get();
        abort_if($attendees->isEmpty(), 404);

        EventAttendee::whereKey($attendees->pluck('id'))->update(['checked_in_at' => $validated['action'] === 'checkin' ? now() : null]);

        if ($validated['action'] === 'undo') {
            return redirect()->back()->with('success', 'Check-in undone.');
        }

        return redirect()->back()->with('success', $attendees->count() === 1 ? $attendees->first()->name.' checked in.' : $attendees->count().' people checked in.');
    }

    /**
     * Someone who turned up without a booking.
     */
    public function walkIn(WalkInRequest $request, string $clubSlug, int $eventId, EventRegistrationService $registrations): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $event = Event::where('club_id', $club->id)->findOrFail($eventId);

        $validated = $request->validated();

        $attendee = $registrations->walkIn($event, $validated['name'], $validated['dietary_requirements'] ?? null);

        return redirect()->back()->with('success', $attendee->name.' added and checked in.');
    }
}
