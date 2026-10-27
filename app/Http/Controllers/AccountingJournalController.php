<?php

namespace App\Http\Controllers;

use App\Http\Requests\AccountingAdmin\StoreJournalEntryRequest;
use App\Http\Requests\AccountingAdmin\UpdateJournalEntryRequest;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manual journal entries. Split out of AccountingAdminController; the URLs, route names and permissions are unchanged.
 */
class AccountingJournalController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService
    ) {}

    public function createJournal(string $clubSlug): Response
    {
        $club = $this->findClub($clubSlug);

        $accounts = Account::where('club_id', $club->id)
            ->orderBy('code')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'type' => $a->type,
            ]);

        return Inertia::render('Admin/Accounting/JournalForm', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'accounts' => $accounts,
        ]);
    }

    public function storeJournalEntry(StoreJournalEntryRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        $this->accountingService->postJournalEntry($club, $validated);

        return redirect()->route('admin.accounting.index', ['clubSlug' => $club->slug, 'tab' => 'accounting'])
            ->with('success', 'Double-entry journal entry posted successfully.');
    }

    public function editJournalEntry(string $clubSlug, int $id): Response
    {
        $club = $this->findClub($clubSlug);
        $entry = JournalEntry::with('items.account')
            ->where('club_id', $club->id)
            ->where('id', $id)
            ->firstOrFail();

        $accounts = Account::where('club_id', $club->id)
            ->orderBy('code')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'type' => $a->type,
            ]);

        return Inertia::render('Admin/Accounting/JournalForm', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'journalEntry' => [
                'id' => $entry->id,
                'reference_number' => $entry->reference_number,
                'entry_date' => $entry->entry_date->format('Y-m-d'),
                'description' => $entry->description,
                'status' => $entry->status,
                'items' => $entry->items->map(fn ($item) => [
                    'id' => $item->id,
                    'account_id' => $item->account_id,
                    'debit' => (float) $item->debit,
                    'credit' => (float) $item->credit,
                    'memo' => $item->memo ?? '',
                ]),
            ],
            'accounts' => $accounts,
        ]);
    }

    public function updateJournalEntry(UpdateJournalEntryRequest $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        try {
            $this->accountingService->updateJournalEntry($club, $id, $validated);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.accounting.index', ['clubSlug' => $clubSlug, 'tab' => 'general_ledger'])
            ->with('success', 'Journal entry updated successfully.');
    }
}
