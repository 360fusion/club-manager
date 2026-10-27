<?php

namespace App\Http\Controllers;

use App\Enums\ClubMemberStatus;
use App\Enums\ClubRole;
use App\Enums\SignatureRequestStatus;
use App\Http\Requests\AccountingAdmin\StoreAccountRequest;
use App\Http\Requests\AccountingAdmin\StoreOpeningBalanceRequest;
use App\Http\Requests\AccountingAdmin\UpdateApprovalThresholdRequest;
use App\Http\Requests\AccountingAdmin\UpdateCharityCommissionRequest;
use App\Http\Requests\AccountingAdmin\UpdateFinancialYearEndMonthRequest;
use App\Http\Requests\AccountingAdmin\UpdateVatSettingsRequest;
use App\Http\Requests\AccountingSettings\RequestIndependentExaminationRequest;
use App\Http\Requests\AccountingSettings\ResendIndependentExaminerSignatureRequest;
use App\Models\Accounting\Account;
use App\Models\Accounting\IndependentExaminerReport;
use App\Services\AccountingService;
use App\Services\FinancialYearService;
use App\Services\Signatures\SignatureRequestService;
use App\Support\ClubAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Accounting settings: chart of accounts, opening balances, VAT, commission, approval threshold, year end and the independent examiner request. Split out of AccountingAdminController; the URLs, route names and permissions are unchanged.
 */
class AccountingSettingsController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService,
        protected SignatureRequestService $signatureRequestService
    ) {}

    public function storeAccount(StoreAccountRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

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

    public function storeOpeningBalance(StoreOpeningBalanceRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        $account = Account::where('club_id', $club->id)->where('id', $validated['account_id'])->firstOrFail();

        $this->accountingService->setOpeningBalance($club, $account, (float) $validated['opening_balance'], $validated['as_of_date'] ?? null);

        return redirect()->back()->with('success', "Opening balance updated for {$account->code} - {$account->name}.");
    }

    public function updateVatSettings(UpdateVatSettingsRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

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

    public function updateCharityCommission(UpdateCharityCommissionRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        $club->update([
            'settings' => array_merge($club->settings ?? [], ['charity_commission' => [
                'registered' => $validated['registered'],
                'charity_number' => $validated['charity_number'] ?? null,
            ]]),
        ]);

        return redirect()->back()->with('success', 'Charity Commission details updated.');
    }

    public function requestIndependentExamination(RequestIndependentExaminationRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        abort_unless($club->charityCommissionSettings()['registered'], 404);

        $validated = $request->validated();

        $examiner = $club->users()->wherePivot('role', ClubRole::Examiner->value)->wherePivot('status', ClubMemberStatus::Active->value)->where('users.id', $validated['examiner_user_id'])->first();

        if (! $examiner) {
            return redirect()->back()->withErrors(['examiner_user_id' => 'Choose someone who has been given the Examiner role at this lodge.']);
        }

        try {
            $report = app(FinancialYearService::class)->requestIndependentExamination($club, (int) $validated['financial_year'], $examiner, $validated['notes'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $this->signatureRequestService->requestIfOpen($report, AccountingService::INDEPENDENT_EXAMINER_PURPOSE, $examiner, $examiner->name, $examiner->email, $request->user());

        return redirect()->back()->with('success', "Signature request sent to {$examiner->name} for the {$validated['financial_year']} Independent Examiner's Report.");
    }

    public function resendIndependentExaminerSignature(ResendIndependentExaminerSignatureRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        abort_unless($club->charityCommissionSettings()['registered'], 404);

        $validated = $request->validated();
        $report = IndependentExaminerReport::where('club_id', $club->id)->where('financial_year', $validated['financial_year'])->firstOrFail();

        $pending = $this->signatureRequestService->forSignable($report)
            ->where('purpose', AccountingService::INDEPENDENT_EXAMINER_PURPOSE)
            ->where('status', SignatureRequestStatus::Pending)
            ->first();

        if ($pending) {
            $this->signatureRequestService->resend($pending);
        }

        return redirect()->back()->with('success', 'Signature request resent.');
    }

    public function updateApprovalThreshold(UpdateApprovalThresholdRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        $club->update([
            'settings' => array_merge($club->settings ?? [], [
                'approval_threshold' => [
                    'enabled' => $validated['enabled'],
                    'amount' => $validated['amount'] !== null ? (float) $validated['amount'] : null,
                ],
            ]),
        ]);

        return redirect()->back()->with('success', 'Approval threshold updated.');
    }

    public function updateFinancialYearEndMonth(UpdateFinancialYearEndMonthRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        $club->update([
            'settings' => array_merge($club->settings ?? [], ['financial_year_end_month' => $validated['financial_year_end_month']]),
        ]);

        return redirect()->back()->with('success', 'Financial year end updated.');
    }

    public function dismissOnboarding(Request $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        ClubAccess::authorize($request->user(), $club, 'manage_billing');

        $club->update([
            'settings' => array_merge($club->settings ?? [], ['onboarding_dismissed' => true]),
        ]);

        return redirect()->back();
    }
}
