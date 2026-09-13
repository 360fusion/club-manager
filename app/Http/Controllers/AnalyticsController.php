<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    /**
     * Executive Analytics & Revenue Dashboard for a club.
     */
    public function show(string $slug): Response
    {
        $club = Club::where('slug', $slug)
            ->with(['membershipPlans', 'events.attendees'])
            ->withCount(['users', 'events', 'posts'])
            ->firstOrFail();

        // 1. Membership Revenue Projection
        $monthlyDuesEst = $club->membershipPlans->sum('price') * max(1, $club->users_count);

        // 2. Event & RSVP Revenue
        $totalEventRevenue = DB::table('event_user')
            ->join('events', 'events.id', '=', 'event_user.event_id')
            ->where('events.club_id', $club->id)
            ->where('event_user.payment_status', 'paid')
            ->sum('event_user.amount_paid');

        // 3. RSVP Attendance Metrics
        $totalRSVPs = DB::table('event_user')
            ->join('events', 'events.id', '=', 'event_user.event_id')
            ->where('events.club_id', $club->id)
            ->count();

        $attendingRSVPs = DB::table('event_user')
            ->join('events', 'events.id', '=', 'event_user.event_id')
            ->where('events.club_id', $club->id)
            ->where('event_user.attendance_status', 'attending')
            ->count();

        $attendanceRate = $totalRSVPs > 0 ? round(($attendingRSVPs / $totalRSVPs) * 100, 1) : 0;

        $upcomingMeetings = \App\Models\Meeting::where('club_id', $club->id)
            ->withCount([
                'rsvps as dining_count' => function ($query) {
                    $query->where('attendance_status', 'attending_dining');
                },
                'rsvps as apologies_count' => function ($query) {
                    $query->where('attendance_status', 'apologies');
                },
            ])
            ->orderBy('meeting_date', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'title' => $m->title && !str_contains($m->title, 'Regular Meeting No.') ? $m->title : ('Meeting - ' . \Illuminate\Support\Carbon::parse($m->meeting_date)->format('jS F Y')),
                    'meeting_date' => \Illuminate\Support\Carbon::parse($m->meeting_date)->format('D, jS M Y'),
                    'starts_at' => $m->starts_at ? substr($m->starts_at, 0, 5) : '18:30',
                    'venue' => $m->venue,
                    'status' => $m->status,
                    'dining_count' => $m->dining_count,
                    'apologies_count' => $m->apologies_count,
                ];
            });

        return Inertia::render('Admin/Analytics', [
            'club' => $club,
            'upcomingMeetings' => $upcomingMeetings,
            'metrics' => [
                'total_members' => $club->users_count,
                'monthly_dues_est' => number_format($monthlyDuesEst, 2),
                'total_event_revenue' => number_format($totalEventRevenue, 2),
                'total_rsvps' => $totalRSVPs,
                'attending_rsvps' => $attendingRSVPs,
                'attendance_rate' => $attendanceRate,
                'events_count' => $club->events_count,
                'posts_count' => $club->posts_count,
            ],
        ]);
    }
}
