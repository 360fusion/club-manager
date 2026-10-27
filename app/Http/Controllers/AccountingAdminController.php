<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Models\BankAccount;
use App\Enums\ClubMemberStatus;
use App\Enums\ClubRole;
use App\Http\Controllers\Concerns\LinksAccountingAttachments;
use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\IndependentExaminerReport;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\LedgerAuditLog;
use App\Models\Accounting\RecurringBillTemplate;
use App\Models\Club;
use App\Models\Invoice;
use App\Models\User;
use App\Services\AccountingReports;
use App\Services\AccountingService;
use App\Services\AnnualTreasurerReportService;
use App\Services\BudgetService;
use App\Services\FixedAssetService;
use App\Services\LedgerScreenData;
use App\Services\ReconciliationScreenData;
use App\Services\Signatures\SignatureRequestService;
use App\Support\ClubAccess;
use App\Support\Currencies;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AccountingAdminController extends Controller
{
    use LinksAccountingAttachments;

    public function __construct(
        protected AccountingService $accountingService,
        protected FixedAssetService $fixedAssetService,
        protected BudgetService $budgetService,
        protected AnnualTreasurerReportService $annualTreasurerReportService,
        protected SignatureRequestService $signatureRequestService
    ) {}

    public function index(Request $request, string $clubSlug, ?string $tab = null, ?string $report = null): Response
    {
        $club = $this->findClub($clubSlug);

        $this->accountingService->seedDefaultAccounts($club);

        $accountList = Account::where('club_id', $club->id)->orderBy('code')->get();
        Account::loadTotals($accountList);

        $accounts = $accountList->map(fn ($acc) => [
            'id' => $acc->id,
            'code' => $acc->code,
            'name' => $acc->name,
            'type' => $acc->type,
            'currency' => $acc->currency,
            'is_active' => $acc->is_active,
            'balance' => $acc->balance,
            'formatted_balance' => Currencies::format($acc->balance, $club),
        ]);

        $journalEntries = app(LedgerScreenData::class)->journalEntries($club);

        $invoices = app(LedgerScreenData::class)->invoices($club, $request->user());

        $bills = app(LedgerScreenData::class)->bills($club, $request->user());

        $members = User::whereHas('clubs', fn ($q) => $q->where('clubs.id', $club->id))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $contacts = app(LedgerScreenData::class)->contacts($club);

        $summary = app(AccountingReports::class)->getFinancialSummary($club);
        $reports = app(AccountingReports::class)->getReportsData($club);

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
            'financial_year_end_month' => 4,
        ], $club->settings ?? []);

        $reconciliation = app(ReconciliationScreenData::class)->build($club);

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

        $canManageBilling = ClubAccess::can($request->user(), $club, 'manage_billing');

        $rawSettings = $club->settings ?? [];
        $onboarding = [
            'dismissed' => (bool) ($rawSettings['onboarding_dismissed'] ?? false),
            'steps' => [
                [
                    'key' => 'bank_account',
                    'label' => 'Add your first bank account',
                    'tab' => 'bank-accounts',
                    'done' => $bankAccountsQuery->isNotEmpty(),
                    'skippable' => false,
                ],
                [
                    'key' => 'opening_balance',
                    'label' => 'Set an opening balance',
                    'tab' => 'chart-of-accounts',
                    'done' => JournalEntry::where('club_id', $club->id)->where('source_type', 'OpeningBalance')->exists(),
                    'skippable' => false,
                ],
                [
                    'key' => 'financial_year_end',
                    'label' => 'Set your financial year end',
                    'tab' => 'settings',
                    'done' => array_key_exists('financial_year_end_month', $rawSettings),
                    'skippable' => false,
                ],
                [
                    'key' => 'vat_settings',
                    'label' => 'Review VAT settings',
                    'tab' => 'settings',
                    'done' => array_key_exists('vat', $rawSettings),
                    'skippable' => true,
                ],
            ],
        ];

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
            'approvalThreshold' => $club->approvalThresholdSettings(),
            'charityCommission' => $club->charityCommissionSettings(),
            'independentExaminer' => $this->independentExaminerPayload($club, $treasurerReportYear),
            'reconciliation' => $reconciliation,
            'fixedAssets' => $fixedAssets,
            'budgetVsActual' => $budgetVsActual,
            'annualTreasurerReport' => $annualTreasurerReport,
            'recurringBillTemplates' => $recurringBillTemplates,
            'onboarding' => $onboarding,
            'canManageBilling' => $canManageBilling,
            'canExportAllData' => ClubAccess::canExportAllData($request->user(), $club),
        ]);
    }

    /**
     * What the Independent Examiner's Report screen needs; only meaningful for a registered charity.
     *
     * @return array<string, mixed>
     */
    private function independentExaminerPayload(Club $club, int $year): array
    {
        if (! $club->charityCommissionSettings()['registered']) {
            return ['financial_year' => $year, 'examiners' => [], 'report' => null];
        }

        $report = IndependentExaminerReport::where('club_id', $club->id)->where('financial_year', $year)->with('examiner')->first();
        $signature = $report
            ? $this->signatureRequestService->forSignable($report)->where('purpose', AccountingService::INDEPENDENT_EXAMINER_PURPOSE)->sortByDesc('requested_at')->first()
            : null;

        return [
            'financial_year' => $year,
            'examiners' => $club->users()->wherePivot('role', ClubRole::Examiner->value)->wherePivot('status', ClubMemberStatus::Active->value)->orderBy('users.name')->get(['users.id', 'users.name'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all(),
            'report' => $report ? [
                'examiner_name' => $report->examiner?->name,
                'examined_at' => $report->examined_at?->format('d M Y'),
                'notes' => $report->observations,
                'signature_status' => $signature?->status->value,
                'signature_label' => $signature?->status->label(),
            ] : null,
        ];
    }

    /**
     * Activity log for a single entity (bill, invoice, fixed asset, ...), backed by
     * the append-only LedgerAuditLog trail every mutation already writes to.
     */
    public function activityLog(string $clubSlug, string $entityType, int $entityId): JsonResponse
    {
        $club = $this->findClub($clubSlug);

        abort_unless(in_array($entityType, ['bill', 'invoice', 'fixed_asset', 'journal_entry', 'financial_year'], true), 404);

        $entries = LedgerAuditLog::where('club_id', $club->id)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->with('user')
            ->get()
            ->map(fn (LedgerAuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'summary' => $log->summary,
                'user_name' => $log->user?->name ?? 'System',
                'created_at' => $log->created_at->format('d M Y H:i'),
            ]);

        return response()->json(['entries' => $entries]);
    }

    /**
     * Stream an accounting attachment to a signed-in user who can manage billing at this club.
     */
    public function showAttachment(string $clubSlug, int $mediaId): BinaryFileResponse
    {
        $club = $this->findClub($clubSlug);
        $media = $club->media()->where('collection_name', 'accounting')->where('id', $mediaId)->firstOrFail();

        return response()->file($media->getPath(), [
            'Content-Type' => $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
