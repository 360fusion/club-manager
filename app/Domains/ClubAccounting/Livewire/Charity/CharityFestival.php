<?php

namespace App\Domains\ClubAccounting\Livewire\Charity;

use App\Domains\ClubAccounting\Models\CharityCollection;
use App\Domains\ClubAccounting\Models\FestivalTarget;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberFestivalGiving;
use App\Models\Club;
use Livewire\Component;

class CharityFestival extends Component
{
    public string $clubSlug;

    // Modals Visibility
    public bool $showTargetModal = false;
    public bool $showGivingModal = false;

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
            $this->target_amount = (string)$target->target_amount;
            $this->bronze_tier = (string)$target->bronze_tier;
            $this->silver_tier = (string)$target->silver_tier;
            $this->gold_tier = (string)$target->gold_tier;
            $this->platinum_tier = (string)$target->platinum_tier;
        }
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
                'target_amount' => (float)$this->target_amount,
                'bronze_tier' => (float)$this->bronze_tier,
                'silver_tier' => (float)$this->silver_tier,
                'gold_tier' => (float)$this->gold_tier,
                'platinum_tier' => (float)$this->platinum_tier,
            ]
        );

        session()->flash('success', "Provincial Festival Target milestones saved.");
        $this->showTargetModal = false;
    }

    public function openGivingModal(int $memberId): void
    {
        $this->givingMemberId = $memberId;
        $giving = MemberFestivalGiving::where('member_id', $memberId)->first();
        if ($giving) {
            $this->regular_giving_amount = (string)$giving->regular_giving_amount;
            $this->total_donated_to_date = (string)$giving->total_donated_to_date;
            $this->qualifies_for_jewel = (bool)$giving->qualifies_for_jewel;
            $this->qualifies_for_bar = (bool)$giving->qualifies_for_bar;
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

        $donated = (float)$this->total_donated_to_date;
        $autoJewel = $this->qualifies_for_jewel || $donated >= 250.00;
        $autoBar = $this->qualifies_for_bar || $donated >= 500.00;

        MemberFestivalGiving::updateOrCreate(
            ['member_id' => $this->givingMemberId],
            [
                'regular_giving_amount' => (float)$this->regular_giving_amount,
                'total_donated_to_date' => $donated,
                'qualifies_for_jewel' => $autoJewel,
                'qualifies_for_bar' => $autoBar,
            ]
        );

        session()->flash('success', "Member festival giving updated.");
        $this->showGivingModal = false;
    }

    public function render()
    {
        $club = $this->getClub();
        $target = FestivalTarget::where('club_id', $club->id)->first();
        if (! $target) {
            $target = new FestivalTarget([
                'festival_name' => 'Durham 2029 Festival',
                'relief_chest_ref' => 'E1418',
                'target_amount' => 25000.00,
                'bronze_tier' => 5000.00,
                'silver_tier' => 10000.00,
                'gold_tier' => 18000.00,
                'platinum_tier' => 25000.00,
            ]);
        }

        $totalCollectionsCash = CharityCollection::where('club_id', $club->id)->sum('cash_amount');
        $totalCollectionsCheque = CharityCollection::where('club_id', $club->id)->sum('cheque_amount');
        $totalCollectionsAmount = $totalCollectionsCash + $totalCollectionsCheque;

        $activeMembers = Member::where('club_id', $club->id)->active()->orderBy('last_name')->get();
        $memberGivingRecords = MemberFestivalGiving::whereIn('member_id', $activeMembers->pluck('id'))->get()->keyBy('member_id');
        $totalMemberDonations = $memberGivingRecords->sum('total_donated_to_date');

        $totalRaisedForFestival = $totalCollectionsAmount + $totalMemberDonations;
        $targetPercentage = $target->getPercentage($totalRaisedForFestival);
        $currentHonorTier = $target->getCurrentTier($totalRaisedForFestival);

        return view('livewire.charity.charity-festival', [
            'club' => $club,
            'target' => $target,
            'totalRaisedForFestival' => $totalRaisedForFestival,
            'targetPercentage' => $targetPercentage,
            'currentHonorTier' => $currentHonorTier,
            'activeMembers' => $activeMembers,
            'memberGivingRecords' => $memberGivingRecords,
        ])->layout('components.layouts.app', [
            'title' => 'Festival Information — ' . $club->name,
            'club' => $club,
        ]);
    }
}
