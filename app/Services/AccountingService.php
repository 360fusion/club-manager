<?php

namespace App\Services;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Club;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AccountingService
{
    /**
     * Default Chart of Accounts Template
     */
    public const DEFAULT_ACCOUNTS = [
        ['code' => '1000', 'name' => 'Operating Bank Account', 'type' => 'asset'],
        ['code' => '1100', 'name' => 'Petty Cash', 'type' => 'asset'],
        ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'asset'],
        ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability'],
        ['code' => '2100', 'name' => 'Unearned Dues Revenue', 'type' => 'liability'],
        ['code' => '3000', 'name' => 'Retained Earnings', 'type' => 'equity'],
        ['code' => '3100', 'name' => 'Member Reserves', 'type' => 'equity'],
        ['code' => '4000', 'name' => 'Membership Dues Income', 'type' => 'revenue'],
        ['code' => '4100', 'name' => 'Event Ticket Revenue', 'type' => 'revenue'],
        ['code' => '4200', 'name' => 'Bar & Dining Sales', 'type' => 'revenue'],
        ['code' => '5000', 'name' => 'Facility & Clubhouse Maintenance', 'type' => 'expense'],
        ['code' => '5100', 'name' => 'Utilities', 'type' => 'expense'],
        ['code' => '5200', 'name' => 'Catering & Food Supplies', 'type' => 'expense'],
        ['code' => '5300', 'name' => 'Administrative & Software Fees', 'type' => 'expense'],
    ];

    /**
     * Seed default Chart of Accounts for a club.
     */
    public function seedDefaultAccounts(Club $club): void
    {
        foreach (self::DEFAULT_ACCOUNTS as $acc) {
            Account::firstOrCreate(
                [
                    'club_id' => $club->id,
                    'code' => $acc['code'],
                ],
                [
                    'name' => $acc['name'],
                    'type' => $acc['type'],
                    'currency' => 'GBP',
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Get or create a specific account by code.
     */
    public function getAccount(Club $club, string $code): Account
    {
        $this->seedDefaultAccounts($club);

        $account = Account::where('club_id', $club->id)->where('code', $code)->first();

        if (!$account) {
            throw new InvalidArgumentException("Account with code {$code} not found for club.");
        }

        return $account;
    }

    /**
     * Post a balanced double-entry journal entry.
     */
    public function postJournalEntry(Club $club, array $data): JournalEntry
    {
        $items = $data['items'] ?? [];
        if (count($items) < 2) {
            throw new InvalidArgumentException("A journal entry must contain at least 2 line items.");
        }

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($items as $item) {
            $totalDebit += (float) ($item['debit'] ?? 0);
            $totalCredit += (float) ($item['credit'] ?? 0);
        }

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new InvalidArgumentException("Journal entry is not balanced. Total Debits (£{$totalDebit}) must equal Total Credits (£{$totalCredit}).");
        }

        return DB::transaction(function () use ($club, $data, $items) {
            $entryCount = JournalEntry::where('club_id', $club->id)->count() + 1;
            $ref = $data['reference_number'] ?? 'JE-' . date('Y') . '-' . str_pad((string) $entryCount, 4, '0', STR_PAD_LEFT);

            $entry = JournalEntry::create([
                'club_id' => $club->id,
                'reference_number' => $ref,
                'entry_date' => $data['entry_date'] ?? date('Y-m-d'),
                'description' => $data['description'] ?? 'General Journal Entry',
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'status' => $data['status'] ?? 'posted',
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);

            foreach ($items as $item) {
                $entry->items()->create([
                    'account_id' => $item['account_id'],
                    'debit' => (float) ($item['debit'] ?? 0),
                    'credit' => (float) ($item['credit'] ?? 0),
                    'memo' => $item['memo'] ?? null,
                ]);
            }

            return $entry;
        });
    }

    /**
     * Record Member Dues Payment in Double-Entry Ledger
     */
    public function recordMemberDuesPayment(Club $club, float $amount, string $description, ?int $sourceId = null): JournalEntry
    {
        $bankAcc = $this->getAccount($club, '1000'); // Operating Bank Account
        $duesAcc = $this->getAccount($club, '4000'); // Membership Dues Income

        return $this->postJournalEntry($club, [
            'description' => $description,
            'source_type' => 'MemberDues',
            'source_id' => $sourceId,
            'items' => [
                ['account_id' => $bankAcc->id, 'debit' => $amount, 'credit' => 0, 'memo' => 'Dues Payment Received'],
                ['account_id' => $duesAcc->id, 'debit' => 0, 'credit' => $amount, 'memo' => 'Membership Dues Revenue'],
            ],
        ]);
    }

    /**
     * Record Event Ticket Sale in Double-Entry Ledger
     */
    public function recordEventTicketSale(Club $club, float $amount, string $description, ?int $sourceId = null): JournalEntry
    {
        $bankAcc = $this->getAccount($club, '1000'); // Operating Bank Account
        $ticketAcc = $this->getAccount($club, '4100'); // Event Ticket Revenue

        return $this->postJournalEntry($club, [
            'description' => $description,
            'source_type' => 'EventRsvp',
            'source_id' => $sourceId,
            'items' => [
                ['account_id' => $bankAcc->id, 'debit' => $amount, 'credit' => 0, 'memo' => 'Event Ticket Payment'],
                ['account_id' => $ticketAcc->id, 'debit' => 0, 'credit' => $amount, 'memo' => 'Event Ticket Revenue'],
            ],
        ]);
    }

    /**
     * Get Overall Financial Summary KPIs
     */
    public function getFinancialSummary(Club $club): array
    {
        $this->seedDefaultAccounts($club);

        $accounts = Account::where('club_id', $club->id)->get();

        $totalAssets = 0.0;
        $totalLiabilities = 0.0;
        $totalEquity = 0.0;
        $totalRevenue = 0.0;
        $totalExpenses = 0.0;

        foreach ($accounts as $acc) {
            $bal = $acc->balance;
            switch ($acc->type) {
                case 'asset':
                    $totalAssets += $bal;
                    break;
                case 'liability':
                    $totalLiabilities += $bal;
                    break;
                case 'equity':
                    $totalEquity += $bal;
                    break;
                case 'revenue':
                    $totalRevenue += $bal;
                    break;
                case 'expense':
                    $totalExpenses += $bal;
                    break;
            }
        }

        $netIncome = $totalRevenue - $totalExpenses;

        return [
            'total_assets' => round($totalAssets, 2),
            'total_liabilities' => round($totalLiabilities, 2),
            'total_equity' => round($totalEquity, 2),
            'total_revenue' => round($totalRevenue, 2),
            'total_expenses' => round($totalExpenses, 2),
            'net_income' => round($netIncome, 2),
        ];
    }
}
