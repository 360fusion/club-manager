<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Models\Member;
use App\Enums\ClubRole;
use App\Http\Requests\MeetingAdmin\StoreMeetingRequest;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\RecurringRule;
use App\Support\ImageDownscaler;
use App\Support\OfficersYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MeetingAdminController extends Controller
{
    /**
     * Display a list of meetings for a club with recurring rule generator options.
     */
    public function index(string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);

        $visitorUserIds = $club->users()->wherePivot('role', ClubRole::Visitor->value)->pluck('users.id');

        $meetings = Meeting::where('club_id', $club->id)
            ->withCount([
                'rsvps as dining_count' => function ($query) use ($visitorUserIds) {
                    if ($visitorUserIds->isNotEmpty()) {
                        $query->whereNotIn('user_id', $visitorUserIds);
                    }
                    $query->where('attendance_status', 'attending_dining');
                },
                'rsvps as apologies_count' => function ($query) use ($visitorUserIds) {
                    if ($visitorUserIds->isNotEmpty()) {
                        $query->whereNotIn('user_id', $visitorUserIds);
                    }
                    $query->where('attendance_status', 'apologies');
                },
                'rsvps as visitors_count' => function ($query) use ($visitorUserIds) {
                    if ($visitorUserIds->isNotEmpty()) {
                        $query->whereIn('user_id', $visitorUserIds);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                },
            ])
            ->orderBy('meeting_date', 'asc')
            ->get();

        // Convert any existing legacy meeting titles with numbers to date-based titles & compute visitor counts
        foreach ($meetings as $m) {
            if ($m->title && str_contains($m->title, 'Regular Meeting No.')) {
                $m->title = 'Meeting - '.Carbon::parse($m->meeting_date)->format('jS F Y');
                $m->meeting_number = null;
                $m->save();
            }
            if ($visitorUserIds->isNotEmpty() && $m->status === 'published' && $m->visitors_count === 0) {
                $m->visitors_count = $visitorUserIds->count();
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
        $club = $this->findClub($clubSlug);

        return Inertia::render('Admin/Meetings/Form', [
            'club' => $club,
            'meeting' => new Meeting([
                'salutation' => 'Dear Sir and Brother,',
                'starts_at' => '18:30',
                'rehearsal_starts_at' => '17:30',
                'venue' => $club->defaultMeetingVenue(),
                'dress_code' => 'Dinner Jacket, White Gloves',
                'dining_cost_member' => 20.00,
                'dining_cost_guest' => 20.00,
                'bank_sort_code' => $club->settings['bank_sort_code'] ?? null,
                'bank_account_number' => $club->settings['bank_account_number'] ?? null,
                'status' => 'draft',
                'officers_year_label' => OfficersYear::label(now(), $club->settings['installation_month'] ?? null),
            ]),
            'members' => $club->users,
        ]);
    }

    /**
     * Show form to edit an existing meeting.
     */
    public function edit(string $clubSlug, int $id): Response
    {
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)
            ->where('id', $id)
            ->with(['agendaItems', 'officerAssignments.officerRole', 'officerAssignments.user'])
            ->firstOrFail();

        if (empty($meeting->bank_sort_code) && ! empty($club->settings['bank_sort_code'])) {
            $meeting->bank_sort_code = $club->settings['bank_sort_code'];
        }
        if (empty($meeting->bank_account_number) && ! empty($club->settings['bank_account_number'])) {
            $meeting->bank_account_number = $club->settings['bank_account_number'];
        }

        $charityGrants = CharityGrant::where('club_id', $club->id)
            ->with(['proposer', 'seconder'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Admin/Meetings/Form', [
            'club' => $club,
            'meeting' => $meeting,
            'members' => $club->users()->select('users.id', 'users.name', 'users.email')->get(),
            'charityGrants' => $charityGrants,
        ]);
    }

    /**
     * Return club settings as JSON (used by the reload-from-settings button).
     */
    public function settingsJson(string $clubSlug): JsonResponse
    {
        $club = $this->findClub($clubSlug);

        return response()->json([
            'officers_roster' => $club->settings['officers_roster'] ?? [],
            'officers_year_label' => $club->settings['officers_year_label'] ?? '',
        ]);
    }

    /**
     * Store new or updated meeting.
     */
    public function store(StoreMeetingRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        if ($request->hasFile('front_page_logo_file')) {
            ImageDownscaler::apply($request->file('front_page_logo_file'));
            $path = $request->file('front_page_logo_file')->store('summons_logos', 'public');
            $validated['front_page_logo'] = asset('storage/'.$path);
        }
        unset($validated['front_page_logo_file']);

        $validated['club_id'] = $club->id;
        $validated['rsvp_cutoff_at'] = Carbon::parse($validated['meeting_date'])->subDays(5)->endOfDay();

        $agendaData = $validated['agenda_items'] ?? [];
        unset($validated['agenda_items']);

        $meeting = Meeting::updateOrCreate(['id' => $validated['id'] ?? null, 'club_id' => $club->id], Arr::except($validated, ['id']));

        if (! empty($agendaData)) {
            $meeting->agendaItems()->delete();
            foreach ($agendaData as $idx => $item) {
                if (! empty($item['title'])) {
                    $meeting->agendaItems()->create([
                        'item_number' => $idx + 1,
                        'title' => $item['title'],
                        'description' => $item['description'] ?? null,
                    ]);
                }
            }
        }

        return redirect()->route('admin.meetings.edit', ['clubSlug' => $club->slug, 'id' => $meeting->id])
            ->with('success', 'Meeting summons saved successfully.');
    }

    public function show(string $clubSlug, int $id): Response
    {
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)
            ->where('id', $id)
            ->with(['agendaItems', 'officerAssignments.user', 'officerAssignments.officerRole', 'fraternalVisits', 'rsvps.user', 'rsvps.guests', 'financialReturn'])
            ->firstOrFail();

        $subscribingMembers = $club->users()->wherePivot('role', '!=', 'visitor')->get()->unique('id');

        $rsvps = MeetingRsvp::where('meeting_id', $meeting->id)
            ->with(['user.clubs', 'guests'])
            ->get()
            ->unique('user_id')
            ->map(function ($rsvp) use ($club) {
                $clubUser = $rsvp->user ? $rsvp->user->clubs->firstWhere('id', $club->id)?->pivot : null;
                $rsvp->is_visitor = $clubUser && $clubUser->role === 'visitor';
                $rsvp->visitor_home_club = $rsvp->is_visitor ? trim(($clubUser->home_club_name ?? '').($clubUser->home_club_number ? ' No '.$clubUser->home_club_number : '')) : null;

                return $rsvp;
            });

        // Separate subscribing member RSVPs from visitor RSVPs
        $memberRsvps = $rsvps->where('is_visitor', false);
        $visitorRsvps = $rsvps->where('is_visitor', true);

        $memberAttendingDining = $memberRsvps->where('attendance_status', 'attending_dining');
        $memberAttendingMeetingOnly = $memberRsvps->where('attendance_status', 'attending_meeting_only');
        $memberApologies = $memberRsvps->where('attendance_status', 'apologies');

        $visitorAttendingDining = $visitorRsvps->where('attendance_status', 'attending_dining');
        $visitingAttendingCount = $visitorRsvps->whereIn('attendance_status', ['attending_dining', 'attending_meeting_only'])->count();

        // Calculate awaiting RSVPs strictly for subscribing members (0 until invites dispatched)
        $awaitingSubscribingMembers = ($meeting->status === 'published')
            ? max(0, $subscribingMembers->count() - $memberRsvps->count())
            : 0;

        // Caterer & Guest headcount calculations
        $totalGuestsCount = 0;
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
                $totalGuestsCount++;
                if ($guest->attending_dining) {
                    $guestMealsCount++;
                }
                if ($guest->dietary_requirements) {
                    $dietaryConstraints[] = [
                        'person' => $guest->guest_name.' (Guest)',
                        'requirement' => $guest->dietary_requirements,
                    ];
                }
            }
        }

        $visitorUsers = $club->users()->wherePivot('role', ClubRole::Visitor->value)->get()->unique('id');
        $visitorsList = $visitorUsers->map(function ($visitor) use ($meeting) {
            $rsvp = MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $visitor->id)->first();
            $pivot = $visitor->pivot;
            $summonsSent = $rsvp ? true : ($meeting->status === 'published');

            return [
                'id' => $visitor->id,
                'name' => $visitor->name,
                'email' => $visitor->email,
                'rank' => $pivot->rank ?? null,
                'home_club_name' => $pivot->home_club_name,
                'home_club_number' => $pivot->home_club_number,
                'home_club_info' => trim(($pivot->home_club_name ?? '').($pivot->home_club_number ? ' No '.$pivot->home_club_number : '')),
                'phone' => $pivot->phone,
                'dietary_notes' => $rsvp?->dietary_requirements ?? $pivot->dietary_notes,
                'attendance_status' => $rsvp?->attendance_status ?: ($summonsSent ? 'awaiting' : 'not_sent'),
                'summons_sent' => $summonsSent,
                'responded_at' => $rsvp?->responded_at?->format('d M Y H:i'),
                'payment_reference' => $rsvp?->payment_reference,
                'payment_status' => $rsvp?->payment_status ?? 'unpaid',
            ];
        });

        $totalCatererHeadcount = $memberAttendingDining->count() + $visitorAttendingDining->count() + $guestMealsCount;
        $totalVisitorsAttending = $visitingAttendingCount + $totalGuestsCount;

        $allClubUsers = $club->users->unique('id')->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->pivot->role ?? 'member',
            'rank' => $u->pivot->rank ?? '',
        ])->values();

        return Inertia::render('Admin/Meetings/Dashboard', [
            'club' => $club,
            'meeting' => $meeting,
            'rsvps' => $rsvps,
            'visitorsList' => $visitorsList,
            'allClubUsers' => $allClubUsers,
            'stats' => [
                'total_members' => $subscribingMembers->count(),
                'total_attending' => $memberAttendingDining->count() + $memberAttendingMeetingOnly->count() + $totalVisitorsAttending,
                'total_dining' => $totalCatererHeadcount,
                'dining_members_and_visitors' => $memberAttendingDining->count() + $visitorAttendingDining->count(),
                'attending_dining' => $memberAttendingDining->count(),
                'attending_meeting_only' => $memberAttendingMeetingOnly->count(),
                'apologies' => $memberApologies->count(),
                'awaiting' => $awaitingSubscribingMembers,
                'visiting_count' => $visitorsList->count(),
                'visiting_attending' => $totalVisitorsAttending,
                'visiting_attending_users' => $visitingAttendingCount,
                'visiting_dining' => $visitorAttendingDining->count(),
                'guest_meals' => $guestMealsCount,
                'total_guests' => $totalGuestsCount,
                'total_caterer_headcount' => $totalCatererHeadcount,
                'dietary_constraints' => $dietaryConstraints,
            ],
        ]);
    }

    /**
     * Duplicate an existing meeting summons with its details, agenda items, and officer roles.
     */
    public function duplicate(string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $original = Meeting::where('club_id', $club->id)
            ->where('id', $id)
            ->with(['agendaItems', 'officerAssignments'])
            ->firstOrFail();

        $newMeeting = $original->replicate([
            'summons_published_at',
            'created_at',
            'updated_at',
        ]);

        $newMeeting->title = $original->title ? ($original->title.' (Copy)') : ('Meeting Copy - '.Carbon::parse($original->meeting_date)->format('jS F Y'));
        $newMeeting->status = 'draft';
        $newMeeting->meeting_date = Carbon::parse($original->meeting_date)->addMonth()->format('Y-m-d');
        $newMeeting->rsvp_cutoff_at = Carbon::parse($newMeeting->meeting_date)->subDays(5)->endOfDay();
        $newMeeting->save();

        // Duplicate Agenda Items
        foreach ($original->agendaItems as $item) {
            $newMeeting->agendaItems()->create([
                'item_number' => $item->item_number,
                'title' => $item->title,
                'description' => $item->description,
            ]);
        }

        // Duplicate Officer Assignments
        foreach ($original->officerAssignments as $assignment) {
            $newMeeting->officerAssignments()->create([
                'officer_role_id' => $assignment->officer_role_id,
                'user_id' => $assignment->user_id,
                'custom_name' => $assignment->custom_name,
                'prefix_titles' => $assignment->prefix_titles,
                'suffix_titles' => $assignment->suffix_titles,
            ]);
        }

        return redirect()->route('admin.meetings.edit', ['clubSlug' => $club->slug, 'id' => $newMeeting->id])
            ->with('success', 'Meeting summons duplicated successfully as draft! You can now adjust the date and details.');
    }

    /**
     * Delete a meeting.
     */
    public function destroy(string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();
        $meeting->delete();

        return redirect()->route('admin.meetings.index', ['clubSlug' => $club->slug])
            ->with('success', 'Meeting removed successfully.');
    }
}
