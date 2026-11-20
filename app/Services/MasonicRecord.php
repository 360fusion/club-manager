<?php

namespace App\Services;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\AnnualOfficerAssignment;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\MemberVisit;
use App\Models\User;
use App\Services\Meetings\SigningInBook;
use Carbon\CarbonInterface;

/**
 * A brother's own Masonic record in one lodge: his degrees and joining, ranks, the offices he has held (from the
 * confirmed officer rosters), his attendance from the signing-in book and the visits he has recorded. Read-only: the
 * Secretary keeps the record.
 */
class MasonicRecord
{
    public function __construct(private SigningInBook $book) {}

    /**
     * @return array<string, mixed>|null null when the lodge has no roster record for this person yet
     */
    public function for(Club $club, User $user): ?array
    {
        $member = Member::where('club_id', $club->id)->where('user_id', $user->id)->first();
        if (! $member) {
            return null;
        }

        $milestones = collect([
            ['label' => 'Initiated', 'date' => $member->date_of_initiation],
            ['label' => 'Passed', 'date' => $member->date_of_passing],
            ['label' => 'Raised', 'date' => $member->date_of_raising],
            ['label' => 'Joined this lodge', 'date' => $member->date_of_joining],
        ])->filter(fn (array $row) => $row['date'] !== null)
            ->map(fn (array $row) => [
                'label' => $row['label'],
                'date' => $row['date']->format('j F Y'),
                'years' => (int) $row['date']->diffInYears(now()),
                'anniversary_soon' => $this->anniversaryWithin($row['date'], 31),
            ])->values();

        $offices = AnnualOfficerAssignment::where('member_id', $member->id)
            ->whereHas('roster', fn ($roster) => $roster->where('club_id', $club->id)->where('status', 'confirmed'))
            ->with('roster')->get()
            ->map(fn (AnnualOfficerAssignment $assignment) => [
                'office' => LodgeOffice::tryFrom((string) $assignment->office)?->label() ?? (string) $assignment->office,
                'year' => $assignment->roster->masonic_year ?: trim($assignment->roster->start_year.'–'.$assignment->roster->end_year, '–'),
                'sort' => (int) $assignment->roster->start_year,
            ])
            ->reject(fn (array $row) => in_array($row['office'], [LodgeOffice::Member->label(), LodgeOffice::CommitteeMember->label()], true))
            ->sortByDesc('sort')->map(fn (array $row) => collect($row)->except('sort')->all())->values();

        $book = $this->book->attendance($club, now()->subYear());
        $attendance = collect($book['members'])->firstWhere('user_id', $user->id);
        [$upcoming, $visits] = MemberVisit::where('user_id', $user->id)->orderByDesc('visited_on')->get(['lodge_name', 'visited_on'])
            ->partition(fn (MemberVisit $visit) => $visit->visited_on->isAfter(today()));
        $visitRow = fn (MemberVisit $visit) => ['lodge' => $visit->lodge_name, 'date' => $visit->visited_on->format('j M Y')];

        return [
            'name' => $member->formatted_rank_name,
            'grand_lodge_number' => $member->grand_lodge_number,
            'ranks' => array_values(array_filter([
                $member->grand_rank ? ['label' => 'Grand rank', 'value' => $member->grand_rank] : null,
                $member->provincial_rank ? ['label' => 'Provincial rank', 'value' => $member->provincial_rank] : null,
            ])),
            'milestones' => $milestones->all(),
            'offices' => $offices->all(),
            'current_office' => ($office = LodgeOffice::tryFrom((string) $member->getRawOriginal('current_office'))) && ! in_array($office, [LodgeOffice::Member, LodgeOffice::CommitteeMember], true) ? $office->label() : null,
            // Only meetings where the signing-in book was used count, so a lodge that doesn't use it shows nothing.
            'attendance' => $attendance && $book['meetings'] > 0 ? ['attended' => $attendance['attended'], 'meetings' => $book['meetings'], 'last' => $attendance['last_attended']] : null,
            'visits' => ['count' => $visits->count(), 'recent' => $visits->take(5)->map($visitRow)->values()->all(), 'upcoming' => $upcoming->reverse()->map($visitRow)->values()->all()],
        ];
    }

    private function anniversaryWithin(CarbonInterface $date, int $days): bool
    {
        $next = $date->copy()->year((int) now()->year);
        if ($next->lt(now()->startOfDay())) {
            $next = $next->addYear();
        }

        return $next->diffInDays(now()->startOfDay(), true) <= $days;
    }
}
