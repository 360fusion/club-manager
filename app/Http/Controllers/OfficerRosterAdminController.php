<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\AnnualOfficerRoster;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\AnnualOfficerRosterService;
use App\Models\Club;
use App\Support\MasonicRanks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OfficerRosterAdminController extends Controller
{
    /**
     * Display multi-year officer roster management hub.
     */
    public function index(Request $request, string $clubSlug, AnnualOfficerRosterService $rosterService): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        // Automatically trigger installation rollover if the installation meeting has passed
        $rosterService->checkAndAutoInstallPassedInstallationMeetings($club);

        $activeRoster = AnnualOfficerRoster::where('club_id', $club->id)
            ->where('status', 'installed')
            ->orderBy('masonic_year', 'desc')
            ->first();

        $rosters = AnnualOfficerRoster::where('club_id', $club->id)
            ->with('assignments.member')
            ->orderBy('masonic_year', 'desc')
            ->get();

        $members = Member::where('club_id', $club->id)->active()->get()->map(function ($m) {
            return [
                'id' => $m->id,
                'name' => $m->formatted_rank_name,
                'full_name' => $m->full_name,
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

        $installationMonth = $club->settings['installation_month'] ?? 'October';

        return Inertia::render('Admin/Officers/RosterIndex', [
            'club' => $club,
            'rosters' => $rosters,
            'members' => $members,
            'offices' => $offices,
            'installationMonth' => $installationMonth,
            'grandRanks' => MasonicRanks::optionsFor($club, MasonicRanks::GRAND),
            'provincialRanks' => MasonicRanks::optionsFor($club, MasonicRanks::PROVINCIAL),
        ]);
    }

    /**
     * Save or update roster for a specific Masonic Year.
     */
    public function store(Request $request, string $clubSlug, AnnualOfficerRosterService $rosterService): RedirectResponse|JsonResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'masonic_year' => 'required|string|max:20',
            'assignments' => 'present|array|max:100',
            'assignments.*.member_id' => 'required|integer',
            'assignments.*.office' => 'required|string|max:50',
            'notes' => 'nullable|string|max:10000',
            'status' => 'nullable|string|in:draft,proposed,confirmed,installed',
        ]);

        try {
            $roster = $rosterService->saveRoster(
                $club,
                $validated['masonic_year'],
                $validated['assignments'],
                null,
                $validated['status'] ?? 'draft',
                $validated['notes'] ?? null
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Officer roster for {$roster->masonic_year} saved.",
                    'roster' => $roster,
                ]);
            }

            return redirect()->back()->with('success', "Officer roster for {$roster->masonic_year} saved successfully.");
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['assignments' => $e->getMessage()]);
        }
    }

    /**
     * Update status (e.g., mark as Proposed or Approved in Committee).
     */
    public function updateStatus(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $roster = AnnualOfficerRoster::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'status' => 'required|string|in:draft,proposed,confirmed,installed',
        ]);

        $roster->update([
            'status' => $validated['status'],
        ]);

        return redirect()->back()->with('success', "Roster status updated to {$validated['status']}.");
    }

    /**
     * Perform Installation Rollover (Installs approved roster as active).
     */
    public function install(Request $request, string $clubSlug, int $id, AnnualOfficerRosterService $rosterService): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $roster = AnnualOfficerRoster::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        // Mark previously installed roster as archived/past
        AnnualOfficerRoster::where('club_id', $club->id)
            ->where('status', 'installed')
            ->update(['status' => 'confirmed']);

        $installedRoster = $rosterService->confirmRoster($roster);
        $installedRoster->update(['status' => 'installed']);

        return redirect()->back()->with('success', "✨ Officers for {$installedRoster->masonic_year} successfully Installed! Active member badges updated.");
    }

    /**
     * Quickly create a historic/new member for roster assignment.
     */
    public function storeQuickMember(Request $request, string $clubSlug): JsonResponse|RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'membership_status' => 'nullable|string|max:50',
            'masonic_rank' => ['nullable', Rule::in(array_keys(Member::MASONIC_RANKS))],
            'grand_rank' => ['nullable', 'string', 'max:100', Rule::in(MasonicRanks::allowedValues($club, MasonicRanks::GRAND))],
            'provincial_rank' => ['nullable', 'string', 'max:100', Rule::in(MasonicRanks::allowedValues($club, MasonicRanks::PROVINCIAL))],
        ]);

        $masonicRank = $validated['masonic_rank'] ?: 'Bro';

        $statusStr = $validated['membership_status'] ?? 'historical';
        $membershipStatus = MembershipStatus::tryFrom($statusStr) ?? MembershipStatus::Historical;

        $member = Member::create([
            'club_id' => $club->id,
            'first_name' => trim($validated['first_name']),
            'last_name' => trim($validated['last_name']),
            'masonic_rank' => $masonicRank,
            'grand_rank' => $validated['grand_rank'] ?: null,
            'provincial_rank' => $validated['provincial_rank'] ?: null,
            'membership_status' => $membershipStatus,
            'current_office' => LodgeOffice::Member,
        ]);

        $memberData = [
            'id' => $member->id,
            'name' => $member->formatted_rank_name,
            'full_name' => $member->full_name,
            'current_office' => $member->current_office?->value,
            'current_office_label' => $member->current_office?->label(),
        ];

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Member {$member->formatted_rank_name} created.",
                'member' => $memberData,
            ]);
        }

        return redirect()->back()->with('success', "Member {$member->formatted_rank_name} created.");
    }
}
