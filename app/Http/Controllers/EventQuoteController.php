<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Services\Events\EventPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * The live price on the booking screen: what each payment option would cost for the people entered so far.
 * It uses the same pricing engine that charges the booking, so the screen and the bill can never disagree.
 */
class EventQuoteController extends Controller
{
    public function member(Request $request, string $slug, int $id, EventPricing $pricing): JsonResponse
    {
        $club = Club::where('slug', $slug)->firstOrFail();
        $user = Auth::user();
        $event = Event::where('club_id', $club->id)->published()->visibleTo($user)->findOrFail($id);

        abort_unless($event->canBeRsvpedBy($user), 403);

        return $this->respond($request, $event, $pricing);
    }

    /**
     * The same price, for an organiser building a booking on the registrations page.
     */
    public function organiser(Request $request, string $clubSlug, int $id, EventPricing $pricing): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($id);

        return $this->respond($request, $event, $pricing);
    }

    public function guest(Request $request, string $clubSlug, string $eventSlug, EventPricing $pricing): JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->published()->visibleTo(Auth::user())->where('slug', $eventSlug)->firstOrFail();

        abort_unless($event->allow_public_registration && $event->visibility->value === 'public', 403);

        return $this->respond($request, $event, $pricing);
    }

    private function respond(Request $request, Event $event, EventPricing $pricing): JsonResponse
    {
        $validated = $request->validate([
            'promo_code' => 'nullable|string|max:40',
            'attendees' => 'required|array|min:1|max:50',
            'attendees.*.is_guest' => 'nullable|boolean',
            'attendees.*.ticket_tier_id' => 'nullable|integer|max:4294967295',
            'attendees.*.attending_dining' => 'nullable|boolean',
        ]);

        $people = collect($validated['attendees'])->values()->map(fn (array $person, int $index) => [
            'is_guest' => (bool) ($person['is_guest'] ?? $index > 0),
            'ticket_tier_id' => $person['ticket_tier_id'] ?? null,
            'attending_dining' => (bool) ($person['attending_dining'] ?? false),
        ])->all();

        try {
            $options = $pricing->priceTable($event, $people, $validated['promo_code'] ?? null);
            $single = $options === [] ? $pricing->quote($event, $people, $validated['promo_code'] ?? null) : null;
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'charged' => (bool) $event->requires_payment,
            'options' => $options,
            // With no payment options set up there is just one price.
            'quote' => $single,
            'advertised' => $pricing->advertised($event),
        ]);
    }
}
