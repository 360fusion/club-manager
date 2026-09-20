<?php

namespace App\Domains\ClubAccounting\Livewire\Banking;

use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Domains\ClubAccounting\Services\BankReconciliationMatcherService;
use App\Models\Accounting\Bill;
use App\Models\Club;
use Livewire\Component;
use Livewire\WithPagination;

class BankReconciliationWorkspace extends Component
{
    use WithPagination;

    public string $clubSlug;

    public ?int $selectedTransactionId = null;

    public string $search = '';

    // Manual Lookup Drawer State
    public bool $showManualDrawer = false;

    public string $manualSearch = '';

    public string $manualAllocationType = 'member_subscription'; // member_subscription, supplier_bill, ledger_account

    public string $manualNominalCode = '4000';

    public function mount(string $clubSlug): void
    {
        $this->clubSlug = $clubSlug;
        $this->autoSelectFirstUnmatched();
    }

    public function autoSelectFirstUnmatched(): void
    {
        $club = $this->getClub();
        $first = BankTransaction::where('club_id', $club->id)
            ->unmatched()
            ->orderBy('transaction_date', 'asc')
            ->first();

        $this->selectedTransactionId = $first?->id;
    }

    public function selectTransaction(int $id): void
    {
        $club = $this->getClub();
        $tx = BankTransaction::where('club_id', $club->id)->findOrFail($id);
        $this->selectedTransactionId = $tx->id;
    }

    public function reconcileSuggested(int $transactionId, string $matchType, int $targetId, BankReconciliationMatcherService $matcher): void
    {
        $club = $this->getClub();
        $tx = BankTransaction::where('club_id', $club->id)->findOrFail($transactionId);

        $matcher->reconcileTransaction($tx, $matchType, $targetId);

        session()->flash('success', 'Transaction reconciled successfully!');
        $this->autoSelectFirstUnmatched();
    }

    public function openManualDrawer(int $transactionId): void
    {
        $club = $this->getClub();
        $tx = BankTransaction::where('club_id', $club->id)->findOrFail($transactionId);

        $this->selectedTransactionId = $tx->id;
        $this->manualAllocationType = $tx->amount > 0 ? 'member_subscription' : 'supplier_bill';
        $this->manualSearch = '';
        $this->showManualDrawer = true;
    }

    public function executeManualAllocation(int $targetId, BankReconciliationMatcherService $matcher): void
    {
        $club = $this->getClub();
        $tx = BankTransaction::where('club_id', $club->id)->findOrFail($this->selectedTransactionId);

        $matcher->reconcileTransaction($tx, $this->manualAllocationType, $targetId, [
            'nominal_code' => $this->manualNominalCode,
        ]);

        session()->flash('success', 'Manual allocation reconciled for transaction.');
        $this->showManualDrawer = false;
        $this->autoSelectFirstUnmatched();
    }

    public function ignoreLine(int $transactionId, BankReconciliationMatcherService $matcher): void
    {
        $club = $this->getClub();
        $tx = BankTransaction::where('club_id', $club->id)->findOrFail($transactionId);

        $matcher->ignoreTransaction($tx);

        session()->flash('success', 'Transaction line marked as ignored.');
        $this->autoSelectFirstUnmatched();
    }

    private function getClub(): Club
    {
        return Club::where('slug', $this->clubSlug)->firstOrFail();
    }

    public function render(BankReconciliationMatcherService $matcher)
    {
        $club = $this->getClub();

        // 1. Left Panel: Unmatched Bank Transactions List
        $txQuery = BankTransaction::where('club_id', $club->id)
            ->unmatched()
            ->orderBy('transaction_date', 'asc');

        if (! empty($this->search)) {
            $txQuery->search($this->search);
        }

        $unmatchedTransactions = $txQuery->paginate(15);

        // 2. Selected Transaction Details & Suggested Match Candidates
        $selectedTx = $this->selectedTransactionId
            ? BankTransaction::where('club_id', $club->id)->find($this->selectedTransactionId)
            : null;

        $suggestedMatches = $selectedTx ? $matcher->suggestMatches($selectedTx) : [];

        // 3. Manual Drawer Candidates
        $manualCandidates = collect();
        if ($this->showManualDrawer && $selectedTx) {
            if ($this->manualAllocationType === 'member_subscription') {
                $subQuery = MemberSubscription::where('club_id', $club->id)->unpaid()->with('member');
                if (! empty($this->manualSearch)) {
                    $term = '%'.trim($this->manualSearch).'%';
                    $subQuery->whereHas('member', fn ($q) => $q->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term));
                }
                $manualCandidates = $subQuery->take(15)->get();
            } elseif ($this->manualAllocationType === 'supplier_bill') {
                $billQuery = Bill::where('club_id', $club->id)->where('status', 'unpaid');
                if (! empty($this->manualSearch)) {
                    $term = '%'.trim($this->manualSearch).'%';
                    $billQuery->where(fn ($q) => $q->where('vendor_name', 'like', $term)->orWhere('bill_number', 'like', $term));
                }
                $manualCandidates = $billQuery->take(15)->get();
            }
        }

        // Stats
        $unmatchedCount = BankTransaction::where('club_id', $club->id)->unmatched()->count();
        $reconciledCount = BankTransaction::where('club_id', $club->id)->matched()->count();

        return view('livewire.banking.bank-reconciliation-workspace', [
            'club' => $club,
            'unmatchedTransactions' => $unmatchedTransactions,
            'selectedTx' => $selectedTx,
            'suggestedMatches' => $suggestedMatches,
            'manualCandidates' => $manualCandidates,
            'unmatchedCount' => $unmatchedCount,
            'reconciledCount' => $reconciledCount,
        ])->layout('components.layouts.app', [
            'title' => 'Bank Reconciliation Workspace — '.$club->name,
            'club' => $club,
        ]);
    }
}
