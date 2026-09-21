<?php

namespace App\Domains\ClubAccounting\Livewire\Charity;

use App\Domains\ClubAccounting\Enums\CollectionType;
use App\Domains\ClubAccounting\Enums\GrantApprovalStatus;
use App\Domains\ClubAccounting\Models\CharityCollection;
use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Domains\ClubAccounting\Models\FestivalTarget;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberFestivalGiving;
use App\Domains\ClubAccounting\Services\ReliefChestExportService;
use App\Domains\ClubAccounting\Services\ReliefChestReconciliationService;
use App\Models\Club;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CharityDashboard extends Component
{
    #[Locked]
    public string $clubSlug;

    // Modals Visibility
    public bool $showCollectionModal = false;

    public bool $showGrantModal = false;

    public bool $showTargetModal = false;

    public bool $showGivingModal = false;

    // Collection Form
    public string $collection_type = 'alms_plate';

    public string $cash_amount = '0.00';

    public string $cheque_amount = '0.00';

    public ?int $counted_by_member_id = null;

    public ?int $witnessed_by_member_id = null;

    public string $collection_notes = '';

    // Grant Form
    public ?int $grantId = null;

    public string $recipient_name = '';

    public string $purpose = '';

    public string $grant_amount = '0.00';

    public string $relief_chest_number = '';

    public string $approval_status = 'proposed';

    public string $bacs_reference = '';

    public ?int $proposer_member_id = null;

    public ?int $seconder_member_id = null;

    public ?int $committee_meeting_id = null;

    // Festival Target Form
    public string $festival_name = 'Durham 2029 Festival';

    public string $relief_chest_ref = 'E1418';

    public string $target_amount = '25000.00';

    public string $bronze_tier = '5000.00';

    public string $silver_tier = '10000.00';

    public string $gold_tier = '18000.00';

    public string $platinum_tier = '25000.00';

    // Member Giving Form
    public ?int $givingMemberId = null;

    public string $regular_giving_amount = '0.00';

    public string $total_donated_to_date = '0.00';

    public bool $qualifies_for_jewel = false;

    public bool $qualifies_for_bar = false;

    public function mount(string $clubSlug): void
    {
        $this->clubSlug = $clubSlug;
        $this->loadFestivalTargetData();
    }

    private function getClub(): Club
    {
        return Club::where('slug', $this->clubSlug)->firstOrFail();
    }

    private function loadFestivalTargetData(): void
    {
        $club = $this->getClub();
        $target = FestivalTarget::where('club_id', $club->id)->first();
        if ($target) {
            $this->festival_name = $target->festival_name;
            $this->relief_chest_ref = $target->relief_chest_ref;
            $this->target_amount = (string) $target->target_amount;
            $this->bronze_tier = (string) $target->bronze_tier;
            $this->silver_tier = (string) $target->silver_tier;
            $this->gold_tier = (string) $target->gold_tier;
            $this->platinum_tier = (string) $target->platinum_tier;
        }
    }

    public function openCollectionModal(): void
    {
        $this->resetCollectionForm();
        $this->showCollectionModal = true;
    }

    public function recordCollection(): void
    {
        $club = $this->getClub();

        $this->validate([
            'collection_type' => 'required|string',
            'cash_amount' => 'required|numeric|min:0',
            'cheque_amount' => 'required|numeric|min:0',
            'counted_by_member_id' => 'required|exists:club_acc_members,id',
            'witnessed_by_member_id' => 'required|exists:club_acc_members,id|different:counted_by_member_id',
            'collection_notes' => 'nullable|string',
        ], [
            'witnessed_by_member_id.different' => 'Witness must be a different brother than the Counter for dual-custody verification.',
        ]);

        $col = CharityCollection::create([
            'club_id' => $club->id,
            'collection_type' => CollectionType::from($this->collection_type),
            'cash_amount' => (float) $this->cash_amount,
            'cheque_amount' => (float) $this->cheque_amount,
            'counted_by_member_id' => $this->counted_by_member_id,
            'witnessed_by_member_id' => $this->witnessed_by_member_id,
            'notes' => $this->collection_notes,
        ]);

        session()->flash('success', 'Dual-custody meeting collection of £'.number_format($col->total_amount, 2).' recorded.');
        $this->showCollectionModal = false;
        $this->resetCollectionForm();
    }

    public function openGrantModal(?int $id = null): void
    {
        $club = $this->getClub();
        if ($id) {
            $grant = CharityGrant::where('club_id', $club->id)->findOrFail($id);
            $this->grantId = $grant->id;
            $this->recipient_name = $grant->recipient_name;
            $this->purpose = $grant->purpose;
            $this->grant_amount = (string) $grant->amount;
            $this->relief_chest_number = $grant->relief_chest_number ?? '';
            $this->approval_status = $grant->approval_status->value;
            $this->bacs_reference = $grant->bacs_reference ?? '';
            $this->proposer_member_id = $grant->proposer_member_id;
            $this->seconder_member_id = $grant->seconder_member_id;
            $this->committee_meeting_id = $grant->committee_meeting_id;
        } else {
            $this->resetGrantForm();
        }
        $this->showGrantModal = true;
    }

    public function saveGrant(): void
    {
        $club = $this->getClub();

        $this->validate([
            'recipient_name' => 'required|string|max:150',
            'purpose' => 'required|string',
            'grant_amount' => 'required|numeric|min:0.01',
            'approval_status' => 'required|string',
            'proposer_member_id' => 'nullable|exists:club_acc_members,id',
            'seconder_member_id' => 'nullable|exists:club_acc_members,id|different:proposer_member_id',
            'committee_meeting_id' => 'nullable|exists:club_acc_committee_meetings,id',
        ], [
            'seconder_member_id.different' => 'The Seconder must be a different Brother than the Proposer.',
        ]);

        $data = [
            'club_id' => $club->id,
            'recipient_name' => $this->recipient_name,
            'purpose' => $this->purpose,
            'amount' => (float) $this->grant_amount,
            'relief_chest_number' => $this->relief_chest_number ?: null,
            'approval_status' => GrantApprovalStatus::from($this->approval_status),
            'bacs_reference' => $this->bacs_reference ?: 'BACS-G'.sprintf('%04d', rand(1, 9999)),
            'proposer_member_id' => $this->proposer_member_id ?: null,
            'seconder_member_id' => $this->seconder_member_id ?: null,
            'committee_meeting_id' => $this->committee_meeting_id ?: null,
        ];

        if ($this->grantId) {
            $grant = CharityGrant::where('club_id', $club->id)->findOrFail($this->grantId);
            $grant->update($data);
            session()->flash('success', "Charity grant for '{$grant->recipient_name}' updated.");
        } else {
            $grant = CharityGrant::create($data);
            session()->flash('success', "New charity grant for '{$grant->recipient_name}' logged.");
        }

        $this->showGrantModal = false;
        $this->resetGrantForm();
    }

    public function updateGrantStatus(int $id, string $statusStr): void
    {
        $club = $this->getClub();
        $grant = CharityGrant::where('club_id', $club->id)->findOrFail($id);
        $status = GrantApprovalStatus::from($statusStr);

        $grant->update(['approval_status' => $status]);
        session()->flash('success', "Grant for '{$grant->recipient_name}' status updated to {$status->label()}.");
    }

    public function saveFestivalTarget(): void
    {
        $club = $this->getClub();

        $this->validate([
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
            [
                'festival_name' => $this->festival_name,
                'relief_chest_ref' => $this->relief_chest_ref,
                'target_amount' => (float) $this->target_amount,
                'bronze_tier' => (float) $this->bronze_tier,
                'silver_tier' => (float) $this->silver_tier,
                'gold_tier' => (float) $this->gold_tier,
                'platinum_tier' => (float) $this->platinum_tier,
            ]
        );

        session()->flash('success', 'Provincial Festival Target milestones saved.');
        $this->showTargetModal = false;
    }

    public function openGivingModal(int $memberId): void
    {
        $this->givingMemberId = $memberId;
        $giving = MemberFestivalGiving::where('member_id', $memberId)->first();
        if ($giving) {
            $this->regular_giving_amount = (string) $giving->regular_giving_amount;
            $this->total_donated_to_date = (string) $giving->total_donated_to_date;
            $this->qualifies_for_jewel = (bool) $giving->qualifies_for_jewel;
            $this->qualifies_for_bar = (bool) $giving->qualifies_for_bar;
        } else {
            $this->regular_giving_amount = '0.00';
            $this->total_donated_to_date = '0.00';
            $this->qualifies_for_jewel = false;
            $this->qualifies_for_bar = false;
        }
        $this->showGivingModal = true;
    }

    public function saveMemberGiving(): void
    {
        $this->validate([
            'regular_giving_amount' => 'required|numeric|min:0',
            'total_donated_to_date' => 'required|numeric|min:0',
            'qualifies_for_jewel' => 'boolean',
            'qualifies_for_bar' => 'boolean',
        ]);

        $donated = (float) $this->total_donated_to_date;
        $autoJewel = $this->qualifies_for_jewel || $donated >= 250.00;
        $autoBar = $this->qualifies_for_bar || $donated >= 500.00;

        MemberFestivalGiving::updateOrCreate(
            ['member_id' => $this->givingMemberId],
            [
                'regular_giving_amount' => (float) $this->regular_giving_amount,
                'total_donated_to_date' => $donated,
                'qualifies_for_jewel' => $autoJewel,
                'qualifies_for_bar' => $autoBar,
            ]
        );

        session()->flash('success', 'Member Festival Giving record saved.');
        $this->showGivingModal = false;
    }

    public function exportReliefChestCsv(ReliefChestExportService $exportService)
    {
        $club = $this->getClub();
        $csvContent = $exportService->generateReliefChestCsv($club);
        $filename = 'MCF-ReliefChest-Deposit-'.$club->slug.'-'.date('Ymd').'.csv';

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportBacsSchedule(ReliefChestExportService $exportService)
    {
        $club = $this->getClub();
        $csvContent = $exportService->generateBacsSchedule($club);
        $filename = 'Charity-Grants-BACS-Schedule-'.$club->slug.'-'.date('Ymd').'.csv';

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function resetCollectionForm(): void
    {
        $this->reset(['cash_amount', 'cheque_amount', 'counted_by_member_id', 'witnessed_by_member_id', 'collection_notes']);
        $this->collection_type = 'alms_plate';
        $this->cash_amount = '0.00';
        $this->cheque_amount = '0.00';
    }

    private function resetGrantForm(): void
    {
        $this->reset(['grantId', 'recipient_name', 'purpose', 'relief_chest_number', 'bacs_reference', 'proposer_member_id', 'seconder_member_id', 'committee_meeting_id']);
        $this->grant_amount = '0.00';
        $this->approval_status = 'proposed';
    }

    public function render()
    {
        $club = $this->getClub();

        // 1. Festival Target
        $target = FestivalTarget::where('club_id', $club->id)->first();
        if (! $target) {
            $target = FestivalTarget::create([
                'club_id' => $club->id,
                'festival_name' => 'Durham 2029 Festival',
                'relief_chest_ref' => 'E1418',
                'target_amount' => 25000.00,
                'bronze_tier' => 5000.00,
                'silver_tier' => 18000.00,
                'gold_tier' => 18000.00,
                'platinum_tier' => 25000.00,
            ]);
        }

        // 2. Collections
        $collections = CharityCollection::where('club_id', $club->id)
            ->with(['countedBy', 'witnessedBy'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        $totalCollectionsCash = CharityCollection::where('club_id', $club->id)->sum('cash_amount');
        $totalCollectionsCheque = CharityCollection::where('club_id', $club->id)->sum('cheque_amount');
        $totalCollectionsAmount = $totalCollectionsCash + $totalCollectionsCheque;

        // 3. Grants
        $grants = CharityGrant::where('club_id', $club->id)
            ->with(['proposer', 'seconder', 'committeeMeeting'])
            ->orderBy('created_at', 'desc')
            ->get();
        $totalGrantsDisbursed = CharityGrant::where('club_id', $club->id)->where('approval_status', GrantApprovalStatus::Disbursed->value)->sum('amount');

        // 4. Member Festival Giving & Committee Meetings
        $activeMembers = Member::where('club_id', $club->id)->active()->orderBy('last_name')->get();
        $committeeMeetings = ClubCommitteeMeeting::where('club_id', $club->id)->orderBy('meeting_date', 'desc')->get();
        $memberGivingRecords = MemberFestivalGiving::whereIn('member_id', $activeMembers->pluck('id'))->get()->keyBy('member_id');

        $totalMemberDonations = $memberGivingRecords->sum('total_donated_to_date');
        $totalRaisedForFestival = $totalCollectionsAmount + $totalMemberDonations;

        $targetPercentage = $target->getPercentage($totalRaisedForFestival);
        $currentHonorTier = $target->getCurrentTier($totalRaisedForFestival);

        $giftAidService = app(ReliefChestReconciliationService::class);
        $giftAidSummary = $giftAidService->getGiftAidSummary($club);
        $reconciledDonations = $giftAidService->getReconciledDonations($club);

        return view('livewire.charity.charity-dashboard', [
            'club' => $club,
            'target' => $target,
            'totalRaisedForFestival' => $totalRaisedForFestival,
            'targetPercentage' => $targetPercentage,
            'currentHonorTier' => $currentHonorTier,
            'giftAidSummary' => $giftAidSummary,
            'reconciledDonations' => $reconciledDonations,
            'collections' => $collections,
            'totalCollectionsAmount' => $totalCollectionsAmount,
            'grants' => $grants,
            'totalGrantsDisbursed' => $totalGrantsDisbursed,
            'activeMembers' => $activeMembers,
            'committeeMeetings' => $committeeMeetings,
            'memberGivingRecords' => $memberGivingRecords,
            'collectionTypes' => CollectionType::cases(),
            'grantStatuses' => GrantApprovalStatus::cases(),
        ])->layout('components.layouts.app', [
            'title' => 'Charity Dashboard',
            'club' => $club,
        ]);
    }
}
