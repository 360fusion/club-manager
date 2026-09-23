<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankAccount;
use App\Domains\ClubAccounting\Models\BankImport;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Domains\ClubAccounting\Services\BankReconciliationMatcherService;
use App\Domains\ClubAccounting\Services\BankStatementParserService;
use App\Domains\ClubAccounting\Services\GoCardlessSyncService;
use App\Domains\ClubAccounting\Services\PayPalSyncService;
use App\Domains\ClubAccounting\Services\ReliefChestReconciliationService;
use App\Domains\ClubAccounting\Services\StripeSyncService;
use App\Domains\ClubAccounting\Services\SumUpSyncService;
use App\Enums\SignatureRequestStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingContact;
use App\Models\Accounting\AccountingYearAudit;
use App\Models\Accounting\Bill;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\RecurringBillTemplate;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AnnualTreasurerReportService;
use App\Services\BudgetService;
use App\Services\FixedAssetService;
use App\Services\Signatures\SignatureRequestService;
use App\Support\ClubAccess;
use App\Support\Currencies;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AccountingAdminController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService,
        protected FixedAssetService $fixedAssetService,
        protected BudgetService $budgetService,
        protected AnnualTreasurerReportService $annualTreasurerReportService,
        protected SignatureRequestService $signatureRequestService
    ) {}

    public function index(Request $request, string $clubSlug, ?string $tab = null, ?string $report = null): Response
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
                'formatted_balance' => Currencies::format($acc->balance, $club),
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
                'formatted_total' => Currencies::format($entry->total_debit, $club),
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
                'formatted_amount' => Currencies::format($inv->amount, $club),
                'status' => $inv->status,
                'recipient_name' => $inv->user ? $inv->user->name : 'Member',
                'created_at' => $inv->created_at->format('d M Y'),
                'paid_at' => $inv->paid_at ? $inv->paid_at->format('d M Y') : null,
                'reconciled_at' => $inv->reconciled_at ? $inv->reconciled_at->format('d M Y') : null,
                'attachment' => $inv->media ? [
                    'id' => $inv->media->id,
                    'file_name' => $inv->media->file_name,
                    'url' => $this->attachmentUrl($club, $inv->media),
                    'original_url' => $this->attachmentUrl($club, $inv->media),
                    'mime_type' => $inv->media->mime_type,
                    'size' => $inv->media->human_readable_size ?? (round($inv->media->size / 1024, 1).' KB'),
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
                'formatted_amount' => Currencies::format($b->amount, $club),
                'due_date' => $b->due_date->format('d M Y'),
                'status' => $b->status,
                'notes' => $b->notes,
                'paid_at' => $b->paid_at ? $b->paid_at->format('d M Y') : null,
                'reconciled_at' => $b->reconciled_at ? $b->reconciled_at->format('d M Y') : null,
                'attachment' => $b->media ? [
                    'id' => $b->media->id,
                    'file_name' => $b->media->file_name,
                    'url' => $this->attachmentUrl($club, $b->media),
                    'original_url' => $this->attachmentUrl($club, $b->media),
                    'mime_type' => $b->media->mime_type,
                    'size' => $b->media->human_readable_size ?? (round($b->media->size / 1024, 1).' KB'),
                    'is_image' => str_starts_with($b->media->mime_type ?? '', 'image/'),
                ] : null,
            ]);

        $members = User::whereHas('clubs', fn ($q) => $q->where('clubs.id', $club->id))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $contactsQuery = AccountingContact::where('club_id', $club->id)->orderBy('name')->get();

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
            'tax_registration_number' => '',
            'contact_email' => '',
            'phone' => '',
            'address_line_1' => '',
            'address_line_2' => '',
            'city' => '',
            'county' => '',
            'postcode' => '',
            'country' => 'United Kingdom',
            'currency' => $club->currencyCode(),
            'receipt_footer_notes' => '',
            'dues_grace_period_days' => 14,
            'auto_invoice_days_before' => 7,
        ], $club->settings ?? []);

        // Reconciliation Data Assembly
        $matcher = app(BankReconciliationMatcherService::class);
        $unmatchedTxModels = BankTransaction::where('club_id', $club->id)
            ->where('status', 'unmatched')
            ->orderBy('transaction_date', 'asc')
            ->get();

        $unmatchedTransactions = $unmatchedTxModels->map(function ($tx) use ($matcher, $club) {
            $suggestions = $matcher->suggestMatches($tx);

            return [
                'id' => $tx->id,
                'bank_account_id' => $tx->bank_account_id,
                'transaction_date' => $tx->transaction_date->format('d M Y'),
                'raw_description' => $tx->raw_description,
                'reference' => $tx->reference,
                'amount' => (float) $tx->amount,
                'formatted_amount' => Currencies::format((float) $tx->amount, $club),
                'status' => $tx->status->value ?? (string) $tx->status,
                'suggested_matches' => array_map(function ($m) {
                    return [
                        'match_type' => $m['match_type'],
                        'target_id' => $m['target_id'],
                        'target_title' => $m['target_title'],
                        'target_amount' => (float) $m['target_amount'],
                        'confidence_score' => $m['confidence_score'],
                        'confidence_level' => $m['confidence_level'],
                        'match_reason' => $m['match_reason'],
                    ];
                }, $suggestions),
            ];
        });

        $reconciledTransactions = BankTransaction::where('club_id', $club->id)
            ->where('status', 'reconciled')
            ->orderByDesc('updated_at')
            ->take(30)
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'bank_account_id' => $tx->bank_account_id,
                'transaction_date' => $tx->transaction_date->format('d M Y'),
                'raw_description' => $tx->raw_description,
                'reference' => $tx->reference,
                'amount' => (float) $tx->amount,
                'formatted_amount' => Currencies::format((float) $tx->amount, $club),
                'status' => $tx->status->value ?? (string) $tx->status,
                'updated_at' => $tx->updated_at->format('d M Y H:i'),
            ]);

        $bankImports = BankImport::where('club_id', $club->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($imp) => [
                'id' => $imp->id,
                'bank_account_id' => $imp->bank_account_id ?? null,
                'filename' => $imp->filename,
                'account_number' => $imp->account_number,
                'sort_code' => $imp->sort_code,
                'total_lines' => $imp->total_lines,
                'total_amount' => (float) $imp->total_amount,
                'formatted_total' => Currencies::format((float) $imp->total_amount, $club),
                'created_at' => $imp->created_at->format('d M Y H:i'),
            ]);

        $unpaidSubscriptions = MemberSubscription::where('club_id', $club->id)
            ->unpaid()
            ->with('member')
            ->get()
            ->map(fn ($sub) => [
                'id' => $sub->id,
                'invoice_reference' => $sub->invoice_reference,
                'member_name' => $sub->member ? $sub->member->full_name : 'Unknown Member',
                'amount_due' => (float) $sub->balance_due,
                'formatted_amount' => Currencies::format((float) $sub->balance_due, $club),
            ]);

        $allStatementLines = BankTransaction::where('club_id', $club->id)
            ->orderByDesc('transaction_date')
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'bank_account_id' => $tx->bank_account_id,
                'transaction_date' => $tx->transaction_date->format('d M Y'),
                'type' => $tx->amount < 0 ? 'Debit' : 'Credit',
                'raw_description' => $tx->raw_description,
                'reference' => $tx->reference ?: ($tx->amount < 0 ? 'DEBIT' : 'CREDIT'),
                'amount' => (float) $tx->amount,
                'spent' => $tx->amount < 0 ? Currencies::format(abs((float) $tx->amount), $club) : '',
                'received' => $tx->amount > 0 ? Currencies::format((float) $tx->amount, $club) : '',
                'source' => 'Bank Feed',
                'status' => match (strtolower($tx->status->value ?? (string) $tx->status)) {
                    'matched', 'reconciled' => 'Reconciled',
                    'ignored' => 'Deleted',
                    default => 'Unreconciled',
                },
            ]);

        $accountTransactions = BankTransaction::where('club_id', $club->id)
            ->whereIn('status', ['reconciled', 'matched'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($tx) => [
                'id' => 'tx_'.$tx->id,
                'bank_account_id' => $tx->bank_account_id,
                'transaction_date' => $tx->transaction_date->format('d M Y'),
                'type' => $tx->amount < 0 ? 'Spend Money' : 'Receive Money',
                'description' => $tx->raw_description,
                'reference' => $tx->reference ?: 'SYSTEM-REF',
                'amount' => (float) $tx->amount,
                'spent' => $tx->amount < 0 ? Currencies::format(abs((float) $tx->amount), $club) : '',
                'received' => $tx->amount > 0 ? Currencies::format((float) $tx->amount, $club) : '',
                'status' => 'Reconciled',
            ])
            ->concat(
                $unpaidSubscriptions->map(fn ($sub) => [
                    'id' => 'sub_'.$sub['id'],
                    'bank_account_id' => null,
                    'transaction_date' => date('d M Y'),
                    'type' => 'Invoice Payment',
                    'description' => 'Dues: '.$sub['member_name'],
                    'reference' => $sub['invoice_reference'],
                    'amount' => (float) $sub['amount_due'],
                    'spent' => '',
                    'received' => Currencies::format((float) $sub['amount_due'], $club),
                    'status' => 'Unreconciled',
                ])
            )->values();

        $giftAidService = app(ReliefChestReconciliationService::class);
        $giftAidSummary = $giftAidService->getGiftAidSummary($club);
        $reconciledDonationsData = $giftAidService->getReconciledDonations($club);

        $reconciliation = [
            'unmatched_transactions' => $unmatchedTransactions,
            'reconciled_transactions' => $reconciledTransactions,
            'statement_lines' => $allStatementLines,
            'account_transactions' => $accountTransactions,
            'bank_imports' => $bankImports,
            'unpaid_subscriptions' => $unpaidSubscriptions,
            'gift_aid_summary' => $giftAidSummary,
            'reconciled_donations' => $reconciledDonationsData,
        ];

        // Bank Accounts Assembly
        $bankAccountsQuery = BankAccount::where('club_id', $club->id)
            ->with(['account', 'transactions'])
            ->orderBy('is_active', 'desc')
            ->orderBy('id', 'asc')
            ->get();

        $bankAccounts = $bankAccountsQuery->map(fn ($b) => [
            'id' => $b->id,
            'bank_name' => $b->bank_name,
            'account_name' => $b->account_name,
            'account_type' => $b->account_type,
            'formatted_account_type' => $b->formatted_account_type,
            'account_number' => $b->account_number,
            'sort_code' => $b->sort_code,
            'currency' => $b->currency,
            'opening_balance' => (float) $b->opening_balance,
            'statement_balance' => $b->statement_balance,
            'formatted_statement_balance' => Currencies::format($b->statement_balance, $club),
            'ledger_balance' => $b->ledger_balance,
            'formatted_ledger_balance' => Currencies::format($b->ledger_balance, $club),
            'unreconciled_count' => $b->unreconciled_count,
            'is_active' => $b->is_active,
            'account_code' => $b->account?->code ?? '1000',
        ]);

        $fixedAssets = FixedAsset::where('club_id', $club->id)
            ->orderByDesc('purchase_date')
            ->get()
            ->map(fn ($asset) => [
                'id' => $asset->id,
                'name' => $asset->name,
                'category' => $asset->category,
                'purchase_date' => $asset->purchase_date->format('d M Y'),
                'purchase_cost' => (float) $asset->purchase_cost,
                'formatted_purchase_cost' => Currencies::format((float) $asset->purchase_cost, $club),
                'depreciation_method' => $asset->depreciation_method,
                'useful_life_years' => $asset->useful_life_years,
                'salvage_value' => (float) $asset->salvage_value,
                'accumulated_depreciation' => $asset->accumulated_depreciation,
                'formatted_accumulated_depreciation' => Currencies::format($asset->accumulated_depreciation, $club),
                'net_book_value' => $asset->net_book_value,
                'formatted_net_book_value' => Currencies::format($asset->net_book_value, $club),
                'is_disposed' => $asset->isDisposed(),
                'disposal_date' => $asset->disposal_date?->format('d M Y'),
                'disposal_proceeds' => $asset->disposal_proceeds !== null ? (float) $asset->disposal_proceeds : null,
            ]);

        $budgetYear = (int) ($request->query('budget_year') ?: now()->year);
        $budgetVsActual = $this->budgetService->getBudgetVsActual($club, $budgetYear);

        $treasurerReportYear = (int) ($request->query('treasurer_report_year') ?: now()->year);
        $annualTreasurerReport = $this->annualTreasurerReportService->build($club, $treasurerReportYear);

        $recurringBillTemplates = RecurringBillTemplate::where('club_id', $club->id)
            ->orderBy('next_run_date')
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'vendor_name' => $t->vendor_name,
                'category' => $t->category,
                'amount' => (float) $t->amount,
                'formatted_amount' => Currencies::format((float) $t->amount, $club),
                'frequency' => $t->frequency,
                'next_run_date' => $t->next_run_date->format('d M Y'),
                'is_active' => $t->is_active,
                'notes' => $t->notes,
            ]);

        return Inertia::render('Admin/Accounting/Index', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'initialTab' => $tab,
            'initialReport' => $report,
            'accounts' => $accounts,
            'bankAccounts' => $bankAccounts,
            'journalEntries' => $journalEntries,
            'invoices' => $invoices,
            'bills' => $bills,
            'members' => $members,
            'contacts' => $contacts,
            'summary' => $summary,
            'reports' => $reports,
            'settings' => $clubSettings,
            'vatSettings' => $club->vatSettings(),
            'vatLocked' => $club->vatSettingsAreLocked(),
            'reconciliation' => $reconciliation,
            'fixedAssets' => $fixedAssets,
            'budgetVsActual' => $budgetVsActual,
            'annualTreasurerReport' => $annualTreasurerReport,
            'recurringBillTemplates' => $recurringBillTemplates,
        ]);
    }

    public function reconcileBankTransaction(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $validated = $request->validate([
            'transaction_id' => 'required|integer',
            'match_type' => 'required|string|max:50',
            'target_id' => 'required',
            'nominal_code' => 'nullable|string|max:255',
        ]);

        $tx = BankTransaction::where('club_id', $club->id)
            ->findOrFail($validated['transaction_id']);

        $matcher = app(BankReconciliationMatcherService::class);
        $matcher->reconcileTransaction($tx, $validated['match_type'], $validated['target_id'], [
            'nominal_code' => $validated['nominal_code'] ?? 'GENERAL',
        ]);

        return redirect()->back()->with('success', 'Transaction successfully reconciled!');
    }

    public function ignoreBankTransaction(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $validated = $request->validate([
            'transaction_id' => 'required|integer',
        ]);

        $tx = BankTransaction::where('club_id', $club->id)
            ->findOrFail($validated['transaction_id']);

        $matcher = app(BankReconciliationMatcherService::class);
        $matcher->ignoreTransaction($tx);

        return redirect()->back()->with('success', 'Transaction line ignored.');
    }

    public function deleteBankStatementLines(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $ids = (array) $request->input('transaction_ids', []);

        if (! empty($ids)) {
            BankTransaction::where('club_id', $club->id)
                ->whereIn('id', $ids)
                ->update(['status' => BankTransactionStatus::Ignored->value]);
        }

        return redirect()->back()->with('success', count($ids).' statement line(s) deleted.');
    }

    public function restoreBankStatementLines(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $ids = (array) $request->input('transaction_ids', []);

        if (! empty($ids)) {
            BankTransaction::where('club_id', $club->id)
                ->whereIn('id', $ids)
                ->update(['status' => BankTransactionStatus::Unmatched->value]);
        }

        return redirect()->back()->with('success', count($ids).' statement line(s) restored.');
    }

    public function removeAndRedoAccountTransactions(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $rawIds = (array) $request->input('transaction_ids', []);

        $txIds = [];
        foreach ($rawIds as $id) {
            if (is_numeric($id)) {
                $txIds[] = (int) $id;
            } elseif (is_string($id) && str_starts_with($id, 'tx_')) {
                $txIds[] = (int) str_replace('tx_', '', $id);
            }
        }

        if (! empty($txIds)) {
            BankTransaction::where('club_id', $club->id)
                ->whereIn('id', $txIds)
                ->update(['status' => BankTransactionStatus::Unmatched->value]);
        }

        return redirect()->back()->with('success', count($txIds).' transaction(s) un-reconciled and returned to the Reconcile queue.');
    }

    public function importBankStatement(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $request->validate([
            'statement_file' => 'required|file|mimes:csv,txt,ofx,qfx|max:10240',
        ]);

        $parser = app(BankStatementParserService::class);
        $file = $request->file('statement_file');

        $bundle = $parser->parseFile($file->getRealPath(), $file->getClientOriginalName(), $club->id);
        $parser->importParsedBundle($club, $bundle);

        return redirect()->back()->with('success', "Imported {$bundle['new_lines_count']} new statement lines successfully!");
    }

    public function storeAccount(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:accounting_accounts,code,NULL,id,club_id,'.$club->id],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:asset,liability,equity,revenue,expense'],
            'opening_balance' => ['nullable', 'numeric', 'max:99999999.99'],
            'as_of_date' => ['nullable', 'date'],
        ]);

        $account = Account::create([
            'club_id' => $club->id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'currency' => $club->currencyCode(),
            'is_active' => true,
        ]);

        if (! empty($validated['opening_balance']) && (float) $validated['opening_balance'] != 0) {
            $this->accountingService->setOpeningBalance($club, $account, (float) $validated['opening_balance'], $validated['as_of_date'] ?? null);
        }

        return redirect()->back()->with('success', 'Account added to Chart of Accounts with opening balance.');
    }

    public function storeOpeningBalance(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'account_id' => ['required', 'exists:accounting_accounts,id'],
            'opening_balance' => ['required', 'numeric', 'max:99999999.99'],
            'as_of_date' => ['nullable', 'date'],
        ]);

        $account = Account::where('club_id', $club->id)->where('id', $validated['account_id'])->firstOrFail();

        $this->accountingService->setOpeningBalance($club, $account, (float) $validated['opening_balance'], $validated['as_of_date'] ?? null);

        return redirect()->back()->with('success', "Opening balance updated for {$account->code} - {$account->name}.");
    }

    public function updateVatSettings(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'scheme' => ['required', 'string', 'in:not_registered,standard,flat_rate,cash_accounting,annual_accounting'],
            'vat_number' => ['nullable', 'string', 'max:20'],
            'flat_rate_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'registered_from' => ['nullable', 'date'],
        ]);

        $current = $club->vatSettings();
        $isChangingLockedSetting = $validated['scheme'] !== $current['scheme'] || $validated['enabled'] !== $current['enabled'];

        if ($isChangingLockedSetting && $club->vatSettingsAreLocked() && ! $request->user()->is_super_admin) {
            return redirect()->back()->withErrors([
                'scheme' => 'This club already has VAT-inclusive transactions on its books, so the VAT scheme cannot be changed. Contact support if this needs to change.',
            ]);
        }

        $club->update([
            'settings' => array_merge($club->settings ?? [], ['vat' => $validated]),
        ]);

        return redirect()->back()->with('success', 'VAT settings updated.');
    }

    public function storeFixedAsset(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['required', 'date'],
            'purchase_cost' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'bill_id' => ['nullable', 'exists:accounting_bills,id'],
            'depreciation_method' => ['required', 'string', 'in:straight_line,reducing_balance,none'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:100'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if (! empty($validated['bill_id'])) {
            $bill = Bill::where('club_id', $club->id)->where('id', $validated['bill_id'])->first();
            if (! $bill) {
                return redirect()->back()->withErrors(['bill_id' => 'That bill does not belong to this club.']);
            }
        }

        $asset = $this->fixedAssetService->registerAsset($club, $validated);

        return redirect()->back()->with('success', "Fixed asset '{$asset->name}' registered and capitalised.");
    }

    public function disposeFixedAsset(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');
        $asset = FixedAsset::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'disposal_date' => ['required', 'date'],
            'disposal_proceeds' => ['required', 'numeric', 'min:0'],
        ]);

        $this->fixedAssetService->disposeAsset($asset, $validated['disposal_date'], (float) $validated['disposal_proceeds']);

        return redirect()->back()->with('success', "Fixed asset '{$asset->name}' disposed of.");
    }

    public function runFixedAssetDepreciation(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');

        $validated = $request->validate([
            'period' => ['required', 'string', 'max:20'],
        ]);

        $result = $this->fixedAssetService->runDepreciation($club, $validated['period']);

        return redirect()->back()->with('success', "Depreciation run for {$validated['period']}: {$result['created_count']} asset(s) depreciated, {$result['skipped_count']} skipped.");
    }

    public function updateBudget(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');

        $validated = $request->validate([
            'financial_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'lines' => ['required', 'array'],
            'lines.*.account_id' => ['required', 'exists:accounting_accounts,id'],
            'lines.*.budgeted_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ]);

        $this->budgetService->setBudgetLines($club, (int) $validated['financial_year'], $validated['lines']);

        return redirect()->back()->with('success', "Budget for {$validated['financial_year']} saved.");
    }

    public function closeFinancialYear(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');

        $validated = $request->validate([
            'financial_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->accountingService->closeFinancialYear($club, (int) $validated['financial_year'], $request->user(), $validated['notes'] ?? null);

        return redirect()->back()->with('success', "Financial year {$validated['financial_year']} closed.");
    }

    public function reopenFinancialYear(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        if (! $request->user()->is_super_admin) {
            abort(403, 'Only a platform super admin can reopen a closed financial year.');
        }

        $validated = $request->validate([
            'financial_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $this->accountingService->reopenFinancialYear($club, (int) $validated['financial_year'], $request->user());

        return redirect()->back()->with('success', "Financial year {$validated['financial_year']} reopened.");
    }

    public function signOffYearAudit(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');

        $validated = $request->validate([
            'financial_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'auditor_one_user_id' => ['required', 'different:auditor_two_user_id', 'exists:users,id'],
            'auditor_two_user_id' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $auditorOne = User::whereHas('clubs', fn ($q) => $q->where('clubs.id', $club->id))->findOrFail($validated['auditor_one_user_id']);
        $auditorTwo = User::whereHas('clubs', fn ($q) => $q->where('clubs.id', $club->id))->findOrFail($validated['auditor_two_user_id']);

        $audit = $this->accountingService->requestYearAudit($club, (int) $validated['financial_year'], $auditorOne, $auditorTwo, $validated['notes'] ?? null);

        $this->signatureRequestService->requestIfOpen($audit, 'year_audit_auditor_one', $auditorOne, $auditorOne->name, $auditorOne->email, $request->user());
        $this->signatureRequestService->requestIfOpen($audit, 'year_audit_auditor_two', $auditorTwo, $auditorTwo->name, $auditorTwo->email, $request->user());

        return redirect()->back()->with('success', "Signature requests sent to {$auditorOne->name} and {$auditorTwo->name} for {$validated['financial_year']}.");
    }

    public function resendYearAuditSignature(Request $request, string $clubSlug, string $purpose): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');

        abort_unless(in_array($purpose, ['year_audit_auditor_one', 'year_audit_auditor_two'], true), 404);

        $validated = $request->validate(['financial_year' => ['required', 'integer', 'min:2000', 'max:2100']]);

        $audit = AccountingYearAudit::where('club_id', $club->id)->where('financial_year', $validated['financial_year'])->firstOrFail();

        $pending = $this->signatureRequestService->forSignable($audit)
            ->where('purpose', $purpose)
            ->where('status', SignatureRequestStatus::Pending)
            ->first();

        if ($pending) {
            $this->signatureRequestService->resend($pending);
        }

        return redirect()->back()->with('success', 'Signature request resent.');
    }

    public function cancelYearAuditSignature(Request $request, string $clubSlug, string $purpose): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');

        abort_unless(in_array($purpose, ['year_audit_auditor_one', 'year_audit_auditor_two'], true), 404);

        $validated = $request->validate(['financial_year' => ['required', 'integer', 'min:2000', 'max:2100']]);

        $audit = AccountingYearAudit::where('club_id', $club->id)->where('financial_year', $validated['financial_year'])->firstOrFail();

        $pending = $this->signatureRequestService->forSignable($audit)
            ->where('purpose', $purpose)
            ->where('status', SignatureRequestStatus::Pending)
            ->first();

        if ($pending) {
            $this->signatureRequestService->cancel($pending);
        }

        return redirect()->back()->with('success', 'Signature request cancelled.');
    }

    public function storeRecurringBillTemplate(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');

        $validated = $request->validate([
            'vendor_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'frequency' => ['required', 'string', 'in:monthly,quarterly,annually'],
            'next_run_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        RecurringBillTemplate::create([
            'club_id' => $club->id,
            'vendor_name' => $validated['vendor_name'],
            'category' => $validated['category'] ?? 'General Expense',
            'amount' => $validated['amount'],
            'frequency' => $validated['frequency'],
            'next_run_date' => $validated['next_run_date'],
            'notes' => $validated['notes'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Recurring bill for {$validated['vendor_name']} scheduled.");
    }

    public function toggleRecurringBillTemplate(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');
        $template = RecurringBillTemplate::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $template->update(['is_active' => ! $template->is_active]);

        return redirect()->back()->with('success', $template->is_active ? 'Recurring bill resumed.' : 'Recurring bill paused.');
    }

    public function destroyRecurringBillTemplate(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');
        RecurringBillTemplate::where('club_id', $club->id)->where('id', $id)->firstOrFail()->delete();

        return redirect()->back()->with('success', 'Recurring bill schedule removed.');
    }

    public function createJournal(string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $accounts = Account::where('club_id', $club->id)
            ->orderBy('code')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'type' => $a->type,
            ]);

        return Inertia::render('Admin/Accounting/JournalCreate', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'accounts' => $accounts,
        ]);
    }

    public function storeJournalEntry(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'entry_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:2', 'max:100'],
            'items.*.account_id' => ['required', 'exists:accounting_accounts,id'],
            'items.*.debit' => ['numeric', 'min:0'],
            'items.*.credit' => ['numeric', 'min:0'],
            'items.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        $this->accountingService->postJournalEntry($club, $validated);

        return redirect()->route('admin.accounting.index', ['clubSlug' => $club->slug, 'tab' => 'accounting'])
            ->with('success', 'Double-entry journal entry posted successfully.');
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
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ]);

        return Inertia::render('Admin/Accounting/InvoiceCreate', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'members' => $members,
        ]);
    }

    public function storeInvoice(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $isDraft = $request->boolean('is_draft');

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'title' => [$isDraft ? 'nullable' : 'required', 'string', 'max:255'],
            'amount' => [$isDraft ? 'nullable' : 'required', 'numeric', 'min:0'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
            'is_draft' => ['nullable', 'boolean'],
        ]);

        $status = $isDraft ? 'draft' : 'unpaid';
        $title = ! empty($validated['title']) ? $validated['title'] : 'Draft Invoice';
        $amount = isset($validated['amount']) ? (float) $validated['amount'] : 0.00;
        $vat = $this->accountingService->resolveVatFields($club, $amount, $request->float('vat_rate') ?: null);

        $invCount = Invoice::where('club_id', $club->id)->count() + 1;
        $invNum = 'INV-'.date('Y').'-'.str_pad((string) $invCount, 4, '0', STR_PAD_LEFT);

        $mediaId = null;
        if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            $media = $club->addMedia($request->file('attachment'))
                ->withCustomProperties([
                    'is_accounting_protected' => true,
                    'source' => 'accounting',
                    'invoice_number' => $invNum,
                    'invoice_title' => $title,
                ])
                ->toMediaCollection('accounting', 'local');
            $mediaId = $media->id;
        }

        $invoice = Invoice::create([
            'club_id' => $club->id,
            'user_id' => $validated['user_id'],
            'invoice_number' => $invNum,
            'title' => $title,
            'amount' => $vat['amount'],
            'net_amount' => $vat['net_amount'],
            'vat_rate' => $vat['vat_rate'],
            'vat_amount' => $vat['vat_amount'],
            'status' => $status,
            'media_id' => $mediaId,
        ]);

        if ($status !== 'draft') {
            $this->accountingService->postMemberInvoiceIssuedJournal($club, $invoice);
            $msg = 'Invoice created and posted to Accounts Receivable.';
        } else {
            $msg = 'Invoice saved as draft.';
        }

        return redirect()
            ->route('admin.accounting.index', ['clubSlug' => $clubSlug, 'tab' => 'sales'])
            ->with('success', $msg);
    }

    public function publishInvoice(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $invoice = Invoice::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        if ($invoice->status === 'draft') {
            $invoice->update(['status' => 'unpaid']);
            $this->accountingService->postMemberInvoiceIssuedJournal($club, $invoice);

            return redirect()->back()->with('success', "Invoice {$invoice->invoice_number} published and posted to Accounts Receivable.");
        }

        return redirect()->back();
    }

    public function createBill(Request $request, string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        return Inertia::render('Admin/Accounting/BillCreate', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'initialVendor' => $request->query('vendor', ''),
        ]);
    }

    public function storeBill(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'vendor_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
            'is_draft' => ['nullable', 'boolean'],
        ]);

        $attachmentFile = $request->hasFile('attachment') ? $request->file('attachment') : null;

        $this->accountingService->createVendorBill($club, $validated, $attachmentFile);

        $msg = ! empty($validated['is_draft'])
            ? 'Vendor bill saved as draft.'
            : 'Vendor bill recorded and posted to Accounts Payable.';

        return redirect()->route('admin.accounting.index', ['clubSlug' => $club->slug, 'tab' => 'purchases'])
            ->with('success', $msg);
    }

    public function publishBill(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $bill = Bill::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        if ($bill->status === 'draft') {
            \DB::transaction(function () use ($club, $bill) {
                $bill->update(['status' => 'unpaid']);
                $this->accountingService->postVendorBillIssuedJournal($club, $bill);
            });

            return redirect()->back()->with('success', "Vendor bill {$bill->bill_number} published and posted to Accounts Payable.");
        }

        return redirect()->back();
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

        JournalEntry::where('club_id', $club->id)
            ->where('source_type', 'VendorBill')
            ->where('source_id', $bill->id)
            ->update(['status' => 'void']);

        $this->accountingService->log($club, 'bill', $bill->id, 'voided', "Vendor bill {$bill->bill_number} ({$bill->vendor_name}) deleted.");

        $bill->delete();

        return redirect()->back()->with('success', "Vendor bill {$bill->bill_number} deleted.");
    }

    public function updateInvoice(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $invoice = Invoice::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'status' => ['required', 'string', Rule::in($invoice->status === 'paid' ? ['draft', 'unpaid', 'paid'] : ['draft', 'unpaid'])],
            'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
        ]);

        if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            if ($invoice->media_id && $invoice->media) {
                $invoice->media->forceDelete();
            }
            $media = $club->addMedia($request->file('attachment'))
                ->withCustomProperties([
                    'is_accounting_protected' => true,
                    'source' => 'accounting',
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_title' => $validated['title'],
                ])
                ->toMediaCollection('accounting', 'local');
            $validated['media_id'] = $media->id;
        }

        $wasDraft = $invoice->status === 'draft';
        $before = $invoice->only(['title', 'amount', 'status']);
        $vat = $this->accountingService->resolveVatFields($club, (float) $validated['amount'], $invoice->vat_rate !== null ? (float) $invoice->vat_rate : null);
        $invoice->update([
            'user_id' => $validated['user_id'],
            'title' => $validated['title'],
            'amount' => $vat['amount'],
            'net_amount' => $vat['net_amount'],
            'vat_rate' => $vat['vat_rate'],
            'vat_amount' => $vat['vat_amount'],
            'status' => $validated['status'],
            'media_id' => $validated['media_id'] ?? $invoice->media_id,
        ]);

        $this->accountingService->log($club, 'invoice', $invoice->id, 'updated', "Invoice {$invoice->invoice_number} updated.", before: $before, after: $invoice->only(['title', 'amount', 'status']));

        if ($wasDraft && $invoice->status === 'unpaid') {
            $this->accountingService->postMemberInvoiceIssuedJournal($club, $invoice);
        }

        return redirect()->back()->with('success', "Invoice {$invoice->invoice_number} updated successfully.");
    }

    public function destroyInvoice(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $invoice = Invoice::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        if ($invoice->status === 'paid') {
            return redirect()->back()->with('error', 'Paid invoices cannot be deleted.');
        }

        if ($invoice->media_id && $invoice->media) {
            $invoice->media->forceDelete();
        }

        JournalEntry::where('club_id', $club->id)
            ->where('source_type', 'Invoice')
            ->where('source_id', $invoice->id)
            ->update(['status' => 'void']);

        $this->accountingService->log($club, 'invoice', $invoice->id, 'voided', "Invoice {$invoice->invoice_number} ({$invoice->title}) deleted.");

        $invoice->delete();

        return redirect()->back()->with('success', "Invoice {$invoice->invoice_number} deleted.");
    }

    public function updateBill(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $bill = Bill::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'vendor_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'due_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'string', Rule::in($bill->status === 'paid' ? ['draft', 'unpaid', 'paid'] : ['draft', 'unpaid'])],
            'attachment' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg,webp', 'max:10240'],
        ]);

        if ($request->hasFile('attachment') && $request->file('attachment')->isValid()) {
            if ($bill->media_id && $bill->media) {
                $bill->media->forceDelete();
            }
            $media = $club->addMedia($request->file('attachment'))
                ->withCustomProperties([
                    'is_accounting_protected' => true,
                    'source' => 'accounting',
                    'bill_number' => $bill->bill_number,
                    'vendor_name' => $validated['vendor_name'],
                ])
                ->toMediaCollection('accounting', 'local');
            $validated['media_id'] = $media->id;
        }

        $wasDraft = $bill->status === 'draft';
        $before = $bill->only(['vendor_name', 'amount', 'status']);
        $vat = $this->accountingService->resolveVatFields($club, (float) $validated['amount'], $bill->vat_rate !== null ? (float) $bill->vat_rate : null);
        $bill->update([
            'vendor_name' => $validated['vendor_name'],
            'category' => $validated['category'],
            'amount' => $vat['amount'],
            'net_amount' => $vat['net_amount'],
            'vat_rate' => $vat['vat_rate'],
            'vat_amount' => $vat['vat_amount'],
            'due_date' => $validated['due_date'],
            'notes' => $validated['notes'] ?? null,
            'status' => $validated['status'],
            'media_id' => $validated['media_id'] ?? $bill->media_id,
        ]);

        $this->accountingService->log($club, 'bill', $bill->id, 'updated', "Vendor bill {$bill->bill_number} updated.", before: $before, after: $bill->only(['vendor_name', 'amount', 'status']));

        if ($wasDraft && $bill->status === 'unpaid') {
            $this->accountingService->postVendorBillIssuedJournal($club, $bill);
        }

        return redirect()->back()->with('success', "Vendor bill {$bill->bill_number} updated successfully.");
    }

    public function editInvoice(string $clubSlug, int $id): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $invoice = Invoice::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $members = \DB::table('club_user')
            ->join('users', 'club_user.user_id', '=', 'users.id')
            ->where('club_user.club_id', $club->id)
            ->select('users.id', 'users.name', 'users.email')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ]);

        return Inertia::render('Admin/Accounting/InvoiceEdit', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'invoice' => [
                'id' => $invoice->id,
                'user_id' => $invoice->user_id,
                'title' => $invoice->title,
                'amount' => (float) $invoice->amount,
                'status' => $invoice->status,
                'invoice_number' => $invoice->invoice_number,
                'attachment_url' => $invoice->media_id && $invoice->media ? $this->attachmentUrl($club, $invoice->media) : null,
                'created_at' => $invoice->created_at->format('d M Y'),
            ],
            'members' => $members,
        ]);
    }

    public function editBill(string $clubSlug, int $id): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $bill = Bill::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        return Inertia::render('Admin/Accounting/BillEdit', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'bill' => [
                'id' => $bill->id,
                'vendor_name' => $bill->vendor_name,
                'category' => $bill->category,
                'amount' => (float) $bill->amount,
                'due_date' => $bill->due_date ? $bill->due_date->format('Y-m-d') : '',
                'notes' => $bill->notes ?? '',
                'status' => $bill->status,
                'bill_number' => $bill->bill_number,
                'attachment_url' => $bill->media_id && $bill->media ? $this->attachmentUrl($club, $bill->media) : null,
                'created_at' => $bill->created_at->format('d M Y'),
            ],
        ]);
    }

    public function editJournalEntry(string $clubSlug, int $id): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
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

        return Inertia::render('Admin/Accounting/JournalEdit', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'journalEntry' => [
                'id' => $entry->id,
                'reference_number' => $entry->reference_number,
                'entry_date' => $entry->entry_date ? $entry->entry_date->format('Y-m-d') : date('Y-m-d'),
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

    public function updateJournalEntry(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'entry_date' => ['required', 'date'],
            'items' => ['required', 'array', 'min:2', 'max:100'],
            'items.*.account_id' => ['required', 'exists:accounting_accounts,id'],
            'items.*.debit' => ['numeric', 'min:0'],
            'items.*.credit' => ['numeric', 'min:0'],
            'items.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->accountingService->updateJournalEntry($club, $id, $validated);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.accounting.index', ['clubSlug' => $clubSlug, 'tab' => 'general_ledger'])
            ->with('success', 'Journal entry updated successfully.');
    }

    public function markBillPaid(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');
        $bill = Bill::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $this->accountingService->markBillAsPaid($bill, $request->user());

        return redirect()->back()->with('success', "Vendor bill {$bill->bill_number} marked as paid.");
    }

    public function markInvoicePaid(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');
        $invoice = Invoice::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $this->accountingService->markInvoiceAsPaid($invoice, $request->user());

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
        $defaultFirst = $nameParts[0] ?? '';
        $defaultMiddle = count($nameParts) === 3 ? $nameParts[1] : null;
        $defaultLast = count($nameParts) >= 2 ? end($nameParts) : null;

        $contact = AccountingContact::firstOrCreate(
            [
                'club_id' => $club->id,
                'user_id' => $user->id,
            ],
            [
                'type' => 'person',
                'name' => $user->name,
                'first_name' => $defaultFirst,
                'middle_names' => $defaultMiddle,
                'last_name' => $defaultLast,
                'preferred_name' => null,
                'contact_person' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'role' => 'Club Member',
                'is_active' => true,
                'is_member' => true,
            ]
        );

        // ── Financial Summary ──────────────────────────────────────────────
        $userInvoices = Invoice::where('club_id', $club->id)
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $amountOwed = $userInvoices->where('status', 'unpaid')->sum('amount');
        $totalPaid = $userInvoices->where('status', 'paid')->sum('amount');
        $creditBalance = $userInvoices->where('status', 'refunded')->sum('amount');
        $invoiceCount = $userInvoices->count();

        $lastPaidInvoice = $userInvoices->where('status', 'paid')->first();
        $lastPaymentDate = $lastPaidInvoice?->paid_at?->format('d M Y')
            ?? $lastPaidInvoice?->created_at?->format('d M Y');
        $lastPaymentAmount = $lastPaidInvoice ? Currencies::format($lastPaidInvoice->amount, $club) : null;

        $unpaidInvoices = $userInvoices->where('status', 'unpaid')->map(fn ($inv) => [
            'id' => $inv->id,
            'invoice_number' => $inv->invoice_number,
            'title' => $inv->title,
            'amount' => Currencies::format($inv->amount, $club),
            'created_at' => $inv->created_at->format('d M Y'),
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
            'amount_owed' => $amountOwed,
            'amount_owed_formatted' => Currencies::format($amountOwed, $club),
            'credit_balance' => $creditBalance,
            'credit_balance_formatted' => Currencies::format($creditBalance, $club),
            'total_paid' => $totalPaid,
            'total_paid_formatted' => Currencies::format($totalPaid, $club),
            'invoice_count' => $invoiceCount,
            'last_payment_date' => $lastPaymentDate,
            'last_payment_amount' => $lastPaymentAmount,
            'unpaid_invoices' => $unpaidInvoices,
            'membership_plan' => $membership ? $membership->name : null,
            'membership_price' => $membership ? Currencies::format($membership->price, $club) : null,
            'membership_period' => $membership ? $membership->billing_period : null,
            'membership_renews' => ($membership && $membership->ends_at)
                ? Carbon::parse($membership->ends_at)->format('d M Y') : null,
            'membership_status' => $membership ? $membership->status : 'no_plan',
            'member_since' => $memberSince
                ? Carbon::parse($memberSince)->format('d M Y') : null,
        ];
        // ─────────────────────────────────────────────────────────────────

        return Inertia::render('Admin/Accounting/ContactForm', [
            'club' => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'contact' => [
                'id' => $contact->id,
                'type' => $contact->type,
                'name' => $contact->name,
                'first_name' => $contact->first_name,
                'middle_names' => $contact->middle_names,
                'last_name' => $contact->last_name,
                'preferred_name' => $contact->preferred_name,
                'contact_person' => $contact->contact_person,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'role' => $contact->role,
                'tax_id' => $contact->tax_id,
                'address_line_1' => $contact->address_line_1,
                'address_line_2' => $contact->address_line_2,
                'city' => $contact->city,
                'postcode' => $contact->postcode,
                'country' => $contact->country,
                'notes' => $contact->notes,
                'is_active' => $contact->is_active,
                'is_member' => true,
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
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
            ],
            'contact' => [
                'id' => $contact->id,
                'type' => $contact->type,
                'name' => $contact->name,
                'first_name' => $contact->first_name,
                'middle_names' => $contact->middle_names,
                'last_name' => $contact->last_name,
                'preferred_name' => $contact->preferred_name,
                'contact_person' => $contact->contact_person,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'role' => $contact->role,
                'tax_id' => $contact->tax_id,
                'address_line_1' => $contact->address_line_1,
                'address_line_2' => $contact->address_line_2,
                'city' => $contact->city,
                'postcode' => $contact->postcode,
                'country' => $contact->country,
                'notes' => $contact->notes,
                'is_active' => $contact->is_active,
                'is_member' => $contact->is_member ?? false,
            ],
        ]);
    }

    public function storeContact(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();

        $validated = $request->validate([
            'name' => ['nullable', 'required_without:first_name', 'string', 'max:255'],
            'first_name' => ['nullable', 'required_without:name', 'string', 'max:100'],
            'middle_names' => ['nullable', 'string', 'max:150'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:person,business'],
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

        $firstName = $validated['first_name'] ?? null;
        $lastName = $validated['last_name'] ?? null;
        $middleNames = $validated['middle_names'] ?? null;

        if (! empty($firstName)) {
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
            'club_id' => $club->id,
            'type' => $validated['type'] ?? 'person',
            'name' => $fullName,
            'first_name' => $firstName,
            'middle_names' => $middleNames,
            'last_name' => $lastName,
            'preferred_name' => $validated['preferred_name'] ?? null,
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
            'is_member' => false,
        ]);

        return redirect()->route('admin.accounting.index', ['clubSlug' => $club->slug, 'tab' => 'contacts'])->with('success', 'New contact added to directory.');
    }

    public function updateContact(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $contact = AccountingContact::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $validated = $request->validate([
            'name' => ['nullable', 'required_without:first_name', 'string', 'max:255'],
            'first_name' => ['nullable', 'required_without:name', 'string', 'max:100'],
            'middle_names' => ['nullable', 'string', 'max:150'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'preferred_name' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'in:person,business'],
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

        $firstName = $validated['first_name'] ?? null;
        $lastName = $validated['last_name'] ?? null;
        $middleNames = $validated['middle_names'] ?? null;

        if (! empty($firstName)) {
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
            'name' => $fullName,
            'first_name' => $firstName,
            'middle_names' => $middleNames,
            'last_name' => $lastName,
            'preferred_name' => $validated['preferred_name'] ?? $contact->preferred_name,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'role' => $contact->is_member ? 'Club Member' : $validated['role'],
            'tax_id' => $validated['tax_id'] ?? null,
            'address_line_1' => $validated['address_line_1'] ?? null,
            'address_line_2' => $validated['address_line_2'] ?? null,
            'city' => $validated['city'] ?? null,
            'postcode' => $validated['postcode'] ?? null,
            'country' => $validated['country'] ?? 'United Kingdom',
            'notes' => $validated['notes'] ?? null,
        ];

        if (isset($validated['type'])) {
            $updateData['type'] = $validated['type'];
        }
        if (array_key_exists('contact_person', $validated)) {
            $updateData['contact_person'] = $validated['contact_person'];
        }

        $contact->update($updateData);

        return redirect()->route('admin.accounting.index', ['clubSlug' => $club->slug, 'tab' => 'contacts'])->with('success', 'Contact details updated successfully.');
    }

    public function destroyContact(string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $contact = AccountingContact::where('club_id', $club->id)->where('id', $id)->firstOrFail();

        $contact->delete();

        return redirect()->route('admin.accounting.index', ['clubSlug' => $club->slug, 'tab' => 'contacts'])->with('success', 'Contact removed from directory.');
    }

    public function storeBankAccount(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $validated = $request->validate([
            'bank_name' => 'required|string|max:100',
            'account_name' => 'required|string|max:150',
            'account_type' => 'required|string|in:current,savings,credit_card,payment_gateway,merchant,cash',
            'account_number' => 'nullable|string|max:50',
            'sort_code' => 'nullable|string|max:20',
            'currency' => $this->clubCurrencyRule($clubSlug),
            'opening_balance' => 'required|numeric|max:99999999.99',
        ]);

        $existingCodes = Account::where('club_id', $club->id)
            ->where('type', 'asset')
            ->where('code', 'like', '10%')
            ->pluck('code')
            ->toArray();

        $codeNum = 1010;
        while (in_array((string) $codeNum, $existingCodes)) {
            $codeNum += 10;
        }

        $ledgerAcc = Account::create([
            'club_id' => $club->id,
            'code' => (string) $codeNum,
            'name' => "{$validated['bank_name']} — {$validated['account_name']}",
            'type' => 'asset',
            'currency' => strtoupper($validated['currency']),
            'is_active' => true,
        ]);

        BankAccount::create([
            'club_id' => $club->id,
            'account_id' => $ledgerAcc->id,
            'bank_name' => $validated['bank_name'],
            'account_name' => $validated['account_name'],
            'account_type' => $validated['account_type'],
            'account_number' => $validated['account_number'] ?? null,
            'sort_code' => $validated['sort_code'] ?? null,
            'currency' => strtoupper($validated['currency']),
            'opening_balance' => $validated['opening_balance'],
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Bank account '{$validated['bank_name']} — {$validated['account_name']}' created and linked to Nominal Code {$ledgerAcc->code}!");
    }

    public function toggleBankAccount(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $acc = BankAccount::where('club_id', $club->id)->findOrFail($id);
        $acc->update(['is_active' => ! $acc->is_active]);

        return redirect()->back()->with('success', 'Bank account status updated.');
    }

    public function connectPayPal(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $validated = $request->validate([
            'account_name' => 'required|string|max:150',
            'paypal_client_id' => 'required|string',
            'paypal_client_secret' => 'required|string|max:500',
            'paypal_environment' => 'required|string|in:live,sandbox',
            'currency' => $this->clubCurrencyRule($clubSlug),
            'opening_balance' => 'required|numeric|max:99999999.99',
        ]);

        $syncService = new PayPalSyncService;
        $testResult = $syncService->testConnection(
            $validated['paypal_client_id'],
            $validated['paypal_client_secret'],
            $validated['paypal_environment']
        );

        if (! $testResult['success']) {
            return redirect()->back()->with('error', "PayPal Connection Failed: {$testResult['message']}");
        }

        $existingCodes = Account::where('club_id', $club->id)
            ->where('type', 'asset')
            ->where('code', 'like', '10%')
            ->pluck('code')
            ->toArray();

        $codeNum = 1010;
        while (in_array((string) $codeNum, $existingCodes)) {
            $codeNum += 10;
        }

        $ledgerAcc = Account::create([
            'club_id' => $club->id,
            'code' => (string) $codeNum,
            'name' => "PayPal — {$validated['account_name']}",
            'type' => 'asset',
            'currency' => strtoupper($validated['currency']),
            'is_active' => true,
        ]);

        $bankAccount = BankAccount::create([
            'club_id' => $club->id,
            'account_id' => $ledgerAcc->id,
            'bank_name' => 'PayPal',
            'account_name' => $validated['account_name'],
            'account_type' => 'payment_gateway',
            'currency' => strtoupper($validated['currency']),
            'opening_balance' => $validated['opening_balance'],
            'paypal_client_id' => $validated['paypal_client_id'],
            'paypal_client_secret' => $validated['paypal_client_secret'],
            'paypal_environment' => $validated['paypal_environment'],
            'paypal_connected_at' => now(),
            'sync_status' => 'connected',
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "PayPal Account '{$validated['account_name']}' connected & linked to Nominal Code {$ledgerAcc->code}!");
    }

    public function testPayPalConnection(Request $request, string $clubSlug, int $id)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $bankAccount = BankAccount::where('club_id', $club->id)->findOrFail($id);

        if (! $bankAccount->paypal_client_id || ! $bankAccount->paypal_client_secret) {
            return response()->json(['success' => false, 'message' => 'PayPal credentials missing.'], 422);
        }

        $syncService = new PayPalSyncService;
        $res = $syncService->testConnection(
            $bankAccount->paypal_client_id,
            $bankAccount->paypal_client_secret,
            $bankAccount->paypal_environment ?? 'live'
        );

        return response()->json($res);
    }

    public function syncPayPalTransactions(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $bankAccount = BankAccount::where('club_id', $club->id)->findOrFail($id);

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        try {
            $syncService = new PayPalSyncService;
            $result = $syncService->syncTransactions(
                $bankAccount,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            $skippedMsg = isset($result['skipped_count']) && $result['skipped_count'] > 0
                ? " ({$result['skipped_count']} duplicates safely ignored)"
                : '';

            return redirect()->back()->with('success', "PayPal API Sync Complete: {$result['synced_count']} new transactions imported{$skippedMsg}.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "PayPal Sync Error: {$e->getMessage()}");
        }
    }

    public function connectStripe(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $validated = $request->validate([
            'account_name' => 'required|string|max:150',
            'stripe_secret_key' => 'required|string|max:500',
            'currency' => $this->clubCurrencyRule($clubSlug),
            'opening_balance' => 'required|numeric|max:99999999.99',
        ]);

        $syncService = new StripeSyncService;
        $testResult = $syncService->testConnection($validated['stripe_secret_key']);

        if (! $testResult['success']) {
            return redirect()->back()->with('error', "Stripe Connection Failed: {$testResult['message']}");
        }

        $existingCodes = Account::where('club_id', $club->id)
            ->where('type', 'asset')
            ->where('code', 'like', '10%')
            ->pluck('code')
            ->toArray();

        $codeNum = 1010;
        while (in_array((string) $codeNum, $existingCodes)) {
            $codeNum += 10;
        }

        $ledgerAcc = Account::create([
            'club_id' => $club->id,
            'code' => (string) $codeNum,
            'name' => "Stripe — {$validated['account_name']}",
            'type' => 'asset',
            'currency' => strtoupper($validated['currency']),
            'is_active' => true,
        ]);

        BankAccount::create([
            'club_id' => $club->id,
            'account_id' => $ledgerAcc->id,
            'bank_name' => 'Stripe',
            'account_name' => $validated['account_name'],
            'account_type' => 'payment_gateway',
            'currency' => strtoupper($validated['currency']),
            'opening_balance' => $validated['opening_balance'],
            'stripe_secret_key' => $validated['stripe_secret_key'],
            'stripe_connected_at' => now(),
            'sync_status' => 'connected',
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "Stripe Account '{$validated['account_name']}' connected & linked to Nominal Code {$ledgerAcc->code}!");
    }

    public function syncStripeTransactions(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $bankAccount = BankAccount::where('club_id', $club->id)->findOrFail($id);

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        try {
            $syncService = new StripeSyncService;
            $result = $syncService->syncTransactions(
                $bankAccount,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            $skippedMsg = isset($result['skipped_count']) && $result['skipped_count'] > 0
                ? " ({$result['skipped_count']} duplicates safely ignored)"
                : '';

            return redirect()->back()->with('success', "Stripe API Sync Complete: {$result['synced_count']} new transactions imported{$skippedMsg}.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "Stripe Sync Error: {$e->getMessage()}");
        }
    }

    public function connectSumUp(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $validated = $request->validate([
            'account_name' => 'required|string|max:150',
            'sumup_api_key' => 'required|string|max:500',
            'currency' => $this->clubCurrencyRule($clubSlug),
            'opening_balance' => 'required|numeric|max:99999999.99',
        ]);

        $syncService = new SumUpSyncService;
        $testResult = $syncService->testConnection($validated['sumup_api_key']);

        if (! $testResult['success']) {
            return redirect()->back()->with('error', "SumUp Connection Failed: {$testResult['message']}");
        }

        $existingCodes = Account::where('club_id', $club->id)
            ->where('type', 'asset')
            ->where('code', 'like', '10%')
            ->pluck('code')
            ->toArray();

        $codeNum = 1010;
        while (in_array((string) $codeNum, $existingCodes)) {
            $codeNum += 10;
        }

        $ledgerAcc = Account::create([
            'club_id' => $club->id,
            'code' => (string) $codeNum,
            'name' => "SumUp — {$validated['account_name']}",
            'type' => 'asset',
            'currency' => strtoupper($validated['currency']),
            'is_active' => true,
        ]);

        BankAccount::create([
            'club_id' => $club->id,
            'account_id' => $ledgerAcc->id,
            'bank_name' => 'SumUp',
            'account_name' => $validated['account_name'],
            'account_type' => 'merchant',
            'currency' => strtoupper($validated['currency']),
            'opening_balance' => $validated['opening_balance'],
            'sumup_api_key' => $validated['sumup_api_key'],
            'sumup_merchant_code' => $testResult['merchant_code'] ?? null,
            'sumup_connected_at' => now(),
            'sync_status' => 'connected',
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "SumUp Account '{$validated['account_name']}' connected & linked to Nominal Code {$ledgerAcc->code}!");
    }

    public function syncSumUpTransactions(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $bankAccount = BankAccount::where('club_id', $club->id)->findOrFail($id);

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        try {
            $syncService = new SumUpSyncService;
            $result = $syncService->syncTransactions(
                $bankAccount,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            $skippedMsg = isset($result['skipped_count']) && $result['skipped_count'] > 0
                ? " ({$result['skipped_count']} duplicates safely ignored)"
                : '';

            return redirect()->back()->with('success', "SumUp API Sync Complete: {$result['synced_count']} new transactions imported{$skippedMsg}.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "SumUp Sync Error: {$e->getMessage()}");
        }
    }

    public function connectGoCardless(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $validated = $request->validate([
            'account_name' => 'required|string|max:150',
            'gocardless_access_token' => 'required|string|max:500',
            'gocardless_environment' => 'required|string|in:sandbox,live',
            'gocardless_webhook_secret' => 'nullable|string|max:500',
            'currency' => $this->clubCurrencyRule($clubSlug),
            'opening_balance' => 'required|numeric|max:99999999.99',
        ]);

        $syncService = new GoCardlessSyncService;
        $testResult = $syncService->testConnection($validated['gocardless_access_token'], $validated['gocardless_environment']);

        if (! $testResult['success']) {
            return redirect()->back()->with('error', "GoCardless Connection Failed: {$testResult['message']}");
        }

        $existingCodes = Account::where('club_id', $club->id)
            ->where('type', 'asset')
            ->where('code', 'like', '10%')
            ->pluck('code')
            ->toArray();

        $codeNum = 1010;
        while (in_array((string) $codeNum, $existingCodes)) {
            $codeNum += 10;
        }

        $ledgerAcc = Account::create([
            'club_id' => $club->id,
            'code' => (string) $codeNum,
            'name' => "GoCardless — {$validated['account_name']}",
            'type' => 'asset',
            'currency' => strtoupper($validated['currency']),
            'is_active' => true,
        ]);

        BankAccount::create([
            'club_id' => $club->id,
            'account_id' => $ledgerAcc->id,
            'bank_name' => 'GoCardless',
            'account_name' => $validated['account_name'],
            'account_type' => 'payment_gateway',
            'currency' => strtoupper($validated['currency']),
            'opening_balance' => $validated['opening_balance'],
            'gocardless_access_token' => $validated['gocardless_access_token'],
            'gocardless_environment' => $validated['gocardless_environment'],
            'gocardless_webhook_secret' => $validated['gocardless_webhook_secret'] ?? null,
            'gocardless_connected_at' => now(),
            'sync_status' => 'connected',
            'is_active' => true,
        ]);

        return redirect()->back()->with('success', "GoCardless Account '{$validated['account_name']}' connected & linked to Nominal Code {$ledgerAcc->code}!");
    }

    public function syncGoCardlessTransactions(Request $request, string $clubSlug, int $id): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $bankAccount = BankAccount::where('club_id', $club->id)->findOrFail($id);

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        try {
            $syncService = new GoCardlessSyncService;
            $result = $syncService->syncTransactions(
                $bankAccount,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null
            );

            $skippedMsg = isset($result['skipped_count']) && $result['skipped_count'] > 0
                ? " ({$result['skipped_count']} duplicates safely ignored)"
                : '';

            return redirect()->back()->with('success', "GoCardless API Sync Complete: {$result['synced_count']} new transactions imported{$skippedMsg}.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', "GoCardless Sync Error: {$e->getMessage()}");
        }
    }

    public function autoReconcileGiftAid(Request $request, string $clubSlug): RedirectResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $service = app(ReliefChestReconciliationService::class);

        $result = $service->autoReconcileGiftAidAndReliefChest($club);

        if ($result['reconciled_count'] > 0) {
            return redirect()->back()->with('success', "Automated Gift Aid & Relief Chest Match: Reconciled {$result['reconciled_count']} deposit line(s) totaling {$result['formatted_total_amount']}.");
        }

        return redirect()->back()->with('info', 'No unmatched HMRC Gift Aid or Relief Chest deposits found to reconcile.');
    }

    public function exportGiftAidSchedule(Request $request, string $clubSlug)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $service = app(ReliefChestReconciliationService::class);

        $csv = $service->generateHmrcGiftAidScheduleCsv($club);
        $filename = 'Gift_Aid_Claim_Schedule_'.$club->slug.'_'.date('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportVatReturn(Request $request, string $clubSlug)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');

        $quarterStart = $request->query('quarter_start');
        $csv = $this->accountingService->getVatReturnCsv($club, $quarterStart);
        $data = $this->accountingService->getVatReturnData($club, $quarterStart);
        $filename = 'VAT_Return_'.$club->slug.'_'.$data['quarter_label'].'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportAnnualTreasurerReportPdf(Request $request, string $clubSlug)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');

        $year = (int) ($request->query('year') ?: now()->year);
        $report = $this->annualTreasurerReportService->build($club, $year);
        $cs = $club->currencySymbol();
        $viewData = compact('report', 'cs');
        $filename = "Annual-Treasurers-Report-{$club->slug}-{$year}.pdf";

        // Detect Node & Npm paths for Laravel Herd / macOS / Linux environments
        $nodeBinary = trim((string) shell_exec('which node 2>/dev/null'));
        $npmBinary = trim((string) shell_exec('which npm 2>/dev/null'));

        if (! $nodeBinary || ! file_exists($nodeBinary)) {
            $nodeCandidates = glob('/Users/*/Library/Application Support/Herd/config/nvm/versions/node/*/bin/node') ?: [];
            $nodeCandidates = array_merge($nodeCandidates, ['/opt/homebrew/bin/node', '/usr/local/bin/node', '/usr/bin/node']);
            foreach ($nodeCandidates as $candidate) {
                if (file_exists($candidate)) {
                    $nodeBinary = $candidate;
                    break;
                }
            }
        }

        if (! $npmBinary || ! file_exists($npmBinary)) {
            $npmCandidates = glob('/Users/*/Library/Application Support/Herd/config/nvm/versions/node/*/bin/npm') ?: [];
            $npmCandidates = array_merge($npmCandidates, ['/opt/homebrew/bin/npm', '/usr/local/bin/npm', '/usr/bin/npm']);
            foreach ($npmCandidates as $candidate) {
                if (file_exists($candidate)) {
                    $npmBinary = $candidate;
                    break;
                }
            }
        }

        if (! $nodeBinary || ! file_exists($nodeBinary) || ! $npmBinary || ! file_exists($npmBinary)) {
            // No Node/Browsershot available at all in this environment — go straight to DomPDF.
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.accounting.annual-treasurer-report', $viewData)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        }

        try {
            return Pdf::view('pdf.accounting.annual-treasurer-report', $viewData)
                ->withBrowsershot(function ($browsershot) use ($nodeBinary, $npmBinary) {
                    $browsershot->setNodeBinary($nodeBinary);
                    $binDir = str_replace(' ', '\ ', dirname($nodeBinary));
                    $browsershot->setIncludePath($binDir.':/opt/homebrew/bin:/usr/local/bin:/usr/bin');
                    $browsershot->setNpmBinary($npmBinary);
                })
                ->name($filename);
        } catch (\Throwable $e) {
            // Fallback gracefully to DomPDF if Node/Browsershot fails in specific PHP-FPM environments.
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.accounting.annual-treasurer-report', $viewData)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        }
    }

    public function exportAnnualTreasurerReportCsv(Request $request, string $clubSlug)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');

        $year = (int) ($request->query('year') ?: now()->year);
        $report = $this->annualTreasurerReportService->build($club, $year);
        $csv = $this->annualTreasurerReportService->toCsv($report);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"Annual-Treasurers-Report-{$club->slug}-{$year}.csv\"",
        ]);
    }

    public function exportReport(Request $request, string $clubSlug, string $report)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        ClubAccess::authorize($request->user(), $club, 'manage_billing');

        $budgetYear = $request->query('budget_year') ? (int) $request->query('budget_year') : null;

        try {
            $csv = $this->accountingService->getReportCsv($club, $report, $budgetYear);
        } catch (\InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        }

        $filename = ucwords(str_replace('_', ' ', $report)).' - '.$club->slug.' - '.date('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function filterReconciledDonations(Request $request, string $clubSlug)
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $service = app(ReliefChestReconciliationService::class);

        $data = $service->getReconciledDonations($club, $request->all());

        return response()->json($data);
    }

    public function giftAidTransactionsPage(Request $request, string $clubSlug): Response
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $service = app(ReliefChestReconciliationService::class);

        $giftAidSummary = $service->getGiftAidSummary($club);
        $reconciledDonations = $service->getReconciledDonations($club, $request->all());

        return Inertia::render('Admin/Accounting/GiftAidTransactions', [
            'club' => $club,
            'giftAidSummary' => $giftAidSummary,
            'reconciledDonations' => $reconciledDonations,
            'filters' => $request->only(['date_from', 'date_to', 'person_id', 'status', 'search']),
        ]);
    }

    /**
     * Authorised link to an accounting attachment (stored on the private disk).
     */
    private function attachmentUrl(Club $club, Media $media): string
    {
        return route('admin.accounting.attachments.show', ['clubSlug' => $club->slug, 'mediaId' => $media->id]);
    }

    /**
     * Stream an accounting attachment to a signed-in user who can manage billing at this club.
     */
    public function showAttachment(string $clubSlug, int $mediaId): BinaryFileResponse
    {
        $club = Club::where('slug', $clubSlug)->firstOrFail();
        $media = $club->media()->where('collection_name', 'accounting')->where('id', $mediaId)->firstOrFail();

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Every account in a club's books uses that club's own currency.
     *
     * @return list<mixed>
     */
    private function clubCurrencyRule(string $clubSlug): array
    {
        $currency = Club::where('slug', $clubSlug)->firstOrFail()->currencyCode();

        return ['required', 'string', 'size:3', Rule::in([$currency])];
    }
}
