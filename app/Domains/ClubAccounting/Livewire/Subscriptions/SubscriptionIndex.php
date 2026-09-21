<?php

namespace App\Domains\ClubAccounting\Livewire\Subscriptions;

use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Domains\ClubAccounting\Models\SubscriptionTier;
use App\Domains\ClubAccounting\Services\SubscriptionBillingService;
use App\Models\Club;
use App\Support\Currencies;
use Carbon\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class SubscriptionIndex extends Component
{
    use WithPagination;

    #[Locked]
    public string $clubSlug;

    public string $search = '';

    public int $selectedYear = 2026;

    public ?string $statusFilter = null;

    // Modals visibility
    public bool $showBillingModal = false;

    public bool $showTierModal = false;

    public bool $showPaymentModal = false;

    public bool $showArrearsModal = false;

    // Annual Billing Run Form
    public int $billing_year = 2026;

    public ?string $billing_due_date = null;

    // Tier Form
    public ?int $tierId = null;

    public string $tier_name = '';

    public string $tier_amount = '160.00';

    public string $tier_description = '';

    public bool $tier_active = true;

    // Payment Form
    public ?int $selectedSubscriptionId = null;

    public string $payment_amount = '0.00';

    public string $payment_reference = '';

    public string $payment_notes = '';

    public function mount(string $clubSlug): void
    {
        $this->clubSlug = $clubSlug;
        $this->selectedYear = (int) Carbon::now()->format('Y');
        $this->billing_year = $this->selectedYear;
        $this->billing_due_date = Carbon::create($this->billing_year, 4, 1)->format('Y-m-d');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedYear(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openBillingModal(): void
    {
        $this->billing_year = (int) Carbon::now()->format('Y');
        $this->billing_due_date = Carbon::create($this->billing_year, 4, 1)->format('Y-m-d');
        $this->showBillingModal = true;
    }

    public function runAnnualBilling(SubscriptionBillingService $billingService): void
    {
        $this->validate([
            'billing_year' => 'required|integer|min:2020|max:2050',
            'billing_due_date' => 'required|date',
        ]);

        $club = $this->getClub();
        $result = $billingService->generateAnnualBillingRun(
            $club,
            $this->billing_year,
            Carbon::parse($this->billing_due_date)
        );

        session()->flash('success', "Annual billing run completed for {$this->billing_year}: {$result['created_count']} new invoices created (".Currencies::format($result['total_billed'], $club)." total billed), {$result['skipped_count']} existing skipped.");
        $this->showBillingModal = false;
        $this->selectedYear = $this->billing_year;
    }

    public function openTierModal(?int $id = null): void
    {
        $club = $this->getClub();
        if ($id) {
            $tier = SubscriptionTier::where('club_id', $club->id)->findOrFail($id);
            $this->tierId = $tier->id;
            $this->tier_name = $tier->name;
            $this->tier_amount = (string) $tier->annual_amount;
            $this->tier_description = $tier->description ?? '';
            $this->tier_active = (bool) $tier->is_active;
        } else {
            $this->resetTierForm();
        }

        $this->showTierModal = true;
    }

    public function saveTier(): void
    {
        $club = $this->getClub();

        $this->validate([
            'tier_name' => 'required|string|max:100',
            'tier_amount' => 'required|numeric|min:0|max:99999999.99',
            'tier_description' => 'nullable|string|max:10000',
            'tier_active' => 'boolean',
        ]);

        $data = [
            'club_id' => $club->id,
            'name' => $this->tier_name,
            'annual_amount' => (float) $this->tier_amount,
            'description' => $this->tier_description,
            'is_active' => $this->tier_active,
        ];

        if ($this->tierId) {
            $tier = SubscriptionTier::where('club_id', $club->id)->findOrFail($this->tierId);
            $tier->update($data);
            session()->flash('success', "Subscription Tier '{$tier->name}' updated.");
        } else {
            $tier = SubscriptionTier::create($data);
            session()->flash('success', "New Subscription Tier '{$tier->name}' created.");
        }

        $this->showTierModal = false;
        $this->resetTierForm();
    }

    public function assignTierToMember(int $memberId, ?int $tierId): void
    {
        $club = $this->getClub();
        $member = Member::where('club_id', $club->id)->findOrFail($memberId);
        $member->update(['subscription_tier_id' => $tierId]);

        session()->flash('success', "Updated subscription tier assignment for '{$member->full_name}'.");
    }

    public function openPaymentModal(int $subscriptionId): void
    {
        $club = $this->getClub();
        $sub = MemberSubscription::where('club_id', $club->id)->findOrFail($subscriptionId);

        $this->selectedSubscriptionId = $sub->id;
        $this->payment_amount = (string) $sub->balance_due;
        $this->payment_reference = 'BAC-'.Carbon::now()->format('Ymd');
        $this->payment_notes = '';
        $this->showPaymentModal = true;
    }

    public function recordPayment(SubscriptionBillingService $billingService): void
    {
        $club = $this->getClub();
        $sub = MemberSubscription::where('club_id', $club->id)->findOrFail($this->selectedSubscriptionId);

        $this->validate([
            'payment_amount' => 'required|numeric|min:0.01|max:99999999.99',
            'payment_reference' => 'nullable|string|max:100',
            'payment_notes' => 'nullable|string|max:10000',
        ]);

        $billingService->recordPayment(
            $sub,
            (float) $this->payment_amount,
            $this->payment_reference,
            $this->payment_notes
        );

        session()->flash('success', 'Payment of '.Currencies::format((float) $this->payment_amount, $club)." recorded for {$sub->member->full_name}.");
        $this->showPaymentModal = false;
    }

    public function waiveDues(int $subscriptionId): void
    {
        $club = $this->getClub();
        $sub = MemberSubscription::where('club_id', $club->id)->findOrFail($subscriptionId);
        $sub->update([
            'status' => SubscriptionStatus::Waived,
            'notes' => trim(($sub->notes ?? '')."\nDues waived by Lodge Secretary on ".Carbon::now()->format('Y-m-d')),
        ]);

        session()->flash('success', "Dues waived for {$sub->member->full_name}.");
    }

    public function runRule181ArrearsAudit(SubscriptionBillingService $billingService): void
    {
        $club = $this->getClub();
        $arrearsList = $billingService->checkRule181Arrears($club, 90);

        session()->flash('success', 'Rule 181 Arrears Audit completed: '.$arrearsList->count().' members identified with statutory arrears warning.');
        $this->showArrearsModal = true;
    }

    private function getClub(): Club
    {
        return Club::where('slug', $this->clubSlug)->firstOrFail();
    }

    private function resetTierForm(): void
    {
        $this->reset(['tierId', 'tier_name', 'tier_description']);
        $this->tier_amount = '160.00';
        $this->tier_active = true;
    }

    public function render(SubscriptionBillingService $billingService)
    {
        $club = $this->getClub();

        // Subscription Tiers
        $tiers = SubscriptionTier::where('club_id', $club->id)->orderBy('annual_amount')->get();

        // Main Subscription Roster Query
        $query = MemberSubscription::with(['member.subscriptionTier', 'tier', 'member.customerAccount'])
            ->where('club_id', $club->id)
            ->year($this->selectedYear);

        if (! empty($this->search)) {
            $term = '%'.trim($this->search).'%';
            $query->whereHas('member', function ($q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term);
            });
        }

        if (! empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        $subscriptions = $query->paginate(20);

        // Aggregate Metrics for Selected Year
        $yearSubscriptions = MemberSubscription::where('club_id', $club->id)->year($this->selectedYear)->get();
        $totalBilled = $yearSubscriptions->sum('amount_due');
        $totalCollected = $yearSubscriptions->sum('amount_paid');
        $totalOutstanding = max(0, $totalBilled - $totalCollected);
        $arrearsCount = MemberSubscription::where('club_id', $club->id)->arrears()->count();

        // Arrears list for confidential review drawer
        $arrearsSubscriptions = MemberSubscription::with(['member', 'tier'])
            ->where('club_id', $club->id)
            ->arrears()
            ->get();

        $activeMembers = Member::where('club_id', $club->id)->active()->orderBy('last_name')->get();

        return view('livewire.subscriptions.subscription-index', [
            'club' => $club,
            'tiers' => $tiers,
            'subscriptions' => $subscriptions,
            'totalBilled' => $totalBilled,
            'totalCollected' => $totalCollected,
            'totalOutstanding' => $totalOutstanding,
            'arrearsCount' => $arrearsCount,
            'arrearsSubscriptions' => $arrearsSubscriptions,
            'activeMembers' => $activeMembers,
            'statuses' => SubscriptionStatus::cases(),
        ])->layout('components.layouts.app', [
            'title' => 'Subscriptions — '.$club->name,
            'club' => $club,
        ]);
    }
}
