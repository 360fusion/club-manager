<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventRegistration;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A booking's ticket: the member shows its QR code at the door, and the organiser's phone camera opens the booking
 * from it. The code is a signed link, so a ticket cannot be made up by changing a number in it.
 */
class EventTicketController extends Controller
{
    public function show(Request $request, string $slug, int $id): Response
    {
        $club = $this->findClub($slug);
        $event = Event::where('club_id', $club->id)->findOrFail($id);
        $booking = EventRegistration::with(['attendees.starter', 'attendees.main', 'attendees.dessert'])
            ->where('event_id', $event->id)
            ->where('user_id', $request->user()->id)
            ->whereIn('status', EventRegistration::HOLDING_PLACE)
            ->firstOrFail();

        $doorUrl = URL::signedRoute('admin.events.ticket', ['clubSlug' => $club->slug, 'id' => $event->id, 'registration' => $booking->id]);

        return Inertia::render('Member/EventTicket', [
            'club' => ['name' => $club->name, 'slug' => $club->slug],
            'event' => $this->eventPayload($event),
            'booking' => $this->bookingPayload($booking),
            'qrSvg' => $this->qrSvg($doorUrl),
        ]);
    }

    /**
     * What the organiser sees after scanning a ticket. The admin middleware has already checked they may run this
     * club's events; the signature checks the link came from a real ticket.
     */
    public function door(string $clubSlug, int $id, int $registration): Response
    {
        $club = $this->findClub($clubSlug);
        $event = Event::where('club_id', $club->id)->findOrFail($id);
        $booking = EventRegistration::with(['attendees.starter', 'attendees.main', 'attendees.dessert'])
            ->where('event_id', $event->id)
            ->findOrFail($registration);

        return Inertia::render('Admin/Events/Ticket', [
            'club' => $club,
            'event' => $this->eventPayload($event),
            'booking' => [
                ...$this->bookingPayload($booking),
                'holds_place' => in_array($booking->status, EventRegistration::HOLDING_PLACE, true),
                'status' => $booking->status,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'starts_at' => $event->starts_at->toIso8601String(),
            'location' => $event->formatted_location ?: $event->location,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingPayload(EventRegistration $booking): array
    {
        return [
            'id' => $booking->id,
            'name' => $booking->contact_name ?: $booking->booker()?->name,
            'payment_status' => $booking->payment_status,
            'total' => (float) $booking->total,
            'amount_due' => $booking->balanceDue(),
            'attendees' => $booking->attendees->map(fn (EventAttendee $attendee) => [
                'id' => $attendee->id,
                'name' => $attendee->name,
                'is_guest' => $attendee->is_guest,
                'meal' => $attendee->mealSummary(),
                'dietary_requirements' => $attendee->dietary_requirements,
                'checked_in_at' => $attendee->checked_in_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function qrSvg(string $text): string
    {
        $svg = (new Writer(new ImageRenderer(new RendererStyle(320, 2), new SvgImageBackEnd)))->writeString($text);

        // Without the XML declaration, so the page can place the SVG inline.
        return trim(substr($svg, strpos($svg, "\n") + 1));
    }
}
