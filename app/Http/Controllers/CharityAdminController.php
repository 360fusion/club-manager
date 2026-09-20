<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\CollectionType;
use App\Domains\ClubAccounting\Enums\GrantApprovalStatus;
use App\Domains\ClubAccounting\Models\CharityCollection;
use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Models\FestivalTarget;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberFestivalGiving;
use App\Domains\ClubAccounting\Services\ReliefChestReconciliationService;
use App\Models\AgendaItem;
use App\Models\Club;
use App\Models\Meeting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CharityAdminController extends Controller
{
    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $target = FestivalTarget::where('club_id', $club->id)->first();
        if (! $target) {
            $target = FestivalTarget::create([
                'club_id' => $club->id,
                'festival_name' => 'Durham 2029 Festival',
                'relief_chest_ref' => 'E1418',
                'target_amount' => 25000.00,
                'bronze_tier' => 5000.00,
                'silver_tier' => 10000.00,
                'gold_tier' => 18000.00,
                'platinum_tier' => 25000.00,
            ]);
        }

        $collections = CharityCollection::where('club_id', $club->id)
            ->with(['countedBy', 'witnessedBy', 'donor'])
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get()
            ->map(fn ($col) => [
                'id' => $col->id,
                'created_at' => $col->created_at ? $col->created_at->format('d M Y') : date('d M Y'),
                'collection_type' => $col->collection_type?->label() ?? 'Alms Plate',
                'cash_amount' => (float)$col->cash_amount,
                'cheque_amount' => (float)$col->cheque_amount,
                'total_amount' => (float)$col->total_amount,
                'formatted_total' => '£' . number_format((float)$col->total_amount, 2),
                'donor_name' => $col->donor_display_name,
                'counted_by' => $col->countedBy?->full_name ?? 'Charity Steward',
                'witnessed_by' => $col->witnessedBy?->full_name ?? 'Assistant DC',
                'gift_aid_status' => $col->gift_aid_status ?: 'pending',
                'notes' => $col->notes,
            ]);

        $totalCollectionsCash = (float)CharityCollection::where('club_id', $club->id)->sum('cash_amount');
        $totalCollectionsCheque = (float)CharityCollection::where('club_id', $club->id)->sum('cheque_amount');
        $totalCollectionsAmount = $totalCollectionsCash + $totalCollectionsCheque;

        $grants = CharityGrant::where('club_id', $club->id)
            ->with(['proposer', 'seconder', 'committeeMeeting', 'meeting'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($g) => [
                'id' => $g->id,
                'recipient_name' => $g->recipient_name,
                'purpose' => $g->purpose,
                'amount' => (float)$g->amount,
                'formatted_amount' => '£' . number_format((float)$g->amount, 2),
                'relief_chest_number' => $g->relief_chest_number,
                'approval_status' => $g->approval_status->value,
                'status_label' => $g->approval_status->label(),
                'proposer_name' => $g->proposer?->full_name,
                'seconder_name' => $g->seconder?->full_name,
                'bacs_reference' => $g->bacs_reference,
                'meeting_id' => $g->meeting_id,
                'meeting_title' => $g->meeting ? ($g->meeting->title ? "Meeting #{$g->meeting->meeting_number} — {$g->meeting->title}" : "Meeting #{$g->meeting->meeting_number}") : null,
            ]);

        $upcomingMeetings = Meeting::where('club_id', $club->id)
            ->orderBy('meeting_date', 'desc')
            ->take(15)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'label' => ($m->title ? "Meeting #{$m->meeting_number} — {$m->title}" : "Meeting #{$m->meeting_number}") . ($m->meeting_date ? " (" . $m->meeting_date->format('d M Y') . ")" : ''),
            ]);

        $totalGrantsDisbursed = (float)CharityGrant::where('club_id', $club->id)
            ->where('approval_status', GrantApprovalStatus::Disbursed->value)
            ->sum('amount');

        $activeMembers = Member::where('club_id', $club->id)->active()->orderBy('last_name')->get(['id', 'first_name', 'last_name', 'masonic_rank']);
        $memberGivingRecords = MemberFestivalGiving::whereIn('member_id', $activeMembers->pluck('id'))->get()->keyBy('member_id');
        $totalMemberDonations = (float)$memberGivingRecords->sum('total_donated_to_date');

        $totalRaisedForFestival = $totalCollectionsAmount + $totalMemberDonations;
        $targetPercentage = $target->getPercentage($totalRaisedForFestival);
        $currentHonorTier = $target->getCurrentTier($totalRaisedForFestival);

        $giftAidService = app(ReliefChestReconciliationService::class);
        $giftAidSummary = $giftAidService->getGiftAidSummary($club);
        $reconciledDonations = $giftAidService->getReconciledDonations($club);

        return Inertia::render('Admin/Charity/Index', [
            'club' => $club,
            'target' => [
                'festival_name' => $target->festival_name,
                'relief_chest_ref' => $target->relief_chest_ref,
                'target_amount' => (float)$target->target_amount,
                'bronze_tier' => (float)$target->bronze_tier,
                'silver_tier' => (float)$target->silver_tier,
                'gold_tier' => (float)$target->gold_tier,
                'platinum_tier' => (float)$target->platinum_tier,
            ],
            'totalRaisedForFestival' => $totalRaisedForFestival,
            'formattedTotalRaised' => '£' . number_format($totalRaisedForFestival, 2),
            'targetPercentage' => $targetPercentage,
            'currentHonorTier' => $currentHonorTier,
            'giftAidSummary' => $giftAidSummary,
            'reconciledDonations' => $reconciledDonations,
            'collections' => $collections,
            'totalCollectionsAmount' => $totalCollectionsAmount,
            'formattedTotalCollections' => '£' . number_format($totalCollectionsAmount, 2),
            'grants' => $grants,
            'upcomingMeetings' => $upcomingMeetings,
            'totalGrantsDisbursed' => $totalGrantsDisbursed,
            'formattedTotalGrants' => '£' . number_format($totalGrantsDisbursed, 2),
            'activeMembers' => $activeMembers->map(fn ($m) => ['id' => $m->id, 'name' => $m->full_name, 'rank' => $m->masonic_rank]),
            'jewelHoldersCount' => $memberGivingRecords->where('qualifies_for_jewel', true)->count(),
        ]);
    }

    public function giftAidTransactionsPage(Request $request, string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $service = app(ReliefChestReconciliationService::class);

        $giftAidSummary = $service->getGiftAidSummary($club);
        $reconciledDonations = $service->getReconciledDonations($club, $request->all());

        return Inertia::render('Admin/Charity/GiftAidTransactions', [
            'club' => $club,
            'giftAidSummary' => $giftAidSummary,
            'reconciledDonations' => $reconciledDonations,
            'filters' => $request->only(['date_from', 'date_to', 'person_id', 'status', 'search']),
        ]);
    }

    public function festivalPage(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $target = FestivalTarget::where('club_id', $club->id)->first();
        if (! $target) {
            $target = FestivalTarget::create([
                'club_id' => $club->id,
                'festival_name' => 'Durham 2029 Festival',
                'relief_chest_ref' => 'E1418',
                'target_amount' => 25000.00,
                'bronze_tier' => 5000.00,
                'silver_tier' => 10000.00,
                'gold_tier' => 18000.00,
                'platinum_tier' => 25000.00,
            ]);
        }

        $totalCollectionsCash = (float)CharityCollection::where('club_id', $club->id)->sum('cash_amount');
        $totalCollectionsCheque = (float)CharityCollection::where('club_id', $club->id)->sum('cheque_amount');
        $totalCollectionsAmount = $totalCollectionsCash + $totalCollectionsCheque;

        $activeMembers = Member::where('club_id', $club->id)->active()->orderBy('last_name')->get();
        $memberGivingRecords = MemberFestivalGiving::whereIn('member_id', $activeMembers->pluck('id'))->get()->keyBy('member_id');
        $totalMemberDonations = (float)$memberGivingRecords->sum('total_donated_to_date');

        $totalRaisedForFestival = $totalCollectionsAmount + $totalMemberDonations;
        $targetPercentage = $target->getPercentage($totalRaisedForFestival);
        $currentHonorTier = $target->getCurrentTier($totalRaisedForFestival);

        return Inertia::render('Admin/Charity/Festival', [
            'club' => $club,
            'target' => [
                'festival_name' => $target->festival_name,
                'relief_chest_ref' => $target->relief_chest_ref,
                'target_amount' => (float)$target->target_amount,
                'bronze_tier' => (float)$target->bronze_tier,
                'silver_tier' => (float)$target->silver_tier,
                'gold_tier' => (float)$target->gold_tier,
                'platinum_tier' => (float)$target->platinum_tier,
            ],
            'totalRaisedForFestival' => $totalRaisedForFestival,
            'formattedTotalRaised' => '£' . number_format($totalRaisedForFestival, 2),
            'targetPercentage' => $targetPercentage,
            'currentHonorTier' => $currentHonorTier,
            'activeMembers' => $activeMembers->map(function ($m) use ($memberGivingRecords) {
                $giving = $memberGivingRecords[$m->id] ?? null;
                return [
                    'id' => $m->id,
                    'name' => $m->full_name,
                    'rank' => $m->formatted_rank_name,
                    'regular_giving' => $giving ? (float)$giving->regular_giving_amount : 0.0,
                    'total_donated' => $giving ? (float)$giving->total_donated_to_date : 0.0,
                    'formatted_donated' => '£' . number_format($giving ? (float)$giving->total_donated_to_date : 0.0, 2),
                    'qualifies_for_jewel' => $giving ? (bool)$giving->qualifies_for_jewel : false,
                    'qualifies_for_bar' => $giving ? (bool)$giving->qualifies_for_bar : false,
                ];
            }),
        ]);
    }

    public function storeCollection(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'collection_type' => 'required|string',
            'cash_amount' => 'required|numeric|min:0',
            'cheque_amount' => 'required|numeric|min:0',
            'counted_by_member_id' => 'nullable|exists:club_acc_members,id',
            'witnessed_by_member_id' => 'nullable|exists:club_acc_members,id',
            'donor_member_id' => 'nullable|exists:club_acc_members,id',
            'donor_name' => 'nullable|string|max:150',
            'notes' => 'nullable|string',
        ]);

        $collection = CharityCollection::create([
            'club_id' => $club->id,
            'collection_type' => $validated['collection_type'],
            'cash_amount' => $validated['cash_amount'],
            'cheque_amount' => $validated['cheque_amount'],
            'counted_by_member_id' => $validated['counted_by_member_id'],
            'witnessed_by_member_id' => $validated['witnessed_by_member_id'],
            'donor_member_id' => $validated['donor_member_id'] ?? null,
            'donor_name' => $validated['donor_name'] ?? null,
            'is_gift_aid_eligible' => true,
            'gift_aid_status' => 'pending',
            'gift_aid_amount' => round(($validated['cash_amount'] + $validated['cheque_amount']) * 0.25, 2),
            'notes' => $validated['notes'],
        ]);

        return redirect()->back()->with('success', "Dual-custody meeting collection of £" . number_format($collection->total_amount, 2) . " recorded.");
    }

    public function storeGrant(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'recipient_name' => 'required|string|max:150',
            'purpose' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'relief_chest_number' => 'nullable|string|max:50',
            'proposer_member_id' => 'nullable|exists:club_acc_members,id',
            'seconder_member_id' => 'nullable|exists:club_acc_members,id',
            'meeting_id' => 'nullable|exists:meetings,id',
        ]);

        $grant = CharityGrant::create([
            'club_id' => $club->id,
            'recipient_name' => $validated['recipient_name'],
            'purpose' => $validated['purpose'],
            'amount' => $validated['amount'],
            'relief_chest_number' => $validated['relief_chest_number'] ?? null,
            'approval_status' => GrantApprovalStatus::Proposed->value,
            'proposer_member_id' => $validated['proposer_member_id'] ?? null,
            'seconder_member_id' => $validated['seconder_member_id'] ?? null,
            'meeting_id' => $validated['meeting_id'] ?? null,
        ]);

        if (!empty($validated['meeting_id'])) {
            $meeting = Meeting::where('club_id', $club->id)->find($validated['meeting_id']);
            if ($meeting) {
                $nextNum = ($meeting->agendaItems()->max('item_number') ?? 0) + 1;
                $proposer = !empty($validated['proposer_member_id']) ? Member::find($validated['proposer_member_id'])?->full_name : null;
                $seconder = !empty($validated['seconder_member_id']) ? Member::find($validated['seconder_member_id'])?->full_name : null;

                $desc = "To consider and, if approved, pass a resolution proposing a Charity Grant of £" . number_format((float)$validated['amount'], 2) . " from the Relief Chest to " . $validated['recipient_name'] . " (" . $validated['purpose'] . ").";
                if ($proposer) {
                    $desc .= " Proposed by {$proposer}.";
                }
                if ($seconder) {
                    $desc .= " Seconded by {$seconder}.";
                }

                AgendaItem::create([
                    'meeting_id' => $meeting->id,
                    'item_number' => $nextNum,
                    'title' => "Charity Grant Proposition: £" . number_format((float)$validated['amount'], 2) . " to " . $validated['recipient_name'],
                    'description' => $desc,
                    'is_ballot' => true,
                ]);
            }
        }

        return redirect()->back()->with('success', "Charity grant proposal for {$validated['recipient_name']} recorded" . (!empty($validated['meeting_id']) ? " and added to Meeting agenda." : "."));
    }

    public function updateGrantStatus(Request $request, string $clubSlug, int $grantId): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $grant = CharityGrant::where('club_id', $club->id)->findOrFail($grantId);

        $validated = $request->validate([
            'approval_status' => 'required|string',
        ]);

        $grant->update([
            'approval_status' => $validated['approval_status'],
        ]);

        return redirect()->back()->with('success', "Grant status updated to {$grant->approval_status->label()}.");
    }

    public function updateFestivalTarget(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'festival_name' => 'required|string|max:150',
            'relief_chest_ref' => 'required|string|max:50',
            'target_amount' => 'required|numeric|min:0',
            'bronze_tier' => 'required|numeric|min:0',
            'silver_tier' => 'required|numeric|min:0',
            'gold_tier' => 'required|numeric|min:0',
            'platinum_tier' => 'required|numeric|min:0',
        ]);

        FestivalTarget::updateOrCreate(
            ['club_id' => $club->id],
            $validated
        );

        return redirect()->back()->with('success', "Provincial Festival Target milestones saved.");
    }

    public function updateMemberGiving(Request $request, string $clubSlug, int $memberId): RedirectResponse
    {
        $validated = $request->validate([
            'regular_giving_amount' => 'required|numeric|min:0',
            'total_donated_to_date' => 'required|numeric|min:0',
            'qualifies_for_jewel' => 'boolean',
            'qualifies_for_bar' => 'boolean',
        ]);

        $donated = (float)$validated['total_donated_to_date'];
        $autoJewel = ($validated['qualifies_for_jewel'] ?? false) || $donated >= 250.00;
        $autoBar = ($validated['qualifies_for_bar'] ?? false) || $donated >= 500.00;

        MemberFestivalGiving::updateOrCreate(
            ['member_id' => $memberId],
            [
                'regular_giving_amount' => (float)$validated['regular_giving_amount'],
                'total_donated_to_date' => $donated,
                'qualifies_for_jewel' => $autoJewel,
                'qualifies_for_bar' => $autoBar,
            ]
        );

        return redirect()->back()->with('success', "Member festival giving updated.");
    }
}
