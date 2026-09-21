<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * Display real-time attendance check-in roster console.
     */
    public function show(string $clubSlug, int $eventId): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($eventId);

        $attendees = DB::table('event_user')
            ->join('users', 'users.id', '=', 'event_user.user_id')
            ->where('event_user.event_id', $event->id)
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'event_user.attendance_status',
                'event_user.attending_dining',
                'event_user.menu_selections',
                'event_user.dietary_requirements',
                'event_user.payment_status',
                'event_user.checked_in_at'
            )
            ->get()
            ->map(function ($attendee) {
                return [
                    'id' => $attendee->id,
                    'name' => $attendee->name,
                    'email' => $attendee->email,
                    'attendance_status' => $attendee->attendance_status,
                    'attending_dining' => (bool) $attendee->attending_dining,
                    'menu_selections' => json_decode($attendee->menu_selections ?? '{}', true),
                    'dietary_requirements' => $attendee->dietary_requirements,
                    'payment_status' => $attendee->payment_status,
                    'checked_in_at' => $attendee->checked_in_at ? Carbon::parse($attendee->checked_in_at)->format('H:i:s') : null,
                ];
            });

        return Inertia::render('Admin/Events/CheckIn', [
            'club' => $club,
            'event' => $event,
            'attendees' => $attendees,
        ]);
    }

    /**
     * Mark a member as checked in or undo check-in.
     */
    public function checkIn(Request $request, string $clubSlug, int $eventId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $event = Event::where('club_id', $club->id)->findOrFail($eventId);

        $validated = $request->validate([
            'user_id' => 'required|integer',
            'action' => 'required|in:checkin,undo',
        ]);

        abort_unless($club->users()->where('users.id', $validated['user_id'])->exists(), 422, 'That person is not a member of this club.');

        if ($validated['action'] === 'checkin') {
            DB::table('event_user')->updateOrInsert(
                ['event_id' => $event->id, 'user_id' => $validated['user_id']],
                [
                    'attendance_status' => 'attending',
                    'checked_in_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $message = 'Member checked in successfully.';
        } else {
            DB::table('event_user')
                ->where('event_id', $event->id)
                ->where('user_id', $validated['user_id'])
                ->update(['checked_in_at' => null, 'updated_at' => now()]);
            $message = 'Check-in undone.';
        }

        return redirect()->back()->with('success', $message);
    }
}
