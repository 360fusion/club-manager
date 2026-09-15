<?php

namespace App\Http\Controllers;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Club;
use App\Services\AccountingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingAdminController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService
    ) {}

    public function index(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $this->accountingService->seedDefaultAccounts($club);

        $accounts = Account::where('club_id', $club->id)
            ->orderBy('code')
            ->get()
            ->map(fn ($acc) => [
                'id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'type' => $acc->type,
                'currency' => $acc->currency,
                'is_active' => $acc->is_active,
                'balance' => $acc->balance,
                'formatted_balance' => '£' . number_format($acc->balance, 2),
            ]);

        $journalEntries = JournalEntry::where('club_id', $club->id)
            ->with(['items.account', 'createdBy'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->take(25)
            ->get()
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'reference_number' => $entry->reference_number,
                'entry_date' => $entry->entry_date->format('d M Y'),
                'description' => $entry->description,
                'source_type' => $entry->source_type,
                'status' => $entry->status,
                'total_debit' => $entry->total_debit,
                'formatted_total' => '£' . number_format($entry->total_debit, 2),
                'created_by' => $entry->createdBy ? $entry->createdBy->name : 'System',
                'items' => $entry->items->map(fn ($item) => [
                    'id' => $item->id,
                    'account_code' => $item->account->code,
                    'account_name' => $item->account->name,
                    'debit' => $item->debit,
                    'credit' => $item->credit,
                    'memo' => $item->memo,
                ]),
            ]);

        $summary = $this->accountingService->getFinancialSummary($club);

        return Inertia::render('Admin/Accounting/Index', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'accounts' => $accounts,
            'journalEntries' => $journalEntries,
            'summary' => $summary,
        ]);
    }

    public function storeAccount(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:accounting_accounts,code,NULL,id,club_id,' . $club->id],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
        ]);

        Account::create([
            'club_id' => $club->id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'currency' => 'GBP',
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', 'Account added to Chart of Accounts.');
    }

    public function storeJournalEntry(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'entry_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:2'],
            'items.*.account_id' => ['required', 'exists:accounting_accounts,id'],
            'items.*.debit' => ['numeric', 'min:0'],
            'items.*.credit' => ['numeric', 'min:0'],
            'items.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        $this->accountingService->postJournalEntry($club, $validated);

        return redirect()->back()->with('success', 'Double-entry journal entry posted successfully.');
    }
}
