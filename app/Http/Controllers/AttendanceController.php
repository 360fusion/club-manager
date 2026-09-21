<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventRegistration;
use App\Services\Events\EventRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * Live check-in roster: everyone holding a place, guests included.
     */
    public function show(string $clubSlug, int $eventId): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($eventId);

        $attendees = EventRegistration::with(['attendees.starter', 'attendees.main', 'attendees.dessert', 'user'])
            ->where('event_id', $event->id)
            ->whereIn('status', EventRegistration::HOLDING_PLACE)
            ->orderBy('contact_name')
            ->get()
            ->flatMap(fn (EventRegistration $registration) => $registration->attendees->map(fn (EventAttendee $attendee) => [
                'id' => $attendee->id,
                'name' => $attendee->name,
                'is_guest' => $attendee->is_guest,
                'booked_by' => $attendee->is_guest ? $registration->contact_name : null,
                'email' => $registration->contact_email ?? $registration->user?->email ?? '',
                'attendance_status' => $registration->status,
                'attending_dining' => $attendee->attending_dining,
                'meal' => $attendee->mealSummary(),
                'dietary_requirements' => $attendee->dietary_requirements,
                'payment_status' => $registration->payment_status,
                'checked_in_at' => $attendee->checked_in_at?->format('H:i:s'),
            ]))
            ->values();

        return Inertia::render('Admin/Events/CheckIn', [
            'club' => $club,
            'event' => $event,
            'attendees' => $attendees,
        ]);
    }

    /**
     * Mark one person as checked in or undo it.
     */
    public function checkIn(Request $request, string $clubSlug, int $eventId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($eventId);

        $validated = $request->validate([
            'attendee_id' => 'required|integer',
            'action' => 'required|in:checkin,undo',
        ]);

        $attendee = EventAttendee::whereHas('registration', fn ($q) => $q->where('event_id', $event->id))->findOrFail($validated['attendee_id']);

        $attendee->update(['checked_in_at' => $validated['action'] === 'checkin' ? now() : null]);

        return redirect()->back()->with('success', $validated['action'] === 'checkin' ? $attendee->name.' checked in.' : 'Check-in undone.');
    }

    /**
     * Someone who turned up without a booking.
     */
    public function walkIn(Request $request, string $clubSlug, int $eventId, EventRegistrationService $registrations): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($eventId);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'dietary_requirements' => 'nullable|string|max:1000',
        ]);

        $attendee = $registrations->walkIn($event, $validated['name'], $validated['dietary_requirements'] ?? null);

        return redirect()->back()->with('success', $attendee->name.' added and checked in.');
    }
}
