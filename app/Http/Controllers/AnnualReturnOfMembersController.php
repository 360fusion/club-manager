<?php

namespace App\Http\Controllers;

use App\Services\AnnualReturnOfMembers;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The annual return of members to Grand Lodge and Province for one financial year (?year=, default the current one):
 * printable, or a PDF with ?download=1. Under /admin/accounting, so only those who may see the lodge's money open it.
 */
class AnnualReturnOfMembersController extends Controller
{
    public function __invoke(Request $request, string $clubSlug, AnnualReturnOfMembers $return): Response
    {
        $club = $this->findClub($clubSlug);
        $current = $club->financialYearFor(now());
        $year = min($current, max(2000, (int) $request->query('year', (string) $current)));
        $data = $return->build($club, $year) + ['currentYear' => $current];

        if ($request->boolean('download')) {
            return Pdf::loadView('pdf.accounting.annual-return', $data + ['pdf' => true])
                ->setPaper('a4')
                ->download("Annual-return-{$club->slug}-{$year}.pdf");
        }

        return response()->view('pdf.accounting.annual-return', $data + ['pdf' => false]);
    }
}
