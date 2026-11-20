<?php

namespace App\Services;

use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The lodge's annual return of members to its Grand Lodge and Province for one financial year: who was a member at the
 * start and end, who was initiated, joined, resigned, was excluded or died in the year, and the dues owed for each
 * subscribing member at the year end (Grand Lodge, Province and Festival rates). Honorary members are listed but owe
 * nothing. A member's start is their joining date, else their initiation date, else "before records began"; a leaver
 * whose date was never recorded is listed apart so the Secretary can fill it in.
 */
class AnnualReturnOfMembers
{
    /**
     * @return array<string, mixed>
     */
    public function build(Club $club, int $year): array
    {
        [$start, $end] = $club->financialYearRange($year);
        $members = Member::where('club_id', $club->id)
            ->where('membership_status', '!=', MembershipStatus::Historical->value)
            ->orderBy('last_name')->orderBy('first_name')->get();

        $joined = fn (Member $member): ?CarbonInterface => $member->date_of_joining ?? $member->date_of_initiation;
        $hasLeft = fn (Member $member): bool => $member->membership_status->hasLeft();
        $memberOn = fn (Member $member, CarbonInterface $day): bool => ($joined($member) === null || $joined($member)->lte($day))
            && (! $hasLeft($member) || ($member->left_on !== null && $member->left_on->gt($day)));
        $inYear = fn (?CarbonInterface $date): bool => $date !== null && $date->between($start, $end);

        $atEnd = $members->filter(fn (Member $member) => $memberOn($member, $end));
        $honorary = $atEnd->filter(fn (Member $member) => $member->membership_status === MembershipStatus::Honorary);
        $subscribing = $atEnd->reject(fn (Member $member) => $member->membership_status === MembershipStatus::Honorary);
        $leavers = $members->filter(fn (Member $member) => $hasLeft($member) && $inYear($member->left_on));

        $rates = [
            'grand_lodge' => $club->province?->grandLodge?->per_capita_rate,
            'province' => $club->province?->per_capita_rate,
            'festival' => $club->province?->festival_contribution_rate,
        ];
        $dues = collect($rates)->map(fn ($rate) => $rate === null ? null : round((float) $rate * $subscribing->count(), 2));

        return [
            'club' => $club,
            'year' => $year,
            'start' => $start,
            'end' => $end,
            'grand_lodge_name' => $club->province?->grandLodge?->name,
            'province_name' => $club->province?->name,
            'counts' => [
                'at_start' => $members->filter(fn (Member $member) => $memberOn($member, $start->copy()->subDay()))->count(),
                'initiated' => $members->filter(fn (Member $member) => $inYear($member->date_of_initiation))->count(),
                'joined' => $members->filter(fn (Member $member) => $inYear($member->date_of_joining) && ! $inYear($member->date_of_initiation))->count(),
                'left' => $leavers->count(),
                'at_end' => $atEnd->count(),
                'subscribing' => $subscribing->count(),
                'honorary' => $honorary->count(),
            ],
            'rates' => array_map(fn ($rate) => $rate === null ? null : (float) $rate, $rates),
            'dues' => $dues->all(),
            'dues_total' => $dues->filter(fn ($amount) => $amount !== null)->isEmpty() ? null : round((float) $dues->sum(), 2),
            'initiated' => $this->rows($members->filter(fn (Member $member) => $inYear($member->date_of_initiation)), fn (Member $m) => $m->date_of_initiation),
            'joined' => $this->rows($members->filter(fn (Member $member) => $inYear($member->date_of_joining) && ! $inYear($member->date_of_initiation)), fn (Member $m) => $m->date_of_joining),
            'leavers' => $leavers->map(fn (Member $member) => ['name' => $member->formatted_rank_name, 'number' => $member->grand_lodge_number, 'date' => $member->left_on?->format('j M Y'), 'reason' => $member->membership_status->label()])->values()->all(),
            'left_undated' => $members->filter(fn (Member $member) => $hasLeft($member) && $member->left_on === null)->map(fn (Member $member) => $member->formatted_rank_name)->values()->all(),
            'subscribing_members' => $this->rows($subscribing, $joined),
            'honorary_members' => $this->rows($honorary, $joined),
        ];
    }

    /**
     * @param  Collection<int, Member>  $members
     * @param  callable(Member): ?CarbonInterface  $date
     * @return list<array{name: string, number: ?string, date: ?string}>
     */
    private function rows(Collection $members, callable $date): array
    {
        return $members->map(fn (Member $member) => ['name' => (string) $member->formatted_rank_name, 'number' => $member->grand_lodge_number, 'date' => $date($member)?->format('j M Y')])->values()->all();
    }
}
