<?php

namespace App\Http\Controllers;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingContact;
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

        $contactsQuery = AccountingContact::where('club_id', $club->id)->orderBy('name')->get();

        if ($contactsQuery->isEmpty()) {
            AccountingContact::create([
                'club_id' => $club->id,
                'type' => 'business',
                'name' => 'Oxford Rowing Supplies Ltd',
                'contact_person' => 'David Miller',
                'email' => 'sales@oxfordrowingsupplies.co.uk',
                'phone' => '+44 1865 240100',
                'role' => 'Vendor / Supplier',
                'tax_id' => 'GB 883 9920 11',
                'address_line_1' => 'Unit 4 Meadowside Works',
                'city' => 'Oxford',
                'postcode' => 'OX2 0ES',
                'notes' => 'Primary supplier for rowing equipment and maintenance parts.',
            ]);

            AccountingContact::create([
                'club_id' => $club->id,
                'type' => 'business',
                'name' => 'Thames Marine Insurance',
                'contact_person' => 'Sarah Jenkins',
                'email' => 'corporate@thamesmarine.co.uk',
                'phone' => '+44 20 7946 0999',
                'role' => 'Sponsor & Insurer',
                'tax_id' => 'GB 552 1198 44',
                'address_line_1' => '88 Leadenhall Street',
                'city' => 'London',
                'postcode' => 'EC3A 3BP',
                'notes' => 'Annual club insurance and regatta sponsor.',
            ]);

            AccountingContact::create([
                'club_id' => $club->id,
                'type' => 'person',
                'name' => 'Robert Sterling',
                'contact_person' => 'Robert Sterling',
                'email' => 'robert.sterling@coachnet.org',
                'phone' => '+44 7700 900456',
                'role' => 'Contractor / Head Coach',
                'tax_id' => 'UTR 982341',
                'address_line_1' => '15 Isis Waterside',
                'city' => 'Oxford',
                'postcode' => 'OX1 4XU',
                'notes' => 'Contract senior coach for regatta training.',
            ]);

            $contactsQuery = AccountingContact::where('club_id', $club->id)->orderBy('name')->get();
        }

        $contacts = $contactsQuery->map(fn ($c) => [
            'id' => $c->id,
            'type' => $c->type, // person or business
            'name' => $c->name,
            'contact_person' => $c->contact_person,
            'email' => $c->email,
            'phone' => $c->phone,
            'role' => $c->role,
            'tax_id' => $c->tax_id,
            'address_line_1' => $c->address_line_1,
            'address_line_2' => $c->address_line_2,
            'city' => $c->city,
            'postcode' => $c->postcode,
            'country' => $c->country,
            'formatted_address' => implode(', ', array_filter([$c->address_line_1, $c->city, $c->postcode])),
            'notes' => $c->notes,
            'is_active' => $c->is_active,
            'created_at' => $c->created_at->format('d M Y'),
        ]);

        $summary = $this->accountingService->getFinancialSummary($club);
        $reports = $this->accountingService->getReportsData($club);

        $clubSettings = array_merge([
            'company_name' => $club->name,
            'tax_registration_number' => 'GB 987 6543 21',
            'contact_email' => 'admin@' . $club->slug . '.org',
            'phone' => '+44 20 7946 0912',
            'address_line_1' => '100 Boathouse Way',
            'address_line_2' => '',
            'city' => 'Oxford',
            'county' => 'Oxfordshire',
            'postcode' => 'OX1 1AA',
            'country' => 'United Kingdom',
            'currency' => 'GBP',
            'receipt_footer_notes' => 'Thank you for supporting our club. Fees support equipment & clubhouse operations.',
            'dues_grace_period_days' => 14,
            'auto_invoice_days_before' => 7,
        ], $club->settings ?? []);

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
            'contacts' => $contacts,
            'summary' => $summary,
            'reports' => $reports,
            'settings' => $clubSettings,
        ]);
    }

    public function storeAccount(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:accounting_accounts,code,NULL,id,club_id,' . $club->id],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'opening_balance' => ['nullable', 'numeric'],
            'as_of_date' => ['nullable', 'date'],
        ]);

        $account = Account::create([
            'club_id' => $club->id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'currency' => 'GBP',
            'is_active' => true,
        ]);

        if (!empty($validated['opening_balance']) && (float) $validated['opening_balance'] != 0) {
            $this->accountingService->setOpeningBalance($club, $account, (float) $validated['opening_balance'], $validated['as_of_date'] ?? null);
        }

        return redirect()->back()->with('success', 'Account added to Chart of Accounts with opening balance.');
    }

    public function storeOpeningBalance(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'account_id' => ['required', 'exists:accounting_accounts,id'],
            'opening_balance' => ['required', 'numeric'],
            'as_of_date' => ['nullable', 'date'],
        ]);

        $account = Account::where('club_id', $club->id)->where('id', $validated['account_id'])->firstOrFail();

        $this->accountingService->setOpeningBalance($club, $account, (float) $validated['opening_balance'], $validated['as_of_date'] ?? null);

        return redirect()->back()->with('success', "Opening balance updated for {$account->code} - {$account->name}.");
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

    public function storeContact(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'type' => ['required', 'in:person,business'],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        AccountingContact::create([
            'club_id' => $club->id,
            'type' => $validated['type'],
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'tax_id' => $validated['tax_id'] ?? null,
            'address_line_1' => $validated['address_line_1'] ?? null,
            'address_line_2' => $validated['address_line_2'] ?? null,
            'city' => $validated['city'] ?? null,
            'postcode' => $validated['postcode'] ?? null,
            'country' => $validated['country'] ?? 'United Kingdom',
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', 'New contact added to directory.');
    }

    public function updateContact(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $contact = AccountingContact::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'type' => ['required', 'in:person,business'],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role' => ['required', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $contact->update([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'role' => $validated['role'],
            'tax_id' => $validated['tax_id'] ?? null,
            'address_line_1' => $validated['address_line_1'] ?? null,
            'address_line_2' => $validated['address_line_2'] ?? null,
            'city' => $validated['city'] ?? null,
            'postcode' => $validated['postcode'] ?? null,
            'country' => $validated['country'] ?? 'United Kingdom',
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Contact details updated successfully.');
    }

    public function destroyContact(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $contact = AccountingContact::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $contact->delete();

        return redirect()->back()->with('success', 'Contact removed from directory.');
    }
}
