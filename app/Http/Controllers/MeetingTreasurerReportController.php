<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Services\Meetings\MeetingTreasurerReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The Treasurer's report for one meeting: printable, or a PDF with ?download=1 to attach to the summons.
 * It sits under /admin/accounting, so only people who may see the lodge's money can open it.
 */
class MeetingTreasurerReportController extends Controller
{
    public function __invoke(Request $request, string $clubSlug, int $id, MeetingTreasurerReport $report): Response
    {
        $club = $this->findClub($clubSlug);
        $meeting = Meeting::where('club_id', $club->id)->where('id', $id)->firstOrFail();
        $data = $report->build($club, $meeting);

        if ($request->boolean('download')) {
            return Pdf::loadView('summons.treasurer-report', $data + ['pdf' => true])
                ->setPaper('a4')
                ->download('Treasurers-report-'.Str::slug($meeting->title).'-'.$meeting->meeting_date->format('Y-m-d').'.pdf');
        }

        return response()->view('summons.treasurer-report', $data + ['pdf' => false]);
    }
}
