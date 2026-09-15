<?php

namespace App\Domains\ClubAccounting\Services\Governance;

use App\Domains\ClubAccounting\Enums\AttendanceType;
use App\Domains\ClubAccounting\Mail\CommitteeAgendaPackMailable;
use App\Domains\ClubAccounting\Models\ClubCommitteeAttendee;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Club;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class CommitteePackCompilerService
{
    /**
     * Compile structured data bundle for the Committee Pre-Meeting Pack.
     */
    public function compilePackData(ClubCommitteeMeeting $meeting): array
    {
        $club = $meeting->club;

        // 1. Previous meeting minutes
        $previousMeeting = ClubCommitteeMeeting::where('club_id', $club->id)
            ->where('id', '!=', $meeting->id)
            ->where('meeting_date', '<', $meeting->meeting_date)
            ->orderByDesc('meeting_date')
            ->first();

        // 2. Candidate Vetting Queue (Candidates pending initiation or joining)
        $domainCandidates = \App\Domains\ClubAccounting\Models\Candidate::where('club_id', $club->id)
            ->where('stage', \App\Domains\ClubAccounting\Enums\CandidateStage::LodgeCommittee->value)
            ->get();

        if ($domainCandidates->isNotEmpty()) {
            $candidates = $domainCandidates->map(function ($c) {
                return (object) [
                    'id' => $c->id,
                    'name' => $c->full_name,
                    'email' => $c->email,
                    'created_at' => $c->created_at,
                    'is_domain_candidate' => true,
                ];
            });
        } else {
            $candidates = User::whereHas('clubs', function ($q) use ($club) {
                $q->where('clubs.id', $club->id)
                  ->whereIn('role', ['candidate', 'applicant', 'enquirer']);
            })->get(['id', 'name', 'email', 'created_at']);
        }

        // 3. Unpaid Vendor Bills for Audit
        $unpaidBills = Bill::where('club_id', $club->id)
            ->where('status', 'unpaid')
            ->orderBy('due_date')
            ->get();

        // 4. Key Bank & Ledger Balances
        $accounts = Account::where('club_id', $club->id)
            ->whereIn('code', ['1000', '1200', '2000'])
            ->get();

        // 5. Active Notices of Motion
        $motions = $meeting->noticesOfMotion()->get();

        return [
            'meeting' => $meeting,
            'club' => $club,
            'attendees' => $meeting->attendees,
            'agenda_items' => $meeting->agendaItems,
            'tasks' => $meeting->tasks,
            'previous_meeting' => $previousMeeting,
            'candidates' => $candidates,
            'unpaid_bills' => $unpaidBills,
            'accounts' => $accounts,
            'motions' => $motions,
        ];
    }

    /**
     * Alias for compilePackData.
     */
    public function compile(ClubCommitteeMeeting $meeting): array
    {
        return $this->compilePackData($meeting);
    }

    /**
     * Compile official PDF Agenda Pack as a raw binary string.
     */
    public function compilePdf(ClubCommitteeMeeting $meeting): string
    {
        $viewData = $this->compilePackData($meeting);

        $pdf = Pdf::loadView('club-accounting.pdf.committee-agenda-pack', $viewData)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ]);

        return $pdf->output();
    }

    /**
     * Compile default plain/markdown email body summary for mobile reading.
     */
    public function compileEmailBody(ClubCommitteeMeeting $meeting): string
    {
        $club = $meeting->club;
        $dateStr = $meeting->meeting_date ? $meeting->meeting_date->format('l, jS F Y \a\t H:i') : 'Date to be confirmed';
        $chairName = $meeting->chair?->name ?? 'Worshipful Master / Chairman';
        $secretaryName = $meeting->secretary?->name ?? 'Lodge Secretary';

        $body = "Dear Brethren,\n\n";
        $body .= "Please find enclosed the official Agenda Pack for the upcoming meeting: {$meeting->title} of {$club->name}.\n\n";
        $body .= "MEETING DETAILS:\n";
        $body .= "• Meeting: {$meeting->title}\n";
        $body .= "• Date & Time: {$dateStr}\n";
        $body .= "• Location: " . ($meeting->location ?: 'Lodge Committee Room') . "\n";
        $body .= "• Chairman: {$chairName}\n";
        $body .= "• Secretary: {$secretaryName}\n\n";

        $items = $meeting->agendaItems;
        if ($items->isNotEmpty()) {
            $body .= "ORDER OF BUSINESS / AGENDA:\n";
            foreach ($items as $item) {
                $type = $item->item_type?->label() ?? 'General';
                $body .= "{$item->order}. {$item->title} [{$type}]\n";
                if ($item->description) {
                    $body .= "   Details: {$item->description}\n";
                }
                if ($item->recommendation_text) {
                    $body .= "   Recommendation: {$item->recommendation_text}\n";
                }
            }
            $body .= "\n";
        }

        $tasks = $meeting->tasks;
        if ($tasks->isNotEmpty()) {
            $body .= "OUTSTANDING ACTION POINTS:\n";
            foreach ($tasks as $task) {
                $assignee = $task->assigned_to_name ?: ($task->assignedTo?->name ?? 'Unassigned');
                $body .= "• [ ] {$task->title} (Assigned: {$assignee})\n";
            }
            $body .= "\n";
        }

        $body .= "Please review the attached briefing document prior to our assembly. If you are unable to attend, kindly submit your apologies to the Secretary as soon as possible.\n\n";
        $body .= "Yours fraternally,\n";
        $body .= "{$secretaryName}\n";
        $body .= "Secretary, {$club->name}\n";

        return $body;
    }

    /**
     * Dispatch Agenda Pack emails to selected members with optional PDF attachment.
     */
    public function dispatchPack(
        ClubCommitteeMeeting $meeting,
        array $recipientMemberIds,
        string $emailSubject,
        string $customEmailBody,
        bool $attachPdf = true
    ): int {
        if (empty($recipientMemberIds)) {
            return 0;
        }

        $pdfBinary = null;
        $pdfFilename = null;
        if ($attachPdf) {
            $pdfBinary = $this->compilePdf($meeting);
            $pdfFilename = 'Agenda-Pack-' . Str::slug($meeting->title) . '-' . ($meeting->meeting_date ? $meeting->meeting_date->format('Y-m-d') : 'meeting') . '.pdf';
        }

        $recipients = User::whereIn('id', $recipientMemberIds)->get();
        $dispatchedCount = 0;

        foreach ($recipients as $recipient) {
            try {
                Mail::to($recipient->email)->queue(new CommitteeAgendaPackMailable(
                    meeting: $meeting,
                    recipient: $recipient,
                    customSubject: $emailSubject,
                    customBodyHtml: $customEmailBody,
                    pdfBinary: $pdfBinary,
                    pdfFilename: $pdfFilename,
                ));
                $dispatchedCount++;
            } catch (\Throwable $e) {
                Log::error("Failed to queue agenda pack email to {$recipient->email}: " . $e->getMessage());
            }
        }

        // Update pack_sent_at timestamp on attendee records
        ClubCommitteeAttendee::where('committee_meeting_id', $meeting->id)
            ->whereIn('user_id', $recipientMemberIds)
            ->update(['pack_sent_at' => Carbon::now()]);

        return $dispatchedCount;
    }
}
