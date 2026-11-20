<?php

namespace App\Services;

use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Masonic anniversaries the lodge may want to mark: years since a brother's initiation ("a Freemason") and since he
 * joined this lodge, with the round numbers highlighted (a 50-year certificate, 60 years with Province...). Worked out
 * from the roster's dates; the lodge decides what to mark.
 */
class MasonicAnniversaries
{
    /** Years that are usually marked. */
    public const MILESTONES = [10, 21, 25, 30, 40, 50, 60, 65, 70, 75];

    /**
     * Every anniversary in the next `$days`, soonest first.
     *
     * @return list<array{name: string, kind: string, label: string, date: string, in_days: int, years: int, milestone: bool}>
     */
    public function upcoming(Club $club, int $days = 90): array
    {
        return Member::where('club_id', $club->id)->active()
            ->where(fn ($query) => $query->whereNotNull('date_of_initiation')->orWhereNotNull('date_of_joining'))
            ->get()
            ->flatMap(fn (Member $member) => array_filter([$this->initiation($member), $this->joining($member)]))
            ->filter(fn (array $row) => $row['in_days'] <= $days)
            ->sortBy('in_days')
            ->values()
            ->all();
    }

    /**
     * The brother's own next anniversary of his initiation, for his record.
     *
     * @return array{name: string, kind: string, label: string, date: string, in_days: int, years: int, milestone: bool}|null
     */
    public function nextFor(Member $member): ?array
    {
        return $this->initiation($member);
    }

    /** @return array<string, mixed>|null */
    private function initiation(Member $member): ?array
    {
        return $member->date_of_initiation ? $this->row($member, $member->date_of_initiation, 'initiation', fn (int $years) => "{$years} ".($years === 1 ? 'year' : 'years').' a Freemason') : null;
    }

    /**
     * Only when he joined from another lodge: otherwise it is the same day as his initiation.
     *
     * @return array<string, mixed>|null
     */
    private function joining(Member $member): ?array
    {
        $joined = $member->date_of_joining;
        if (! $joined || ($member->date_of_initiation && $joined->isSameDay($member->date_of_initiation))) {
            return null;
        }

        return $this->row($member, $joined, 'joining', fn (int $years) => "{$years} ".($years === 1 ? 'year' : 'years').' in the lodge');
    }

    /**
     * @param  callable(int): string  $label
     * @return array<string, mixed>|null
     */
    private function row(Member $member, CarbonInterface $since, string $kind, callable $label): ?array
    {
        $today = CarbonImmutable::today();
        $next = CarbonImmutable::parse($since)->setYear($today->year);
        if ($next->lt($today)) {
            $next = $next->addYear();
        }
        $years = $next->year - $since->year;
        if ($years < 1) {
            return null;
        }

        return [
            'name' => $member->formatted_rank_name,
            'kind' => $kind,
            'label' => $label($years),
            'date' => $next->format('j F Y'),
            'in_days' => (int) $today->diffInDays($next),
            'years' => $years,
            'milestone' => in_array($years, self::MILESTONES, true),
        ];
    }
}
