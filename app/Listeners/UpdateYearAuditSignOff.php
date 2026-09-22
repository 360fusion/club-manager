<?php

namespace App\Listeners;

use App\Enums\SignatureRequestStatus;
use App\Events\SignatureCompleted;
use App\Models\Accounting\AccountingYearAudit;
use App\Services\AccountingService;
use App\Services\Signatures\SignatureRequestService;

/**
 * Once both named auditors have signed, stamp the financial year's audit as signed off — the same
 * AccountingService::signOffYearAudit() the admin form used to call directly.
 */
class UpdateYearAuditSignOff
{
    public function __construct(
        private readonly SignatureRequestService $signatures,
        private readonly AccountingService $accounting,
    ) {}

    public function handle(SignatureCompleted $event): void
    {
        $request = $event->request;

        if (! in_array($request->purpose, ['year_audit_auditor_one', 'year_audit_auditor_two'], true)) {
            return;
        }

        $audit = $request->signable;

        if (! $audit instanceof AccountingYearAudit || $audit->signed_off_at !== null) {
            return;
        }

        $requests = $this->signatures->forSignable($audit)
            ->whereIn('purpose', ['year_audit_auditor_one', 'year_audit_auditor_two']);

        $signed = $requests->where('status', SignatureRequestStatus::Signed);

        if ($signed->pluck('purpose')->unique()->count() < 2) {
            return;
        }

        $audit->loadMissing(['club', 'auditorOne', 'auditorTwo']);

        $this->accounting->signOffYearAudit($audit->club, $audit->financial_year, $audit->auditorOne, $audit->auditorTwo, $audit->notes);
    }
}
