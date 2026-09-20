<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\AnnualOfficerRoster;
use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\AnnualOfficerRosterService;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\RecurringRule;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\MeetingScheduleService;
use App\Services\RsvpTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;

class MeetingAdminController extends Controller
{
    /**
     * Display a list of meetings for a club with recurring rule generator options.
     */
    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $visitorUserIds = $club->users()->wherePivot('role', 'visitor')->pluck('users.id');

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
                'bank_sort_code' => $club->settings['bank_sort_code'] ?? '20-65-18',
                'bank_account_number' => $club->settings['bank_account_number'] ?? '83920145',
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
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        return response()->json([
            'officers_roster' => $club->settings['officers_roster'] ?? [],
            'officers_year_label' => $club->settings['officers_year_label'] ?? '',
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
            'festive_board_menu' => 'nullable|string',
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
            'officers_roster' => 'nullable|array',
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
            $validated['front_page_logo'] = asset('storage/'.$path);
        }
        unset($validated['front_page_logo_file']);

        $validated['club_id'] = $club->id;
        $validated['rsvp_cutoff_at'] = Carbon::parse($validated['meeting_date'])->subDays(5)->endOfDay();

        $agendaData = $validated['agenda_items'] ?? [];
        unset($validated['agenda_items']);

        $meeting = Meeting::updateOrCreate(['id' => $request->id], $validated);

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

    /**
     * Render 2-page printable HTML/PDF summons view.
     */
    public function pdf(string $clubSlug, int $id)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)
            ->with(['agendaItems', 'officerAssignments.officerRole', 'officerAssignments.user'])
            ->firstOrFail();

        $members = $club->users()->wherePivot('role', '!=', 'visitor')->get();
        $secretaryUser = $club->users()->wherePivot('role', 'secretary')->first() ?: $club->users->first();
        $worshipfulMaster = $club->users()->wherePivot('role', 'master')->first() ?: $club->users->first();

        $charityGrants = CharityGrant::where('club_id', $club->id)
            ->with(['proposer', 'seconder'])
            ->orderBy('created_at', 'desc')
            ->get();

        $viewData = [
            'club' => $club,
            'meeting' => $meeting,
            'members' => $members,
            'officerAssignments' => $meeting->officerAssignments,
            'secretaryUser' => $secretaryUser,
            'worshipfulMaster' => $worshipfulMaster,
            'charityGrants' => $charityGrants,
        ];

        if (request()->has('download')) {
            $filename = 'Summons-'.Str::slug($club->name).'-'.$meeting->meeting_date->format('Y-m-d').'.pdf';

            // Detect Node & Npm paths for Laravel Herd / macOS / Linux environments
            $nodeBinary = trim((string) shell_exec('which node 2>/dev/null'));
            $npmBinary = trim((string) shell_exec('which npm 2>/dev/null'));

            if (! $nodeBinary || ! file_exists($nodeBinary)) {
                $nodeCandidates = glob('/Users/*/Library/Application Support/Herd/config/nvm/versions/node/*/bin/node') ?: [];
                $nodeCandidates = array_merge($nodeCandidates, ['/opt/homebrew/bin/node', '/usr/local/bin/node', '/usr/bin/node']);
                foreach ($nodeCandidates as $candidate) {
                    if (file_exists($candidate)) {
                        $nodeBinary = $candidate;
                        break;
                    }
                }
            }

            if (! $npmBinary || ! file_exists($npmBinary)) {
                $npmCandidates = glob('/Users/*/Library/Application Support/Herd/config/nvm/versions/node/*/bin/npm') ?: [];
                $npmCandidates = array_merge($npmCandidates, ['/opt/homebrew/bin/npm', '/usr/local/bin/npm', '/usr/bin/npm']);
                foreach ($npmCandidates as $candidate) {
                    if (file_exists($candidate)) {
                        $npmBinary = $candidate;
                        break;
                    }
                }
            }

            try {
                return Pdf::view('summons.pdf', $viewData)
                    ->landscape()
                    ->withBrowsershot(function ($browsershot) use ($nodeBinary, $npmBinary) {
                        if ($nodeBinary && file_exists($nodeBinary)) {
                            $browsershot->setNodeBinary($nodeBinary);
                            $binDir = str_replace(' ', '\ ', dirname($nodeBinary));
                            $browsershot->setIncludePath($binDir.':/opt/homebrew/bin:/usr/local/bin:/usr/bin');
                        }
                        if ($npmBinary && file_exists($npmBinary)) {
                            $browsershot->setNpmBinary($npmBinary);
                        }
                    })
                    ->name($filename);
            } catch (\Throwable $e) {
                // Fallback gracefully to DomPDF if Node/Browsershot fails in specific PHP-FPM environments
                return \Barryvdh\DomPDF\Facade\Pdf::loadView('summons.pdf', $viewData)
                    ->setPaper('a4', 'landscape')
                    ->download($filename);
            }
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

        $visitorUsers = $club->users()->wherePivot('role', 'visitor')->get()->unique('id');
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
            (int) $request->year,
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
                    'title' => 'Meeting - '.$date->format('jS F Y'),
                    'starts_at' => $startTime,
                    'rehearsal_starts_at' => $rehearsalTime,
                    'venue' => 'Masonic Hall, Oxford',
                    'dress_code' => 'Dark Suit, Craft Regalia',
                    'dining_cost_member' => 35.00,
                    'dining_cost_guest' => 35.00,
                    'bank_sort_code' => $club->settings['bank_sort_code'] ?? '20-65-18',
                    'bank_account_number' => $club->settings['bank_account_number'] ?? '83920145',
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
     * Duplicate an existing meeting summons with its details, agenda items, and officer roles.
     */
    public function duplicate(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
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
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();
        $meeting->delete();

        return redirect()->route('admin.meetings.index', ['clubSlug' => $club->slug])
            ->with('success', 'Meeting removed successfully.');
    }

    /**
     * Manually record or update an RSVP on behalf of a member/visitor.
     */
    public function updateRsvp(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'attendance_status' => 'required|in:attending_dining,attending_meeting_only,apologies',
            'apology_reason' => 'nullable|string',
            'dietary_requirements' => 'nullable|string',
            'payment_status' => 'nullable|in:unpaid,paid,waived,refunded',
            'payment_reference' => 'nullable|string',
            'guests' => 'nullable|array',
            'guests.*.guest_name' => 'nullable|string',
            'guests.*.dietary_requirements' => 'nullable|string',
            'guests.*.attending_dining' => 'nullable|boolean',
        ]);

        $user = User::find($validated['user_id']);
        $surname = $user ? strtoupper(last(explode(' ', $user->name))) : 'MEMBER';
        $defaultRef = ($meeting->payment_reference_prefix ?: 'SUMMONS').'-'.$meeting->id.'-'.$surname;

        $updateData = [
            'token_hash' => Str::random(40),
            'token_expires_at' => now()->addDays(30),
            'attendance_status' => $validated['attendance_status'],
            'apology_reason' => $validated['apology_reason'] ?? null,
            'dietary_requirements' => $validated['dietary_requirements'] ?? null,
            'responded_at' => now(),
        ];

        if (isset($validated['payment_status'])) {
            $updateData['payment_status'] = $validated['payment_status'];
        }
        if (isset($validated['payment_reference'])) {
            $updateData['payment_reference'] = $validated['payment_reference'];
        } else {
            $updateData['payment_reference'] = $defaultRef;
        }

        $rsvp = MeetingRsvp::updateOrCreate(
            ['meeting_id' => $meeting->id, 'user_id' => $validated['user_id']],
            $updateData
        );

        if (array_key_exists('guests', $validated)) {
            $rsvp->guests()->delete();
            if (is_array($validated['guests'])) {
                foreach ($validated['guests'] as $g) {
                    if (! empty($g['guest_name'])) {
                        $rsvp->guests()->create([
                            'guest_name' => $g['guest_name'],
                            'dietary_requirements' => $g['dietary_requirements'] ?? null,
                            'attending_dining' => $g['attending_dining'] ?? true,
                            'dining_fee' => $meeting->dining_cost_guest,
                        ]);
                    }
                }
            }
        }

        return redirect()->back()->with('success', 'RSVP record updated successfully.');
    }

    /**
     * Quickly toggle or set RSVP payment status for a member/visitor.
     */
    public function updatePaymentStatus(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'payment_status' => 'required|in:unpaid,paid,waived,refunded',
            'payment_reference' => 'nullable|string',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $surname = strtoupper(last(explode(' ', $user->name)));
        $paymentRef = $validated['payment_reference'] ?? (($meeting->payment_reference_prefix ?: 'SUMMONS').'-'.$meeting->id.'-'.$surname);

        $rsvp = MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $user->id)->first();
        if ($rsvp) {
            $rsvp->update([
                'payment_status' => $validated['payment_status'],
                'payment_reference' => $paymentRef,
            ]);
        } else {
            MeetingRsvp::create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'token_hash' => Str::random(40),
                'token_expires_at' => now()->addDays(30),
                'attendance_status' => 'attending_dining',
                'payment_status' => $validated['payment_status'],
                'payment_reference' => $paymentRef,
                'responded_at' => now(),
            ]);
        }

        $statusLabel = ucfirst($validated['payment_status']);

        return redirect()->back()->with('success', "Payment status marked as {$statusLabel} for {$user->name}.");
    }

    /**
     * Display dedicated full page for Meeting Financial Return & Dining Calculator.
     */
    public function financialReturn(string $clubSlug, int $id): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $meeting = Meeting::where('club_id', $club->id)
            ->where('id', $id)
            ->with(['rsvps.guests', 'financialReturn'])
            ->firstOrFail();

        // Calculate confirmed dining count from RSVPs
        $confirmedDiningCount = $meeting->rsvps
            ->where('attendance_status', 'attending_dining')
            ->count();

        $guestDiningCount = 0;
        foreach ($meeting->rsvps as $r) {
            if ($r->attendance_status === 'attending_dining') {
                $guestDiningCount += $r->guests->where('attending_dining', true)->count();
            }
        }

        $totalConfirmedDiners = $confirmedDiningCount + $guestDiningCount;

        return Inertia::render('Admin/Meetings/FinancialReturn', [
            'club' => $club,
            'meeting' => $meeting,
            'confirmedDiningCount' => $totalConfirmedDiners,
            'financialReturn' => $meeting->financialReturn,
        ]);
    }

    /**
     * Store financial return for a meeting and post to accounting ledger.
     */
    public function storeFinancialReturn(Request $request, string $clubSlug, int $id, AccountingService $accountingService): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $isDraft = (bool) $request->boolean('is_draft');

        if ($isDraft) {
            $validated = $request->validate([
                'return_date' => 'nullable|date',
                'dining_fee_per_head' => 'nullable|numeric|min:0',
                'paid_diners_count' => 'nullable|integer|min:0',
                'waived_diners_count' => 'nullable|integer|min:0',
                'waived_reason' => 'nullable|string',
                'kitchen_cost_per_head' => 'nullable|numeric|min:0',
                'kitchen_vendor_name' => 'nullable|string|max:255',
                'raffle_amount' => 'nullable|numeric|min:0',
                'alms_amount' => 'nullable|numeric|min:0',
                'donations_amount' => 'nullable|numeric|min:0',
                'bequest_amount' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            $accountingService->saveMeetingFinancialReturnDraft($club, $meeting, $validated);

            return redirect()->route('admin.meetings.financial_return.show', ['clubSlug' => $club->slug, 'id' => $meeting->id])
                ->with('success', 'Meeting financial return draft saved successfully.');
        }

        $validated = $request->validate([
            'return_date' => 'required|date',
            'dining_fee_per_head' => 'required|numeric|min:0',
            'paid_diners_count' => 'required|integer|min:0',
            'waived_diners_count' => 'nullable|integer|min:0',
            'waived_reason' => 'nullable|string',
            'kitchen_cost_per_head' => 'required|numeric|min:0',
            'kitchen_vendor_name' => 'nullable|string|max:255',
            'raffle_amount' => 'nullable|numeric|min:0',
            'alms_amount' => 'nullable|numeric|min:0',
            'donations_amount' => 'nullable|numeric|min:0',
            'bequest_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $accountingService->postMeetingFinancialReturn($club, $meeting, $validated);

        return redirect()->route('admin.meetings.show', ['clubSlug' => $club->slug, 'id' => $meeting->id])
            ->with('success', 'Meeting financial return posted successfully to Accounts Payable and General Ledger!');
    }

    /**
     * Get or create Annual Officer Roster for a meeting agenda.
     */
    public function officerElectionData(string $clubSlug, int $id)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $masonicYear = Carbon::parse($meeting->meeting_date)->format('Y').'-'.(Carbon::parse($meeting->meeting_date)->year + 1);

        $roster = AnnualOfficerRoster::where('club_id', $club->id)
            ->where('masonic_year', $masonicYear)
            ->with('assignments.member')
            ->first();

        $members = Member::where('club_id', $club->id)->active()->get()->map(function ($m) {
            return [
                'id' => $m->id,
                'name' => $m->formatted_rank_name,
                'current_office' => $m->current_office?->value,
                'current_office_label' => $m->current_office?->label(),
            ];
        });

        $offices = collect(LodgeOffice::cases())
            ->filter(fn ($o) => $o !== LodgeOffice::Member && $o !== LodgeOffice::IPM)
            ->map(fn ($o) => [
                'value' => $o->value,
                'label' => $o->label(),
                'category' => $o->category(),
                'is_progressive' => $o->isProgressive(),
                'is_administrative' => $o->isAdministrative(),
            ])
            ->values();

        return response()->json([
            'masonic_year' => $masonicYear,
            'roster' => $roster,
            'members' => $members,
            'offices' => $offices,
        ]);
    }

    /**
     * Save annual officer election draft from meeting agenda discussion.
     */
    public function storeOfficerElection(Request $request, string $clubSlug, int $id, AnnualOfficerRosterService $rosterService)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'masonic_year' => 'required|string',
            'assignments' => 'present|array',
            'assignments.*.member_id' => 'required|integer',
            'assignments.*.office' => 'required|string',
            'notes' => 'nullable|string',
            'status' => 'nullable|string',
        ]);

        try {
            $roster = $rosterService->saveRoster(
                $club,
                $validated['masonic_year'],
                $validated['assignments'],
                $meeting->id,
                $validated['status'] ?? 'proposed',
                $validated['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Annual officer roster draft saved successfully.',
                'roster' => $roster,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Confirm annual officer election and apply to active member profiles.
     */
    public function confirmOfficerElection(Request $request, string $clubSlug, int $id, AnnualOfficerRosterService $rosterService)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $masonicYear = $request->input('masonic_year') ?: (Carbon::parse($meeting->meeting_date)->format('Y').'-'.(Carbon::parse($meeting->meeting_date)->year + 1));

        $roster = AnnualOfficerRoster::where('club_id', $club->id)
            ->where('masonic_year', $masonicYear)
            ->firstOrFail();

        $confirmedRoster = $rosterService->confirmRoster($roster);

        return response()->json([
            'success' => true,
            'message' => "Annual officer roster for {$masonicYear} confirmed and applied to active members!",
            'roster' => $confirmedRoster,
        ]);
    }
}
