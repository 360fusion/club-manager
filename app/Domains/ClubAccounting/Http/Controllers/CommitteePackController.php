<?php

namespace App\Domains\ClubAccounting\Http\Controllers;

use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Domains\ClubAccounting\Services\Governance\CommitteePackCompilerService;
use App\Http\Controllers\Controller;
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
            'noticesOfMotion'
        ])->findOrFail($id);

        $compiler = app(CommitteePackCompilerService::class);
        $pdfOutput = $compiler->compilePdf($meeting);

        $filename = 'Agenda-Pack-' . Str::slug($meeting->title) . '.pdf';
        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
        ]);
    }
}
