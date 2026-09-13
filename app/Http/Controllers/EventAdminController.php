<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventMenuItem;
use App\Models\EventPromo;
use App\Models\EventTicketTier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventAdminController extends Controller
{
    /**
     * Display a listing of club events in admin dashboard.
     */
    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $events = Event::where('club_id', $club->id)
            ->with(['ticketTiers', 'promos', 'menuItems'])
            ->withCount('attendees')
            ->orderByDesc('starts_at')
            ->get();

        return Inertia::render('Admin/Events/Index', [
            'club' => $club,
            'events' => $events,
        ]);
    }

    /**
     * Show form for creating or editing an event.
     */
    public function edit(string $clubSlug, ?int $id = null): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $event = $id
            ? Event::where('club_id', $club->id)->with(['ticketTiers', 'promos', 'menuItems'])->findOrFail($id)
            : new Event([
                'club_id' => $club->id,
                'starts_at' => now()->addDays(7)->format('Y-m-d\TH:i'),
                'requires_payment' => true,
                'has_dining' => false,
                'status' => 'upcoming',
            ]);

        return Inertia::render('Admin/Events/Form', [
            'club' => $club,
            'event' => $event,
        ]);
    }

    /**
     * Store or update an event with ticket tiers, promos, and dining items.
     */
    public function store(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'id' => 'nullable|integer',
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'address_line_1' => 'nullable|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'county' => 'nullable|string|max:255',
            'postcode' => 'nullable|string|max:50',
            'starts_at' => 'required|date',
            'requires_payment' => 'boolean',
            'price' => 'nullable|numeric|min:0',
            'has_dining' => 'boolean',
            'dining_price' => 'nullable|numeric|min:0',
            'booking_cutoff_days' => 'nullable|integer|min:0',
            'status' => 'required|in:upcoming,completed,cancelled',
            'ticket_tiers' => 'array',
            'promos' => 'array',
            'menu_items' => 'array',
        ]);

        $addressParts = array_filter([
            $validated['address_line_1'] ?? null,
            $validated['address_line_2'] ?? null,
            $validated['city'] ?? null,
            $validated['county'] ?? null,
            $validated['postcode'] ?? null,
        ]);
        $location = ! empty($addressParts) ? implode(', ', $addressParts) : ($validated['location'] ?? '');

        $event = Event::updateOrCreate(
            ['id' => $validated['id'] ?? null, 'club_id' => $club->id],
            [
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'description' => $validated['description'] ?? '',
                'location' => $location,
                'address_line_1' => $validated['address_line_1'] ?? null,
                'address_line_2' => $validated['address_line_2'] ?? null,
                'city' => $validated['city'] ?? null,
                'county' => $validated['county'] ?? null,
                'postcode' => $validated['postcode'] ?? null,
                'starts_at' => $validated['starts_at'],
                'requires_payment' => $validated['requires_payment'] ?? true,
                'price' => $validated['price'] ?? 0,
                'has_dining' => $validated['has_dining'] ?? false,
                'dining_price' => $validated['dining_price'] ?? 0,
                'booking_cutoff_days' => isset($validated['booking_cutoff_days']) ? (int) $validated['booking_cutoff_days'] : null,
                'status' => $validated['status'] ?? 'upcoming',
            ]
        );

        // Sync Ticket Tiers
        if (isset($validated['ticket_tiers'])) {
            $event->ticketTiers()->delete();
            foreach ($validated['ticket_tiers'] as $tier) {
                if (! empty($tier['name'])) {
                    EventTicketTier::create([
                        'event_id' => $event->id,
                        'name' => $tier['name'],
                        'price' => $tier['price'] ?? 0,
                        'max_quantity' => $tier['max_quantity'] ?? 100,
                        'sold_quantity' => $tier['sold_quantity'] ?? 0,
                    ]);
                }
            }
        }

        // Sync Promos
        if (isset($validated['promos'])) {
            $event->promos()->delete();
            foreach ($validated['promos'] as $promo) {
                if (! empty($promo['code'])) {
                    EventPromo::create([
                        'event_id' => $event->id,
                        'code' => strtoupper($promo['code']),
                        'discount_type' => 'percent',
                        'discount_amount' => $promo['discount_amount'] ?? 10,
                        'max_uses' => $promo['max_uses'] ?? 50,
                    ]);
                }
            }
        }

        // Sync Menu Items
        if (isset($validated['menu_items']) && $event->has_dining) {
            $event->menuItems()->delete();
            foreach ($validated['menu_items'] as $item) {
                if (! empty($item['name'])) {
                    EventMenuItem::create([
                        'event_id' => $event->id,
                        'category' => $item['category'] ?? 'main',
                        'name' => $item['name'],
                        'description' => $item['description'] ?? '',
                    ]);
                }
            }
        }

        return redirect()->route('admin.events.index', ['clubSlug' => $club->slug])
            ->with('success', 'Event saved successfully.');
    }

    /**
     * Delete an event.
     */
    public function destroy(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($id);
        $event->delete();

        return redirect()->back()->with('success', 'Event deleted successfully.');
    }
}
