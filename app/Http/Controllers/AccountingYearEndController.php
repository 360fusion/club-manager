<?php

namespace App\Http\Controllers;

use App\Enums\SignatureRequestStatus;
use App\Http\Requests\AccountingYearEnd\CancelYearAuditSignatureRequest;
use App\Http\Requests\AccountingYearEnd\CloseFinancialYearRequest;
use App\Http\Requests\AccountingYearEnd\ReopenFinancialYearRequest;
use App\Http\Requests\AccountingYearEnd\ResendYearAuditSignatureRequest;
use App\Http\Requests\AccountingYearEnd\SignOffYearAuditRequest;
use App\Models\Accounting\AccountingYearAudit;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\FinancialYearService;
use App\Services\Signatures\SignatureRequestService;
use Illuminate\Http\RedirectResponse;

/**
 * Closing and reopening a financial year, and the two auditors' sign-off. Split out of AccountingAdminController;
 * the URLs, route names and permissions (the accounting section of club_permissions) are unchanged.
 */
class AccountingYearEndController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService,
        protected SignatureRequestService $signatureRequestService
    ) {}

    public function closeFinancialYear(CloseFinancialYearRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        app(FinancialYearService::class)->closeFinancialYear($club, (int) $validated['financial_year'], $request->user(), $validated['notes'] ?? null);

        return redirect()->back()->with('success', "Financial year {$validated['financial_year']} closed.");
    }

    public function reopenFinancialYear(ReopenFinancialYearRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        if (! $request->user()->is_super_admin) {
            abort(403, 'Only a platform super admin can reopen a closed financial year.');
        }

        $validated = $request->validated();

        app(FinancialYearService::class)->reopenFinancialYear($club, (int) $validated['financial_year'], $request->user());

        return redirect()->back()->with('success', "Financial year {$validated['financial_year']} reopened.");
    }

    public function signOffYearAudit(SignOffYearAuditRequest $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        $validated = $request->validated();

        $auditorOne = User::whereHas('clubs', fn ($q) => $q->where('clubs.id', $club->id))->findOrFail($validated['auditor_one_user_id']);
        $auditorTwo = User::whereHas('clubs', fn ($q) => $q->where('clubs.id', $club->id))->findOrFail($validated['auditor_two_user_id']);

        $audit = app(FinancialYearService::class)->requestYearAudit($club, (int) $validated['financial_year'], $auditorOne, $auditorTwo, $validated['notes'] ?? null);

        $this->signatureRequestService->requestIfOpen($audit, 'year_audit_auditor_one', $auditorOne, $auditorOne->name, $auditorOne->email, $request->user());
        $this->signatureRequestService->requestIfOpen($audit, 'year_audit_auditor_two', $auditorTwo, $auditorTwo->name, $auditorTwo->email, $request->user());

        return redirect()->back()->with('success', "Signature requests sent to {$auditorOne->name} and {$auditorTwo->name} for {$validated['financial_year']}.");
    }

    public function resendYearAuditSignature(ResendYearAuditSignatureRequest $request, string $clubSlug, string $purpose): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        abort_unless(in_array($purpose, ['year_audit_auditor_one', 'year_audit_auditor_two'], true), 404);

        $validated = $request->validated();

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

    public function cancelYearAuditSignature(CancelYearAuditSignatureRequest $request, string $clubSlug, string $purpose): RedirectResponse
    {
        $club = $this->findClub($clubSlug);

        abort_unless(in_array($purpose, ['year_audit_auditor_one', 'year_audit_auditor_two'], true), 404);

        $validated = $request->validated();

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
}
