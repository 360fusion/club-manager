<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\AnnualOfficerRoster;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\AnnualOfficerRosterService;
use App\Http\Requests\MeetingBusiness\StoreOfficerElectionRequest;
use App\Models\Meeting;
use App\Services\AccountingService;
use App\Services\MeetingFinancialReturns;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A meeting's financial return and the election of officers. Split out of MeetingAdminController; the URLs, route names and permissions are unchanged.
 */
class MeetingBusinessController extends Controller
{
    /**
     * Display dedicated full page for Meeting Financial Return & Dining Calculator.
     */
    public function financialReturn(string $clubSlug, int $id): Response
    {
        $club = $this->findClub($clubSlug);
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
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $isDraft = (bool) $request->boolean('is_draft');

        if ($isDraft) {
            $validated = $request->validate([
                'return_date' => 'nullable|date',
                'dining_fee_per_head' => 'nullable|numeric|min:0|max:99999999.99',
                'paid_diners_count' => 'nullable|integer|min:0|max:1000000',
                'waived_diners_count' => 'nullable|integer|min:0|max:1000000',
                'waived_reason' => 'nullable|string|max:10000',
                'kitchen_cost_per_head' => 'nullable|numeric|min:0|max:99999999.99',
                'kitchen_vendor_name' => 'nullable|string|max:255',
                'raffle_amount' => 'nullable|numeric|min:0|max:99999999.99',
                'alms_amount' => 'nullable|numeric|min:0|max:99999999.99',
                'donations_amount' => 'nullable|numeric|min:0|max:99999999.99',
                'bequest_amount' => 'nullable|numeric|min:0|max:99999999.99',
                'notes' => 'nullable|string|max:10000',
            ]);

            app(MeetingFinancialReturns::class)->saveMeetingFinancialReturnDraft($club, $meeting, $validated);

            return redirect()->route('admin.meetings.financial_return.show', ['clubSlug' => $club->slug, 'id' => $meeting->id])
                ->with('success', 'Meeting financial return draft saved successfully.');
        }

        $validated = $request->validate([
            'return_date' => 'required|date',
            'dining_fee_per_head' => 'required|numeric|min:0|max:99999999.99',
            'paid_diners_count' => 'required|integer|min:0|max:1000000',
            'waived_diners_count' => 'nullable|integer|min:0|max:1000000',
            'waived_reason' => 'nullable|string|max:10000',
            'kitchen_cost_per_head' => 'required|numeric|min:0|max:99999999.99',
            'kitchen_vendor_name' => 'nullable|string|max:255',
            'raffle_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'alms_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'donations_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'bequest_amount' => 'nullable|numeric|min:0|max:99999999.99',
            'notes' => 'nullable|string|max:10000',
        ]);

        app(MeetingFinancialReturns::class)->postMeetingFinancialReturn($club, $meeting, $validated);

        return redirect()->route('admin.meetings.show', ['clubSlug' => $club->slug, 'id' => $meeting->id])
            ->with('success', 'Meeting financial return posted successfully to Accounts Payable and General Ledger!');
    }

    /**
     * Get or create Annual Officer Roster for a meeting agenda.
     */
    public function officerElectionData(string $clubSlug, int $id)
    {
        $club = $this->findClub($clubSlug);
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
    public function storeOfficerElection(StoreOfficerElectionRequest $request, string $clubSlug, int $id, AnnualOfficerRosterService $rosterService)
    {
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validated();

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
        $club = $this->findClub($clubSlug);
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
