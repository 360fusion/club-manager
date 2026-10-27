<?php

namespace App\Http\Controllers;

use App\Domains\ClubAccounting\Services\ReliefChestReconciliationService;
use App\Services\AccountingDataExportService;
use App\Services\AccountingReports;
use App\Services\AccountingService;
use App\Services\AnnualTreasurerReportService;
use App\Support\ClubAccess;
use App\Support\NodeBinaries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\Facades\Pdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads: the full data export, Gift Aid and VAT schedules, the treasurer's report and the standard reports as PDF or CSV. Split out of AccountingAdminController; the URLs, route names and permissions are unchanged.
 */
class AccountingExportController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService,
        protected AnnualTreasurerReportService $annualTreasurerReportService
    ) {}

    /**
     * The whole set of books as a zip of CSVs. Owner-only: it holds member details and bank references.
     */
    public function exportAccountingData(Request $request, string $clubSlug): BinaryFileResponse
    {
        $club = $this->findClub($clubSlug);
        abort_unless(ClubAccess::canExportAllData($request->user(), $club), 403, 'Only the lodge owner can export all accounting data.');

        $path = app(AccountingDataExportService::class)->build($club);

        return response()->download($path, "Accounting-Export-{$club->slug}-".date('Y-m-d').'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }

    public function autoReconcileGiftAid(Request $request, string $clubSlug): RedirectResponse
    {
        $club = $this->findClub($clubSlug);
        $service = app(ReliefChestReconciliationService::class);

        $result = $service->autoReconcileGiftAidAndReliefChest($club);

        if ($result['reconciled_count'] > 0) {
            return redirect()->back()->with('success', "Automated Gift Aid & Relief Chest Match: Reconciled {$result['reconciled_count']} deposit line(s) totaling {$result['formatted_total_amount']}.");
        }

        return redirect()->back()->with('info', 'No unmatched HMRC Gift Aid or Relief Chest deposits found to reconcile.');
    }

    public function exportGiftAidSchedule(Request $request, string $clubSlug)
    {
        $club = $this->findClub($clubSlug);
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
        $club = $this->findClub($clubSlug);
        ClubAccess::authorize($request->user(), $club, 'view_accounting');

        $quarterStart = $request->query('quarter_start');
        $csv = app(AccountingReports::class)->getVatReturnCsv($club, $quarterStart);
        $data = app(AccountingReports::class)->getVatReturnData($club, $quarterStart);
        $filename = 'VAT_Return_'.$club->slug.'_'.$data['quarter_label'].'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportAnnualTreasurerReportPdf(Request $request, string $clubSlug)
    {
        $club = $this->findClub($clubSlug);
        ClubAccess::authorize($request->user(), $club, 'view_accounting');

        $year = (int) ($request->query('year') ?: now()->year);
        $report = $this->annualTreasurerReportService->build($club, $year);
        $cs = $club->currencySymbol();
        $viewData = compact('report', 'cs');
        $filename = "Annual-Treasurers-Report-{$club->slug}-{$year}.pdf";

        return $this->renderAccountingPdf($request, 'pdf.accounting.annual-treasurer-report', $viewData, $filename);
    }

    /**
     * Render an accounting PDF, preferring Browsershot (Node) when available and
     * falling back to DomPDF when it isn't, or if it throws. The render must happen
     * inside the try, since Pdf::view()->name() only actually renders in toResponse().
     */
    private function renderAccountingPdf(Request $request, string $view, array $data, string $filename)
    {
        ['node' => $nodeBinary, 'npm' => $npmBinary] = NodeBinaries::find();

        if (! $nodeBinary || ! file_exists($nodeBinary) || ! $npmBinary || ! file_exists($npmBinary)) {
            // No Node/Browsershot available at all in this environment — go straight to DomPDF.
            return \Barryvdh\DomPDF\Facade\Pdf::loadView($view, $data)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        }

        try {
            return Pdf::view($view, $data)
                ->withBrowsershot(function ($browsershot) use ($nodeBinary, $npmBinary) {
                    $browsershot->setNodeBinary($nodeBinary);
                    $binDir = str_replace(' ', '\ ', dirname($nodeBinary));
                    $browsershot->setIncludePath($binDir.':/opt/homebrew/bin:/usr/local/bin:/usr/bin');
                    $browsershot->setNpmBinary($npmBinary);
                })
                ->name($filename)
                ->toResponse($request);
        } catch (\Throwable $e) {
            // The PDF is only rendered by toResponse(), so it must run inside this try for the fallback to work.
            // Say so in the log: without it a missing Chrome for Puppeteer quietly changes how every PDF looks.
            report($e);

            return \Barryvdh\DomPDF\Facade\Pdf::loadView($view, $data)
                ->setPaper('a4', 'portrait')
                ->download($filename);
        }
    }

    public function exportAnnualTreasurerReportCsv(Request $request, string $clubSlug)
    {
        $club = $this->findClub($clubSlug);
        ClubAccess::authorize($request->user(), $club, 'view_accounting');

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
        $club = $this->findClub($clubSlug);
        ClubAccess::authorize($request->user(), $club, 'view_accounting');

        $budgetYear = $request->query('budget_year') ? (int) $request->query('budget_year') : null;

        try {
            $csv = app(AccountingReports::class)->getReportCsv($club, $report, $budgetYear);
        } catch (\InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        }

        $filename = ucwords(str_replace('_', ' ', $report)).' - '.$club->slug.' - '.date('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportReportPdf(Request $request, string $clubSlug, string $report)
    {
        $club = $this->findClub($clubSlug);
        ClubAccess::authorize($request->user(), $club, 'view_accounting');

        $budgetYear = $request->query('budget_year') ? (int) $request->query('budget_year') : null;

        try {
            [$view, $data] = app(AccountingReports::class)->getReportPdfView($club, $report, $budgetYear);
        } catch (\InvalidArgumentException $e) {
            abort(404, $e->getMessage());
        }

        $filename = ucwords(str_replace('_', ' ', $report)).' - '.$club->slug.' - '.date('Y-m-d').'.pdf';

        return $this->renderAccountingPdf($request, $view, $data, $filename);
    }

    public function filterReconciledDonations(Request $request, string $clubSlug)
    {
        $club = $this->findClub($clubSlug);
        $service = app(ReliefChestReconciliationService::class);

        $data = $service->getReconciledDonations($club, $request->all());

        return response()->json($data);
    }

    public function exportIndependentExaminerReportPdf(Request $request, string $clubSlug)
    {
        $club = $this->findClub($clubSlug);
        ClubAccess::authorize($request->user(), $club, 'view_accounting');
        abort_unless($club->charityCommissionSettings()['registered'], 404);

        $year = (int) ($request->query('year') ?: now()->year);
        $viewData = $this->annualTreasurerReportService->independentExaminerReportData($club, $year) + ['cs' => $club->currencySymbol()];

        return $this->renderAccountingPdf($request, 'pdf.accounting.independent-examiner-report', $viewData, "Independent-Examiners-Report-{$club->slug}-{$year}.pdf");
    }
}
