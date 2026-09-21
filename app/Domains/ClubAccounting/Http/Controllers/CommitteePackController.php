<?php

namespace App\Domains\ClubAccounting\Http\Controllers;

use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Domains\ClubAccounting\Services\Governance\CommitteePackCompilerService;
use App\Http\Controllers\Controller;
use App\Support\ClubAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CommitteePackController extends Controller
{
    public function pdf(Request $request, $param1 = null, $param2 = null)
    {
        $id = null;
        if (is_numeric($param2)) {
            $id = (int) $param2;
        } elseif (is_numeric($param1)) {
            $id = (int) $param1;
        } else {
            $id = (int) $request->route('meetingId');
        }

        $meeting = ClubCommitteeMeeting::with([
            'club',
            'chair',
            'secretary',
            'attendees',
            'agendaItems' => fn ($q) => $q->orderBy('order'),
            'tasks.assignedTo',
            'noticesOfMotion',
        ])->findOrFail($id);

        // The meeting must belong to the club in the URL (when there is one), and the
        // caller must be allowed to manage that club's meetings.
        abort_if($request->route('clubSlug') && $meeting->club->slug !== $request->route('clubSlug'), 404);
        ClubAccess::authorize($request->user(), $meeting->club, 'manage_meetings');

        $compiler = app(CommitteePackCompilerService::class);
        $pdfOutput = $compiler->compilePdf($meeting);

        $filename = 'Agenda-Pack-'.Str::slug($meeting->title).'.pdf';
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }
}
