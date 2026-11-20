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
    /** The usual progression through the offices to the Chair; most lodges follow it, though the lodge decides. */
    public const PROGRESSION = [
        LodgeOffice::Steward, LodgeOffice::InnerGuard, LodgeOffice::JuniorDeacon, LodgeOffice::SeniorDeacon,
        LodgeOffice::JuniorWarden, LodgeOffice::SeniorWarden, LodgeOffice::WorshipfulMaster,
    ];

    public function __construct(private SigningInBook $book, private MasonicAnniversaries $anniversaries) {}

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
        $before = $this->book->attendance($club, now()->subYears(2), now()->subYear()->subDay());
        $attendedBefore = collect($before['members'])->firstWhere('user_id', $user->id);
        [$upcoming, $visits] = MemberVisit::where('user_id', $user->id)->orderByDesc('visited_on')->get(['lodge_name', 'visited_on'])
            ->partition(fn (MemberVisit $visit) => $visit->visited_on->isAfter(today()));
        $currentOffice = LodgeOffice::tryFrom((string) $member->getRawOriginal('current_office'));
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
            'path' => $this->path($member, $currentOffice, $offices->pluck('office')->all()),
            'year' => [
                'meetings' => $attendance && $book['meetings'] > 0 ? $attendance['attended'] : null,
                'meetings_before' => $attendedBefore && $before['meetings'] > 0 ? $attendedBefore['attended'] : null,
                'visits' => $visits->filter(fn (MemberVisit $visit) => $visit->visited_on->gte(now()->subYear()))->count(),
                'visits_before' => $visits->filter(fn (MemberVisit $visit) => $visit->visited_on->between(now()->subYears(2), now()->subYear()))->count(),
                'next_anniversary' => $this->anniversaries->nextFor($member),
            ],
        ];
    }

    /**
     * Where the brother is on his way: the three degrees, then the usual offices to the Chair, with his place and the
     * usual next step. Past Masters (anyone recorded as Master) have completed it.
     *
     * @param  list<string>  $officesHeld  labels of the offices he has held
     * @return array<string, mixed>
     */
    private function path(Member $member, ?LodgeOffice $current, array $officesHeld): array
    {
        $degrees = [
            ['label' => 'Initiated', 'done' => $member->date_of_initiation !== null],
            ['label' => 'Passed', 'done' => $member->date_of_passing !== null],
            ['label' => 'Raised', 'done' => $member->date_of_raising !== null],
        ];
        $held = fn (LodgeOffice $office) => in_array($office->label(), $officesHeld, true);
        $pastMaster = $held(LodgeOffice::WorshipfulMaster) || $current === LodgeOffice::IPM;
        $place = $current && in_array($current, self::PROGRESSION, true) ? array_search($current, self::PROGRESSION, true) : null;

        $next = match (true) {
            ! $degrees[0]['done'] => null,
            ! $degrees[1]['done'] => 'Passing to the Second Degree',
            ! $degrees[2]['done'] => 'Raising to the Third Degree',
            $current === LodgeOffice::WorshipfulMaster => 'Immediate Past Master',
            $pastMaster => null,
            $place !== null => self::PROGRESSION[$place + 1]->label(),
            default => null,
        };

        return [
            'degrees' => $degrees,
            'offices' => array_map(fn (LodgeOffice $office, int $index) => [
                'label' => $office->label(),
                'state' => match (true) {
                    $office === $current => 'now',
                    $held($office) || $pastMaster || ($place !== null && $index < $place) => 'done',
                    default => 'to_come',
                },
            ], self::PROGRESSION, array_keys(self::PROGRESSION)),
            'past_master' => $pastMaster,
            'next' => $next,
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
