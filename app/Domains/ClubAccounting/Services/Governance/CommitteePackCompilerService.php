<?php

namespace App\Domains\ClubAccounting\Services\Governance;

use App\Domains\ClubAccounting\Enums\CommitteeMeetingStatus;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Club;
use App\Models\User;

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
        $candidates = User::whereHas('clubs', function ($q) use ($club) {
            $q->where('clubs.id', $club->id)
              ->whereIn('role', ['candidate', 'applicant', 'enquirer']);
        })->get(['id', 'name', 'email', 'created_at']);

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
}
