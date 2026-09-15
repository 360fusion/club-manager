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
            ->with(['user', 'media'])
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
                'attachment' => $inv->media ? [
                    'id' => $inv->media->id,
                    'file_name' => $inv->media->file_name,
                    'url' => $inv->media->getUrl(),
                    'mime_type' => $inv->media->mime_type,
                    'size' => $inv->media->human_readable_size ?? (round($inv->media->size / 1024, 1) . ' KB'),
                    'is_image' => str_starts_with($inv->media->mime_type ?? '', 'image/'),
                ] : null,
            ]);

        $bills = Bill::where('club_id', $club->id)
            ->with('media')
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
                'attachment' => $b->media ? [
                    'id' => $b->media->id,
                    'file_name' => $b->media->file_name,
                    'url' => $b->media->getUrl(),
                    'mime_type' => $b->media->mime_type,
                    'size' => $b->media->human_readable_size ?? (round($b->media->size / 1024, 1) . ' KB'),
                    'is_image' => str_starts_with($b->media->mime_type ?? '', 'image/'),
                ] : null,
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

    public function createInvoice(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $members = \DB::table('club_user')
            ->join('users', 'club_user.user_id', '=', 'users.id')
            ->where('club_user.club_id', $club->id)
            ->select('users.id', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
            ]);

        return Inertia::render('Admin/Accounting/InvoiceCreate', [
            'club'    => [
                'id'   => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'members' => $members,
        ]);
    }

    public function storeInvoice(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
        ]);

        $invCount = Invoice::where('club_id', $club->id)->count() + 1;
        $invNum = 'INV-' . date('Y') . '-' . str_pad((string) $invCount, 4, '0', STR_PAD_LEFT);

        $mediaId = null;
        if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            $media = $club->addMedia($request->file('attachment'))
                ->withCustomProperties([
                    'is_accounting_protected' => true,
                    'source' => 'accounting',
                    'invoice_number' => $invNum,
                    'invoice_title' => $validated['title'],
                ])
                ->toMediaCollection('accounting');
            $mediaId = $media->id;
        }

        $invoice = Invoice::create([
            'club_id' => $club->id,
            'user_id' => $validated['user_id'],
            'invoice_number' => $invNum,
            'title' => $validated['title'],
            'amount' => $validated['amount'],
            'status' => 'unpaid',
            'media_id' => $mediaId,
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

        return redirect()
            ->to(route('admin.accounting.index', $clubSlug) . '#sales')
            ->with('success', 'Invoice created and posted to Accounts Receivable.');
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
            'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
        ]);

        $attachmentFile = $request->hasFile('attachment') ? $request->file('attachment') : null;

        $this->accountingService->createVendorBill($club, $validated, $attachmentFile);

        return redirect()->back()->with('success', 'Vendor bill recorded and posted to Accounts Payable.');
    }

    /**
     * Remove bill receipt attachment directly from Accounting.
     */
    public function deleteBillAttachment(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $bill = Bill::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        if ($bill->media_id && $bill->media) {
            $bill->media->forceDelete();
            $bill->update(['media_id' => null]);
        }

        return redirect()->back()->with('success', 'Bill receipt attachment removed.');
    }

    /**
     * Remove invoice document attachment directly from Accounting.
     */
    public function deleteInvoiceAttachment(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $invoice = Invoice::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        if ($invoice->media_id && $invoice->media) {
            $invoice->media->forceDelete();
            $invoice->update(['media_id' => null]);
        }

        return redirect()->back()->with('success', 'Invoice attachment removed.');
    }

    /**
     * Delete vendor bill and its attachment directly from Accounting.
     */
    public function destroyBill(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $bill = Bill::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        if ($bill->status === 'paid') {
            return redirect()->back()->with('error', 'Paid bills cannot be deleted. Void or adjust via journal entry.');
        }

        if ($bill->media_id && $bill->media) {
            $bill->media->forceDelete();
        }

        \App\Models\Accounting\JournalEntry::where('club_id', $club->id)
            ->where('source_type', 'VendorBill')
            ->where('source_id', $bill->id)
            ->delete();

        $bill->delete();

        return redirect()->back()->with('success', "Vendor bill {$bill->bill_number} deleted.");
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

    public function createContact(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        return Inertia::render('Admin/Accounting/ContactForm', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'contact' => null,
        ]);
    }

    public function editMemberContact(string $clubSlug, int $userId): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $user = User::whereHas('clubs', fn ($q) => $q->where('clubs.id', $club->id))
            ->where('users.id', $userId)
            ->firstOrFail();

        // Auto-split the user's name into parts if not already set
        $nameParts = explode(' ', trim($user->name), 3);
        $defaultFirst  = $nameParts[0] ?? '';
        $defaultMiddle = count($nameParts) === 3 ? $nameParts[1] : null;
        $defaultLast   = count($nameParts) >= 2 ? end($nameParts) : null;

        $contact = AccountingContact::firstOrCreate(
            [
                'club_id' => $club->id,
                'user_id' => $user->id,
            ],
            [
                'type'           => 'person',
                'name'           => $user->name,
                'first_name'     => $defaultFirst,
                'middle_names'   => $defaultMiddle,
                'last_name'      => $defaultLast,
                'preferred_name' => null,
                'contact_person' => $user->name,
                'email'          => $user->email,
                'phone'          => $user->phone ?? null,
                'role'           => 'Club Member',
                'is_active'      => true,
                'is_member'      => true,
            ]
        );

        // ── Financial Summary ──────────────────────────────────────────────
        $userInvoices = Invoice::where('club_id', $club->id)
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $amountOwed    = $userInvoices->where('status', 'unpaid')->sum('amount');
        $totalPaid     = $userInvoices->where('status', 'paid')->sum('amount');
        $creditBalance = $userInvoices->where('status', 'refunded')->sum('amount');
        $invoiceCount  = $userInvoices->count();

        $lastPaidInvoice = $userInvoices->where('status', 'paid')->first();
        $lastPaymentDate = $lastPaidInvoice?->paid_at?->format('d M Y')
            ?? $lastPaidInvoice?->created_at?->format('d M Y');
        $lastPaymentAmount = $lastPaidInvoice ? '£' . number_format($lastPaidInvoice->amount, 2) : null;

        $unpaidInvoices = $userInvoices->where('status', 'unpaid')->map(fn ($inv) => [
            'id'               => $inv->id,
            'invoice_number'   => $inv->invoice_number,
            'title'            => $inv->title,
            'amount'           => '£' . number_format($inv->amount, 2),
            'created_at'       => $inv->created_at->format('d M Y'),
        ])->values();

        // Active membership plan
        $membership = \DB::table('memberships')
            ->join('membership_plans', 'memberships.membership_plan_id', '=', 'membership_plans.id')
            ->where('memberships.club_id', $club->id)
            ->where('memberships.user_id', $user->id)
            ->where('memberships.status', 'active')
            ->select('membership_plans.name', 'membership_plans.price', 'membership_plans.billing_period',
                     'memberships.starts_at', 'memberships.ends_at', 'memberships.status')
            ->orderByDesc('memberships.starts_at')
            ->first();

        // Member since (oldest membership record, or club pivot created_at)
        $memberSince = \DB::table('club_user')
            ->where('club_id', $club->id)
            ->where('user_id', $user->id)
            ->value('created_at');

        $memberSummary = [
            'amount_owed'          => $amountOwed,
            'amount_owed_formatted'=> '£' . number_format($amountOwed, 2),
            'credit_balance'       => $creditBalance,
            'credit_balance_formatted' => '£' . number_format($creditBalance, 2),
            'total_paid'           => $totalPaid,
            'total_paid_formatted' => '£' . number_format($totalPaid, 2),
            'invoice_count'        => $invoiceCount,
            'last_payment_date'    => $lastPaymentDate,
            'last_payment_amount'  => $lastPaymentAmount,
            'unpaid_invoices'      => $unpaidInvoices,
            'membership_plan'      => $membership ? $membership->name : null,
            'membership_price'     => $membership ? '£' . number_format($membership->price, 2) : null,
            'membership_period'    => $membership ? $membership->billing_period : null,
            'membership_renews'    => ($membership && $membership->ends_at)
                ? \Carbon\Carbon::parse($membership->ends_at)->format('d M Y') : null,
            'membership_status'    => $membership ? $membership->status : 'no_plan',
            'member_since'         => $memberSince
                ? \Carbon\Carbon::parse($memberSince)->format('d M Y') : null,
        ];
        // ─────────────────────────────────────────────────────────────────

        return Inertia::render('Admin/Accounting/ContactForm', [
            'club' => [
                'id'   => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'contact' => [
                'id'             => $contact->id,
                'type'           => $contact->type,
                'name'           => $contact->name,
                'first_name'     => $contact->first_name,
                'middle_names'   => $contact->middle_names,
                'last_name'      => $contact->last_name,
                'preferred_name' => $contact->preferred_name,
                'contact_person' => $contact->contact_person,
                'email'          => $contact->email,
                'phone'          => $contact->phone,
                'role'           => $contact->role,
                'tax_id'         => $contact->tax_id,
                'address_line_1' => $contact->address_line_1,
                'address_line_2' => $contact->address_line_2,
                'city'           => $contact->city,
                'postcode'       => $contact->postcode,
                'country'        => $contact->country,
                'notes'          => $contact->notes,
                'is_active'      => $contact->is_active,
                'is_member'      => true,
            ],
            'member_summary' => $memberSummary,
        ]);
    }

    public function editContact(string $clubSlug, int $id): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $contact = AccountingContact::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        return Inertia::render('Admin/Accounting/ContactForm', [
            'club' => [
                'id'   => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'contact' => [
                'id'             => $contact->id,
                'type'           => $contact->type,
                'name'           => $contact->name,
                'first_name'     => $contact->first_name,
                'middle_names'   => $contact->middle_names,
                'last_name'      => $contact->last_name,
                'preferred_name' => $contact->preferred_name,
                'contact_person' => $contact->contact_person,
                'email'          => $contact->email,
                'phone'          => $contact->phone,
                'role'           => $contact->role,
                'tax_id'         => $contact->tax_id,
                'address_line_1' => $contact->address_line_1,
                'address_line_2' => $contact->address_line_2,
                'city'           => $contact->city,
                'postcode'       => $contact->postcode,
                'country'        => $contact->country,
                'notes'          => $contact->notes,
                'is_active'      => $contact->is_active,
                'is_member'      => $contact->is_member ?? false,
            ],
        ]);
    }

    public function storeContact(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'name'           => ['nullable', 'required_without:first_name', 'string', 'max:255'],
            'first_name'     => ['nullable', 'required_without:name', 'string', 'max:100'],
            'middle_names'   => ['nullable', 'string', 'max:150'],
            'last_name'      => ['nullable', 'string', 'max:100'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'type'           => ['nullable', 'string', 'in:person,business'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'role'           => ['required', 'string', 'max:100'],
            'tax_id'         => ['nullable', 'string', 'max:50'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city'           => ['nullable', 'string', 'max:100'],
            'postcode'       => ['nullable', 'string', 'max:20'],
            'country'        => ['nullable', 'string', 'max:100'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $firstName = $validated['first_name'] ?? null;
        $lastName = $validated['last_name'] ?? null;
        $middleNames = $validated['middle_names'] ?? null;

        if (!empty($firstName)) {
            $fullName = trim(implode(' ', array_filter([
                $firstName,
                $middleNames,
                $lastName,
            ])));
        } else {
            $fullName = trim($validated['name'] ?? '');
            $parts = explode(' ', $fullName, 2);
            $firstName = $parts[0] ?? $fullName;
            $lastName = $parts[1] ?? null;
        }

        AccountingContact::create([
            'club_id'        => $club->id,
            'type'           => $validated['type'] ?? 'person',
            'name'           => $fullName,
            'first_name'     => $firstName,
            'middle_names'   => $middleNames,
            'last_name'      => $lastName,
            'preferred_name' => $validated['preferred_name'] ?? null,
            'contact_person' => $validated['contact_person'] ?? null,
            'email'          => $validated['email'] ?? null,
            'phone'          => $validated['phone'] ?? null,
            'role'           => $validated['role'],
            'tax_id'         => $validated['tax_id'] ?? null,
            'address_line_1' => $validated['address_line_1'] ?? null,
            'address_line_2' => $validated['address_line_2'] ?? null,
            'city'           => $validated['city'] ?? null,
            'postcode'       => $validated['postcode'] ?? null,
            'country'        => $validated['country'] ?? 'United Kingdom',
            'notes'          => $validated['notes'] ?? null,
            'is_active'      => true,
            'is_member'      => false,
        ]);

        return redirect()->route('admin.accounting.index', ['clubSlug' => $club->slug, '#contacts'])->with('success', 'New contact added to directory.');
    }

    public function updateContact(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $contact = AccountingContact::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'name'           => ['nullable', 'required_without:first_name', 'string', 'max:255'],
            'first_name'     => ['nullable', 'required_without:name', 'string', 'max:100'],
            'middle_names'   => ['nullable', 'string', 'max:150'],
            'last_name'      => ['nullable', 'string', 'max:100'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'type'           => ['nullable', 'string', 'in:person,business'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email'          => ['nullable', 'email', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'role'           => ['required', 'string', 'max:100'],
            'tax_id'         => ['nullable', 'string', 'max:50'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'city'           => ['nullable', 'string', 'max:100'],
            'postcode'       => ['nullable', 'string', 'max:20'],
            'country'        => ['nullable', 'string', 'max:100'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $firstName = $validated['first_name'] ?? null;
        $lastName = $validated['last_name'] ?? null;
        $middleNames = $validated['middle_names'] ?? null;

        if (!empty($firstName)) {
            $fullName = trim(implode(' ', array_filter([
                $firstName,
                $middleNames,
                $lastName,
            ])));
        } else {
            $fullName = trim($validated['name'] ?? $contact->name);
            $parts = explode(' ', $fullName, 2);
            $firstName = $contact->first_name ?: ($parts[0] ?? $fullName);
            $lastName = $contact->last_name ?: ($parts[1] ?? null);
        }

        $updateData = [
            'name'           => $fullName,
            'first_name'     => $firstName,
            'middle_names'   => $middleNames,
            'last_name'      => $lastName,
            'preferred_name' => $validated['preferred_name'] ?? $contact->preferred_name,
            'email'          => $validated['email'] ?? null,
            'phone'          => $validated['phone'] ?? null,
            'role'           => $contact->is_member ? 'Club Member' : $validated['role'],
            'tax_id'         => $validated['tax_id'] ?? null,
            'address_line_1' => $validated['address_line_1'] ?? null,
            'address_line_2' => $validated['address_line_2'] ?? null,
            'city'           => $validated['city'] ?? null,
            'postcode'       => $validated['postcode'] ?? null,
            'country'        => $validated['country'] ?? 'United Kingdom',
            'notes'          => $validated['notes'] ?? null,
        ];

        if (isset($validated['type'])) {
            $updateData['type'] = $validated['type'];
        }
        if (array_key_exists('contact_person', $validated)) {
            $updateData['contact_person'] = $validated['contact_person'];
        }

        $contact->update($updateData);

        return redirect()->route('admin.accounting.index', ['clubSlug' => $club->slug, '#contacts'])->with('success', 'Contact details updated successfully.');
    }

    public function destroyContact(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $contact = AccountingContact::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $contact->delete();

        return redirect()->route('admin.accounting.index', ['clubSlug' => $club->slug, '#contacts'])->with('success', 'Contact removed from directory.');
    }
}
