<?php

namespace App\Services;

use App\Models\Accounting\AccountingPeriodClose;
use App\Models\Accounting\AccountingYearAudit;
use App\Models\Accounting\IndependentExaminerReport;
use App\Models\Club;
use App\Models\User;
use InvalidArgumentException;

/**
 * Closing and reopening a financial year, and the auditors' and independent examiner's sign-off. Split out of AccountingService.
 */
class FinancialYearService
{
    public function __construct(protected AccountingService $accounting) {}

    /**
     * Close a financial year's books, blocking further postings into it for normal
     * admins (see financialYearIsClosed() usage in postJournalEntry()/updateJournalEntry()).
     */
    public function closeFinancialYear(Club $club, int $year, ?User $actor = null, ?string $notes = null): AccountingPeriodClose
    {
        $close = AccountingPeriodClose::updateOrCreate(
            ['club_id' => $club->id, 'financial_year' => $year],
            ['closed_at' => now(), 'closed_by_user_id' => $actor?->id, 'notes' => $notes]
        );

        $this->accounting->log($club, 'financial_year', $year, 'closed', "Financial year {$year} closed.", userId: $actor?->id);

        return $close;
    }

    /**
     * Reopen a previously closed financial year. Super-admin only at the controller
     * level — reopening the books is a rare, deliberate action.
     */
    public function reopenFinancialYear(Club $club, int $year, ?User $actor = null): void
    {
        AccountingPeriodClose::where('club_id', $club->id)->where('financial_year', $year)->delete();

        $this->accounting->log($club, 'financial_year', $year, 'reopened', "Financial year {$year} reopened.", userId: $actor?->id);
    }

    /**
     * Name the two elected auditors for a financial year and leave the audit awaiting their actual signatures
     * (signed_off_at stays null until both have signed via the signature-request flow).
     */
    public function requestYearAudit(Club $club, int $year, User $auditorOne, User $auditorTwo, ?string $notes = null): AccountingYearAudit
    {
        if ($auditorOne->is($auditorTwo)) {
            throw new InvalidArgumentException('The two auditors must be different people.');
        }

        return AccountingYearAudit::updateOrCreate(
            ['club_id' => $club->id, 'financial_year' => $year],
            [
                'auditor_one_user_id' => $auditorOne->id,
                'auditor_two_user_id' => $auditorTwo->id,
                'notes' => $notes,
            ]
        );
    }

    /**
     * Name the Independent Examiner for a registered charity's financial year, leaving the report awaiting
     * their single signature (examined_at stays null until it is completed via the signature-request flow).
     */
    public function requestIndependentExamination(Club $club, int $year, User $examiner, ?string $notes = null): IndependentExaminerReport
    {
        if (! $club->charityCommissionSettings()['registered']) {
            throw new InvalidArgumentException('This lodge has not said its Charity Fund is registered with the Charity Commission.');
        }

        $existing = IndependentExaminerReport::where('club_id', $club->id)->where('financial_year', $year)->first();

        if ($existing?->examined_at !== null) {
            throw new InvalidArgumentException("The {$year} Independent Examiner's Report has already been signed.");
        }

        return IndependentExaminerReport::updateOrCreate(
            ['club_id' => $club->id, 'financial_year' => $year],
            ['examiner_user_id' => $examiner->id, 'observations' => $notes]
        );
    }

    /**
     * Called once the examiner's signature completes: stamps the report as examined and logs it.
     */
    public function markIndependentExaminationSigned(IndependentExaminerReport $report): void
    {
        $report->update(['examined_at' => now()]);
        $report->loadMissing(['club', 'examiner']);

        $this->accounting->log($report->club, 'financial_year', $report->financial_year, 'examined', "Financial year {$report->financial_year} independently examined by {$report->examiner?->name}.", userId: $report->examiner_user_id);
    }

    /**
     * Record that two elected auditors (Book of Constitutions Rule 153 — never the
     * Treasurer or Secretary) have signed off a financial year's accounts.
     */
    public function signOffYearAudit(Club $club, int $year, User $auditorOne, User $auditorTwo, ?string $notes = null): AccountingYearAudit
    {
        if ($auditorOne->is($auditorTwo)) {
            throw new InvalidArgumentException('The two auditors must be different people.');
        }

        $audit = AccountingYearAudit::updateOrCreate(
            ['club_id' => $club->id, 'financial_year' => $year],
            [
                'auditor_one_user_id' => $auditorOne->id,
                'auditor_two_user_id' => $auditorTwo->id,
                'signed_off_at' => now(),
                'notes' => $notes,
            ]
        );

        $this->accounting->log($club, 'financial_year', $year, 'audited', "Financial year {$year} signed off by {$auditorOne->name} and {$auditorTwo->name}.");

        return $audit;
    }
}
