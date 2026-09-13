<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\RecurringRule;
use App\Models\User;
use App\Services\MeetingScheduleService;
use App\Services\RsvpTokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class MeetingAdminController extends Controller
{
    /**
     * Display a list of meetings for a club with recurring rule generator options.
     */
    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $meetings = Meeting::where('club_id', $club->id)
            ->withCount([
                'rsvps as dining_count' => function ($query) {
                    $query->where('attendance_status', 'attending_dining');
                },
                'rsvps as apologies_count' => function ($query) {
                    $query->where('attendance_status', 'apologies');
                },
            ])
            ->orderBy('meeting_date', 'asc')
            ->get();

        // Convert any existing legacy meeting titles with numbers to date-based titles
        foreach ($meetings as $m) {
            if ($m->title && str_contains($m->title, 'Regular Meeting No.')) {
                $m->title = 'Meeting - ' . Carbon::parse($m->meeting_date)->format('jS F Y');
                $m->meeting_number = null;
                $m->save();
            }
        }

        $recurringRules = RecurringRule::where('club_id', $club->id)->get();

        return Inertia::render('Admin/Meetings/Index', [
            'club' => $club,
            'meetings' => $meetings,
            'recurringRules' => $recurringRules,
        ]);
    }

    /**
     * Show form to manually create or edit a meeting.
     */
    public function create(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        return Inertia::render('Admin/Meetings/Form', [
            'club' => $club,
            'meeting' => new Meeting([
                'salutation' => 'Dear Sir and Brother,',
                'starts_at' => '18:30',
                'rehearsal_starts_at' => '17:30',
                'venue' => 'Masonic Hall, Wellington Street, Stockton-on-Tees',
                'dress_code' => 'Dinner Jacket, White Gloves',
                'dining_cost_member' => 20.00,
                'dining_cost_guest' => 20.00,
                'bank_sort_code' => '20-82-18',
                'bank_account_number' => '80288373',
                'status' => 'draft',
                'officers_year_label' => 'OFFICERS FOR 2025-2026',
            ]),
            'members' => $club->users,
        ]);
    }

    /**
     * Show form to edit an existing meeting.
     */
    public function edit(string $clubSlug, int $id): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $meeting = Meeting::where('club_id', $club->id)
            ->where('id', $id)
            ->with(['agendaItems', 'officerAssignments.officerRole', 'officerAssignments.user'])
            ->firstOrFail();

        return Inertia::render('Admin/Meetings/Form', [
            'club' => $club,
            'meeting' => $meeting,
            'members' => $club->users,
        ]);
    }

    /**
     * Store new or updated meeting.
     */
    public function store(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'id' => 'nullable|exists:meetings,id',
            'title' => 'required|string|max:255',
            'meeting_number' => 'nullable|integer',
            'meeting_date' => 'required|date',
            'starts_at' => 'required|string',
            'rehearsal_starts_at' => 'nullable|string',
            'venue' => 'required|string|max:255',
            'dress_code' => 'required|string|max:255',
            'salutation' => 'nullable|string|max:255',
            'intro_text' => 'nullable|string',
            'rehearsal_text' => 'nullable|string',
            'festive_board_theme' => 'nullable|string',
            'dining_cost_member' => 'required|numeric|min:0',
            'dining_cost_guest' => 'required|numeric|min:0',
            'bank_sort_code' => 'nullable|string|max:20',
            'bank_account_number' => 'nullable|string|max:30',
            'payment_reference_prefix' => 'nullable|string|max:50',
            'payment_link' => 'nullable|string|max:500',
            'almoner_notice' => 'nullable|string',
            'sick_distressed_notes' => 'nullable|string',
            'honorary_members_text' => 'nullable|string',
            'provincial_header_text' => 'nullable|string',
            'fraternal_visits_text' => 'nullable|string',
            'officers_year_label' => 'nullable|string|max:255',
            'front_page_logo' => 'nullable|string|max:1000',
            'front_page_logo_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            'front_page_title' => 'nullable|string|max:255',
            'provincial_grand_master' => 'nullable|string|max:255',
            'deputy_provincial_grand_master' => 'nullable|string|max:255',
            'assistant_provincial_grand_masters' => 'nullable|string',
            'cover_club_name' => 'nullable|string|max:255',
            'cover_club_number' => 'nullable|string|max:100',
            'cover_motto' => 'nullable|string|max:255',
            'cover_worshipful_master' => 'nullable|string|max:255',
            'status' => 'required|in:draft,published,completed,cancelled',
            'agenda_items' => 'nullable|array',
        ]);

        if ($request->hasFile('front_page_logo_file')) {
            $path = $request->file('front_page_logo_file')->store('summons_logos', 'public');
            $validated['front_page_logo'] = asset('storage/' . $path);
        }
        unset($validated['front_page_logo_file']);

        $validated['club_id'] = $club->id;
        $validated['rsvp_cutoff_at'] = Carbon::parse($validated['meeting_date'])->subDays(5)->endOfDay();

        $agendaData = $validated['agenda_items'] ?? [];
        unset($validated['agenda_items']);

        $meeting = Meeting::updateOrCreate(['id' => $request->id], $validated);

        if (!empty($agendaData)) {
            $meeting->agendaItems()->delete();
            foreach ($agendaData as $idx => $item) {
                if (!empty($item['title'])) {
                    $meeting->agendaItems()->create([
                        'item_number' => $idx + 1,
                        'title' => $item['title'],
                        'description' => $item['description'] ?? null,
                    ]);
                }
            }
        }

        return redirect()->route('admin.meetings.index', ['clubSlug' => $club->slug])
            ->with('success', 'Meeting summons updated successfully.');
    }

    /**
     * Render 2-page printable HTML/PDF summons view.
     */
    public function pdf(string $clubSlug, int $id)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)
            ->with(['agendaItems', 'officerAssignments.officerRole', 'officerAssignments.user'])
            ->firstOrFail();

        $members = $club->users;
        $secretaryUser = $club->users()->wherePivot('role', 'secretary')->first() ?: $club->users->first();
        $worshipfulMaster = $club->users()->wherePivot('role', 'master')->first() ?: $club->users->first();

        $viewData = [
            'club' => $club,
            'meeting' => $meeting,
            'members' => $members,
            'officerAssignments' => $meeting->officerAssignments,
            'secretaryUser' => $secretaryUser,
            'worshipfulMaster' => $worshipfulMaster,
        ];

        if (request()->has('download')) {
            $html = view('summons.pdf', $viewData)->render();
            $filename = 'Summons-' . \Illuminate\Support\Str::slug($club->name) . '-' . $meeting->meeting_date->format('Y-m-d') . '.html';

            return response($html)
                ->header('Content-Type', 'text/html')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
        }

        return view('summons.pdf', $viewData);
    }

    /**
     * Display administrative attendance dashboard & caterer breakdown.
     */
    public function show(string $clubSlug, int $id): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $meeting = Meeting::where('club_id', $club->id)
            ->where('id', $id)
            ->with(['agendaItems', 'officerAssignments.user', 'officerAssignments.officerRole', 'fraternalVisits', 'rsvps.user', 'rsvps.guests'])
            ->firstOrFail();

        $allMembers = $club->users;

        $rsvps = MeetingRsvp::where('meeting_id', $meeting->id)
            ->with(['user', 'guests'])
            ->get();

        $attendingDining = $rsvps->where('attendance_status', 'attending_dining');
        $attendingMeetingOnly = $rsvps->where('attendance_status', 'attending_meeting_only');
        $apologies = $rsvps->where('attendance_status', 'apologies');

        // Caterer headcount calculations
        $guestMealsCount = 0;
        $dietaryConstraints = [];

        foreach ($rsvps as $rsvp) {
            if ($rsvp->dietary_requirements) {
                $dietaryConstraints[] = [
                    'person' => $rsvp->user ? $rsvp->user->name : 'Member',
                    'requirement' => $rsvp->dietary_requirements,
                ];
            }
            foreach ($rsvp->guests as $guest) {
                if ($guest->attending_dining) {
                    $guestMealsCount++;
                }
                if ($guest->dietary_requirements) {
                    $dietaryConstraints[] = [
                        'person' => $guest->guest_name . ' (Guest)',
                        'requirement' => $guest->dietary_requirements,
                    ];
                }
            }
        }

        $totalCatererHeadcount = $attendingDining->count() + $guestMealsCount;

        return Inertia::render('Admin/Meetings/Dashboard', [
            'club' => $club,
            'meeting' => $meeting,
            'rsvps' => $rsvps,
            'stats' => [
                'total_members' => $allMembers->count(),
                'attending_dining' => $attendingDining->count(),
                'attending_meeting_only' => $attendingMeetingOnly->count(),
                'apologies' => $apologies->count(),
                'awaiting' => max(0, $allMembers->count() - $rsvps->count()),
                'guest_meals' => $guestMealsCount,
                'total_caterer_headcount' => $totalCatererHeadcount,
                'dietary_constraints' => $dietaryConstraints,
            ],
        ]);
    }

    /**
     * Batch generate season meetings using recurring rule engine.
     */
    public function generateSeason(Request $request, string $clubSlug, MeetingScheduleService $scheduleService): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $request->validate([
            'year' => 'required|integer|min:2025|max:2035',
            'occurrence' => 'required|in:1st,2nd,3rd,4th,last',
            'day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'starts_at' => 'nullable|string',
            'rehearsal_starts_at' => 'nullable|string',
            'active_months' => 'required|array|min:1',
        ]);

        $startTime = $request->starts_at ?: '18:30';
        $rehearsalTime = $request->rehearsal_starts_at ?: '17:30';

        $rule = RecurringRule::create([
            'club_id' => $club->id,
            'name' => "Regular Meetings ({$request->year})",
            'occurrence' => $request->occurrence,
            'day_of_week' => $request->day_of_week,
            'active_months' => $request->active_months,
            'default_start_time' => $startTime,
            'default_rehearsal_time' => $rehearsalTime,
            'default_venue' => 'Masonic Hall, Oxford',
            'default_dress_code' => 'Dark Suit, Craft Regalia',
        ]);

        $dates = $scheduleService->generateSeasonDates(
            (int)$request->year,
            $request->active_months,
            $request->occurrence,
            $request->day_of_week
        );

        $count = 0;
        foreach ($dates as $index => $date) {
            Meeting::firstOrCreate(
                [
                    'club_id' => $club->id,
                    'meeting_date' => $date->format('Y-m-d'),
                ],
                [
                    'recurring_rule_id' => $rule->id,
                    'meeting_number' => null,
                    'title' => 'Meeting - ' . $date->format('jS F Y'),
                    'starts_at' => $startTime,
                    'rehearsal_starts_at' => $rehearsalTime,
                    'venue' => 'Masonic Hall, Oxford',
                    'dress_code' => 'Dark Suit, Craft Regalia',
                    'dining_cost_member' => 35.00,
                    'dining_cost_guest' => 35.00,
                    'bank_sort_code' => '20-65-18',
                    'bank_account_number' => '83920145',
                    'payment_reference_prefix' => 'SUMMONS',
                    'rsvp_cutoff_at' => $date->copy()->subDays(5)->endOfDay(),
                    'status' => 'draft',
                ]
            );
            $count++;
        }

        return redirect()->back()->with('success', "Batch generated {$count} season meetings for {$request->year}.");
    }

    /**
     * Publish summons & issue passwordless tokens to members.
     */
    public function publishSummons(string $clubSlug, int $id, RsvpTokenService $tokenService): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $meeting->update([
            'status' => 'published',
            'summons_published_at' => Carbon::now(),
        ]);

        $expiresAt = Carbon::parse($meeting->meeting_date)->endOfDay();
        $members = $club->users;

        foreach ($members as $member) {
            $tokenService->createTokenForUser($meeting, $member, $expiresAt);
        }

        return redirect()->back()->with('success', "Summons published! Passwordless RSVP tokens issued to {$members->count()} members.");
    }

    /**
     * Delete a meeting.
     */
    public function destroy(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();
        $meeting->delete();

        return redirect()->route('admin.meetings.index', ['clubSlug' => $club->slug])
            ->with('success', 'Meeting removed successfully.');
    }
}
