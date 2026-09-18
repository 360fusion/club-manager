<?php

namespace App\Domains\ClubAccounting\Services;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\AnnualOfficerAssignment;
use App\Domains\ClubAccounting\Models\AnnualOfficerRoster;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AnnualOfficerRosterService
{
    /**
     * Validate officer assignment rules:
     * - A member can have at most ONE progressive office.
     * - A member can have MULTIPLE administrative offices.
     */
    public function validateAssignments(array $assignments): void
    {
        $progressiveMemberCounts = [];

        foreach ($assignments as $item) {
            $memberId = (int) ($item['member_id'] ?? 0);
            $officeCode = $item['office'] ?? '';
            $officeEnum = LodgeOffice::tryFrom($officeCode);

            if (! $officeEnum || ! $memberId) {
                continue;
            }

            if ($officeEnum->isProgressive()) {
                $progressiveMemberCounts[$memberId] = ($progressiveMemberCounts[$memberId] ?? 0) + 1;

                if ($progressiveMemberCounts[$memberId] > 1) {
                    $member = Member::find($memberId);
                    $memberName = $member ? $member->full_name : "Member #{$memberId}";
                    throw new InvalidArgumentException("{$memberName} cannot hold more than one Progressive Office on the ladder.");
                }
            }
        }
    }

    /**
     * Save or update an annual officer roster draft/proposal.
     */
    public function saveRoster(Club $club, string $masonicYear, array $assignments, ?int $meetingId = null, string $status = 'draft', ?string $notes = null): AnnualOfficerRoster
    {
        $this->validateAssignments($assignments);

        $startYear = null;
        $endYear = null;
        $startDate = null;
        $endDate = null;

        $parts = explode('-', trim($masonicYear));
        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            $startYear = (int) $parts[0];
            $endYear = (int) $parts[1];

            if ($endYear !== $startYear + 1) {
                throw new InvalidArgumentException("Invalid Masonic Year. End year ({$endYear}) must be consecutive to start year ({$startYear}).");
            }

            $installationMonthName = $club->settings['installation_month'] ?? 'October';
            $monthNum = (int) date('m', strtotime("1 {$installationMonthName} 2026")) ?: 10;

            $startDate = Carbon::createFromDate($startYear, $monthNum, 1)->startOfDay();
            $endDate = (clone $startDate)->addYear()->subDay()->endOfDay();
        }

        return DB::transaction(function () use ($club, $masonicYear, $startYear, $endYear, $startDate, $endDate, $assignments, $meetingId, $status, $notes) {
            $roster = AnnualOfficerRoster::updateOrCreate(
                [
                    'club_id' => $club->id,
                    'masonic_year' => $masonicYear,
                ],
                [
                    'meeting_id' => $meetingId,
                    'status' => $status,
                    'start_year' => $startYear,
                    'end_year' => $endYear,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'notes' => $notes,
                ]
            );

            // Delete previous assignments for this roster
            $roster->assignments()->delete();

            // Insert new assignments
            foreach ($assignments as $item) {
                $memberId = (int) ($item['member_id'] ?? 0);
                $officeCode = $item['office'] ?? '';
                $officeEnum = LodgeOffice::tryFrom($officeCode);

                if (! $officeEnum || ! $memberId) {
                    continue;
                }

                AnnualOfficerAssignment::create([
                    'roster_id' => $roster->id,
                    'member_id' => $memberId,
                    'office' => $officeEnum->value,
                    'category' => $officeEnum->category(),
                ]);
            }

            // Sync committee_role on club_user pivot table for linked members
            $committeeMemberIds = collect($assignments)
                ->where('office', 'committee_member')
                ->pluck('member_id')
                ->all();

            $committeeUserIds = Member::where('club_id', $club->id)
                ->whereIn('id', $committeeMemberIds)
                ->whereNotNull('user_id')
                ->pluck('user_id')
                ->all();

            foreach ($committeeUserIds as $uId) {
                $currentRole = DB::table('club_user')
                    ->where('club_id', $club->id)
                    ->where('user_id', $uId)
                    ->value('committee_role');

                if (! in_array($currentRole, ['chair', 'secretary'])) {
                    DB::table('club_user')
                        ->where('club_id', $club->id)
                        ->where('user_id', $uId)
                        ->update(['committee_role' => 'member']);
                }
            }

            // Clear 'member' role for users in this club who are no longer assigned as committee members
            DB::table('club_user')
                ->where('club_id', $club->id)
                ->where('committee_role', 'member')
                ->whereNotIn('user_id', $committeeUserIds)
                ->update(['committee_role' => null]);

            return $roster->fresh(['assignments.member']);
        });
    }

    /**
     * Confirm roster and sync active current_office fields on Member records.
     */
    public function confirmRoster(AnnualOfficerRoster $roster): AnnualOfficerRoster
    {
        return DB::transaction(function () use ($roster) {
            $roster->update([
                'status' => 'confirmed',
                'confirmed_at' => Carbon::now(),
            ]);

            // Group assignments by member
            $memberAssignments = $roster->assignments()->get()->groupBy('member_id');

            foreach ($memberAssignments as $memberId => $assignments) {
                $member = Member::find($memberId);
                if (! $member) {
                    continue;
                }

                // Primary office preference: Progressive office first, then first Administrative office in order of insertion
                $orderedAssignments = $assignments->sortBy('id');
                $progressive = $orderedAssignments->first(fn ($a) => $a->lodge_office?->isProgressive());
                $primaryOffice = $progressive ? $progressive->office : ($orderedAssignments->first()?->office ?? 'member');

                $member->update([
                    'current_office' => $primaryOffice,
                ]);
            }

            return $roster->fresh(['assignments.member']);
        });
    }

    /**
     * Automatically install confirmed rosters if the Installation Meeting has passed.
     */
    public function checkAndAutoInstallPassedInstallationMeetings(Club $club): void
    {
        $installationMonthName = $club->settings['installation_month'] ?? 'October';

        // Find meetings in the installation month that have passed
        $passedInstallationMeetings = \App\Models\Meeting::where('club_id', $club->id)
            ->where('meeting_date', '<', Carbon::today())
            ->get()
            ->filter(function ($meeting) use ($installationMonthName) {
                return Carbon::parse($meeting->meeting_date)->format('F') === $installationMonthName;
            });

        foreach ($passedInstallationMeetings as $meeting) {
            $year = Carbon::parse($meeting->meeting_date)->format('Y');
            $nextYear = Carbon::parse($meeting->meeting_date)->year + 1;
            $masonicYear = "{$year}-{$nextYear}";

            $roster = AnnualOfficerRoster::where('club_id', $club->id)
                ->where('masonic_year', $masonicYear)
                ->whereIn('status', ['confirmed', 'proposed'])
                ->first();

            if ($roster) {
                AnnualOfficerRoster::where('club_id', $club->id)
                    ->where('status', 'installed')
                    ->where('id', '!=', $roster->id)
                    ->update(['status' => 'confirmed']);

                $installed = $this->confirmRoster($roster);
                $installed->update(['status' => 'installed']);
            }
        }
    }
}
