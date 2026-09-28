<?php

namespace App\Listeners;

use App\Events\SignatureCompleted;
use App\Models\Accounting\IndependentExaminerReport;
use App\Services\AccountingService;

/**
 * The examiner's single signature is the whole sign-off: stamp the report as examined.
 */
class UpdateIndependentExaminerReportSignOff
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function handle(SignatureCompleted $event): void
    {
        if ($event->request->purpose !== AccountingService::INDEPENDENT_EXAMINER_PURPOSE) {
            return;
        }

        $report = $event->request->signable;

        if (! $report instanceof IndependentExaminerReport || $report->examined_at !== null) {
            return;
        }

        $this->accounting->markIndependentExaminationSigned($report);
    }
}
