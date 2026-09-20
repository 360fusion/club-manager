<?php

namespace App\Domains\ClubAccounting\Livewire\Banking;

use App\Domains\ClubAccounting\Models\BankAccount;
use App\Models\Accounting\Account;
use App\Models\Club;
use Livewire\Component;

class BankAccountsIndex extends Component
{
    public string $clubSlug;

    // Modal state for adding a bank account
    public bool $showCreateModal = false;
    public string $bank_name = '';
    public string $account_name = '';
    public string $account_type = 'current'; // current, savings, credit_card, payment_gateway, merchant, cash
    public string $account_number = '';
    public string $sort_code = '';
    public string $currency = 'GBP';
    public float $opening_balance = 0.00;

    public function mount(string $clubSlug): void
    {
        $this->clubSlug = $clubSlug;
        $this->ensureDefaultAccountExists();
    }

    public function ensureDefaultAccountExists(): void
    {
        $club = $this->getClub();
        if (BankAccount::where('club_id', $club->id)->count() === 0) {
            $ledgerAcc = Account::where('club_id', $club->id)->where('code', '1000')->first();
            BankAccount::create([
                'club_id' => $club->id,
                'account_id' => $ledgerAcc?->id,
                'bank_name' => 'High Street Bank',
                'account_name' => 'Main Operating Account',
                'account_type' => 'current',
                'account_number' => '12345678',
                'sort_code' => '20-00-00',
                'currency' => 'GBP',
                'opening_balance' => 0.00,
                'is_active' => true,
            ]);
        }
    }

    public function openCreateModal(): void
    {
        $this->reset(['bank_name', 'account_name', 'account_type', 'account_number', 'sort_code', 'currency', 'opening_balance']);
        $this->showCreateModal = true;
    }

    public function saveBankAccount(): void
    {
        $this->validate([
            'bank_name' => 'required|string|max:100',
            'account_name' => 'required|string|max:150',
            'account_type' => 'required|string|in:current,savings,credit_card,payment_gateway,merchant,cash',
            'account_number' => 'nullable|string|max:50',
            'sort_code' => 'nullable|string|max:20',
            'currency' => 'required|string|size:3',
            'opening_balance' => 'required|numeric',
        ]);

        $club = $this->getClub();

        // 1. Generate unique nominal code for Chart of Accounts (e.g. 1010, 1020, 1030)
        $existingCodes = Account::where('club_id', $club->id)
            ->where('type', 'asset')
            ->where('code', 'like', '10%')
            ->pluck('code')
            ->toArray();

        $codeNum = 1010;
        while (in_array((string)$codeNum, $existingCodes)) {
            $codeNum += 10;
        }

        $ledgerAcc = Account::create([
            'club_id' => $club->id,
            'code' => (string)$codeNum,
            'name' => "{$this->bank_name} — {$this->account_name}",
            'type' => 'asset',
            'currency' => $this->currency,
            'is_active' => true,
        ]);

        // 2. Create BankAccount record
        BankAccount::create([
            'club_id' => $club->id,
            'account_id' => $ledgerAcc->id,
            'bank_name' => $this->bank_name,
            'account_name' => $this->account_name,
            'account_type' => $this->account_type,
            'account_number' => $this->account_number ?: null,
            'sort_code' => $this->sort_code ?: null,
            'currency' => strtoupper($this->currency),
            'opening_balance' => $this->opening_balance,
            'is_active' => true,
        ]);

        session()->flash('success', "Bank account '{$this->bank_name} — {$this->account_name}' created and linked to Nominal Code {$ledgerAcc->code}!");
        $this->showCreateModal = false;
    }

    public function toggleAccountActive(int $bankAccountId): void
    {
        $club = $this->getClub();
        $account = BankAccount::where('club_id', $club->id)->findOrFail($bankAccountId);
        $account->update(['is_active' => !$account->is_active]);

        session()->flash('success', "Bank account status updated.");
    }

    public function render()
    {
        $club = $this->getClub();
        $bankAccounts = BankAccount::where('club_id', $club->id)
            ->with(['account', 'transactions'])
            ->orderBy('is_active', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        return view('livewire.banking.bank-accounts-index', [
            'club' => $club,
            'bankAccounts' => $bankAccounts,
        ])->layout('layouts.app');
    }

    private function getClub(): Club
    {
        return Club::where('slug', $this->clubSlug)->firstOrFail();
    }
}
