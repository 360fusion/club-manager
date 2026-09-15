<?php

namespace App\Http\Controllers;

use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\JournalEntry;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
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
            ->take(30)
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

        $invoices = Invoice::where('club_id', $club->id)
            ->with('user')
            ->orderByDesc('created_at')
            ->take(50)
            ->get()
            ->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'title' => $inv->title,
                'amount' => $inv->amount,
                'formatted_amount' => '£' . number_format($inv->amount, 2),
                'status' => $inv->status,
                'recipient_name' => $inv->user ? $inv->user->name : 'Member',
                'created_at' => $inv->created_at->format('d M Y'),
                'paid_at' => $inv->paid_at ? $inv->paid_at->format('d M Y') : null,
            ]);

        $bills = Bill::where('club_id', $club->id)
            ->orderByDesc('due_date')
            ->take(50)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'bill_number' => $b->bill_number,
                'vendor_name' => $b->vendor_name,
                'category' => $b->category,
                'amount' => $b->amount,
                'formatted_amount' => '£' . number_format($b->amount, 2),
                'due_date' => $b->due_date->format('d M Y'),
                'status' => $b->status,
                'notes' => $b->notes,
                'paid_at' => $b->paid_at ? $b->paid_at->format('d M Y') : null,
            ]);

        $members = User::whereHas('clubs', fn ($q) => $q->where('clubs.id', $club->id))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $summary = $this->accountingService->getFinancialSummary($club);

        return Inertia::render('Admin/Accounting/Index', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'accounts' => $accounts,
            'journalEntries' => $journalEntries,
            'invoices' => $invoices,
            'bills' => $bills,
            'members' => $members,
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

    public function storeInvoice(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $invCount = Invoice::where('club_id', $club->id)->count() + 1;
        $invNum = 'INV-' . date('Y') . '-' . str_pad((string) $invCount, 4, '0', STR_PAD_LEFT);

        $invoice = Invoice::create([
            'club_id' => $club->id,
            'user_id' => $validated['user_id'],
            'invoice_number' => $invNum,
            'title' => $validated['title'],
            'amount' => $validated['amount'],
            'status' => 'unpaid',
        ]);

        // Auto post receivable ledger entry
        $arAcc = $this->accountingService->getAccount($club, '1200');
        $duesAcc = $this->accountingService->getAccount($club, '4000');

        $this->accountingService->postJournalEntry($club, [
            'description' => "Member Invoice Issued: {$invoice->title} ({$invoice->invoice_number})",
            'source_type' => 'Invoice',
            'source_id' => $invoice->id,
            'items' => [
                ['account_id' => $arAcc->id, 'debit' => $invoice->amount, 'credit' => 0, 'memo' => 'Accounts Receivable'],
                ['account_id' => $duesAcc->id, 'debit' => 0, 'credit' => $invoice->amount, 'memo' => 'Membership Income'],
            ],
        ]);

        return redirect()->back()->with('success', 'Member invoice created and posted to Accounts Receivable.');
    }

    public function storeBill(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'vendor_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->accountingService->createVendorBill($club, $validated);

        return redirect()->back()->with('success', 'Vendor bill recorded and posted to Accounts Payable.');
    }

    public function markBillPaid(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $bill = Bill::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $this->accountingService->markBillAsPaid($bill);

        return redirect()->back()->with('success', "Vendor bill {$bill->bill_number} marked as paid.");
    }

    public function markInvoicePaid(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $invoice = Invoice::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        if ($invoice->status !== 'paid') {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $arAcc = $this->accountingService->getAccount($club, '1200');
            $bankAcc = $this->accountingService->getAccount($club, '1000');

            $this->accountingService->postJournalEntry($club, [
                'description' => "Invoice Paid: {$invoice->title} ({$invoice->invoice_number})",
                'source_type' => 'InvoicePayment',
                'source_id' => $invoice->id,
                'items' => [
                    ['account_id' => $bankAcc->id, 'debit' => $invoice->amount, 'credit' => 0, 'memo' => 'Operating Bank Deposit'],
                    ['account_id' => $arAcc->id, 'debit' => 0, 'credit' => $invoice->amount, 'memo' => 'Clear Accounts Receivable'],
                ],
            ]);
        }

        return redirect()->back()->with('success', "Invoice {$invoice->invoice_number} marked as paid.");
    }
}
