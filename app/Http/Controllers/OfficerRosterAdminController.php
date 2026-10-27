<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\AnnualOfficerRoster;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\AnnualOfficerRosterService;
use App\Http\Requests\OfficerRosterAdmin\StoreOfficerRosterRequest;
use App\Http\Requests\OfficerRosterAdmin\StoreQuickMemberRequest;
use App\Http\Requests\OfficerRosterAdmin\UpdateStatusRequest;
use App\Support\MasonicRanks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfficerRosterAdminController extends Controller
{
    /**
     * Display multi-year officer roster management hub.
     */
    public function index(Request $request, string $clubSlug, AnnualOfficerRosterService $rosterService): Response
    {
        $club = $this->findClub($clubSlug);

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
    public function store(StoreOfficerRosterRequest $request, string $clubSlug, AnnualOfficerRosterService $rosterService): RedirectResponse|JsonResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

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
    public function updateStatus(UpdateStatusRequest $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $roster = AnnualOfficerRoster::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validated();

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
        $club = $this->findClub($clubSlug);
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
    public function storeQuickMember(StoreQuickMemberRequest $request, string $clubSlug): JsonResponse|RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

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
