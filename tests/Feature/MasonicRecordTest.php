<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Models\AnnualOfficerAssignment;
use App\Domains\ClubAccounting\Models\AnnualOfficerRoster;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\MemberVisit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasonicRecordTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_sees_their_degrees_ranks_offices_held_and_visits(): void
    {
        $club = Club::factory()->create();
        $user = User::factory()->create();
        $club->users()->attach($user->id, ['role' => 'member', 'status' => 'active']);
        $member = Member::create(['club_id' => $club->id, 'user_id' => $user->id, 'first_name' => 'Tom', 'last_name' => 'Hart', 'membership_status' => 'active', 'masonic_rank' => 'WBro',
            'provincial_rank' => 'PPrJGD', 'date_of_initiation' => '2014-03-04', 'date_of_passing' => '2014-10-07', 'date_of_raising' => now()->subYears(11)->addDays(10)->toDateString()]);

        foreach ([[2024, 'sw', 'confirmed'], [2025, 'wm', 'confirmed'], [2026, 'ipm', 'draft']] as [$year, $office, $status]) {
            $roster = AnnualOfficerRoster::create(['club_id' => $club->id, 'masonic_year' => "{$year}-".($year + 1), 'start_year' => $year, 'end_year' => $year + 1, 'status' => $status]);
            AnnualOfficerAssignment::create(['roster_id' => $roster->id, 'member_id' => $member->id, 'office' => $office, 'category' => 'regular']);
        }
        MemberVisit::create(['user_id' => $user->id, 'lodge_name' => 'Unity Lodge No 5', 'visited_on' => now()->subWeek()]);

        $this->actingAs($user)->get(route('member.record', ['slug' => $club->slug]))->assertInertia(fn ($page) => $page
            ->component('Member/Record')
            ->where('record.name', 'WBro Tom Hart PPrJGD')
            ->where('record.milestones.0.label', 'Initiated')
            ->where('record.milestones.2.anniversary_soon', true)
            ->where('record.offices', [['office' => 'Worshipful Master', 'year' => '2025-2026'], ['office' => 'Senior Warden', 'year' => '2024-2025']])
            ->where('record.visits.count', 1));
    }

    public function test_someone_without_a_roster_record_is_told_to_ask_the_secretary_and_a_non_member_is_refused(): void
    {
        $club = Club::factory()->create();
        $user = User::factory()->create();
        $club->users()->attach($user->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($user)->get(route('member.record', ['slug' => $club->slug]))->assertInertia(fn ($page) => $page->where('record', null));
        $this->actingAs(User::factory()->create())->get(route('member.record', ['slug' => $club->slug]))->assertForbidden();
    }

    /** @param  array<string, mixed>  $fields */
    private function brother(Club $club, array $fields, string $role = 'member'): User
    {
        $user = User::factory()->create();
        $club->users()->attach($user->id, ['role' => $role, 'status' => 'active']);
        Member::create(['club_id' => $club->id, 'user_id' => $user->id, 'first_name' => 'Tom', 'last_name' => 'Hart', 'membership_status' => 'active', ...$fields]);

        return $user;
    }

    public function test_the_path_shows_his_place_on_the_way_to_the_chair_and_the_usual_next_step(): void
    {
        $club = Club::factory()->create();
        $raised = ['date_of_initiation' => '2020-03-04', 'date_of_passing' => '2020-06-04', 'date_of_raising' => '2020-10-04'];

        $this->actingAs($this->brother($club, [...$raised, 'current_office' => 'jd']))->get(route('member.record', $club->slug))->assertInertia(fn ($page) => $page
            ->where('record.path.degrees.2', ['label' => 'Raised', 'done' => true])
            ->where('record.path.offices.1', ['label' => 'Inner Guard', 'state' => 'done'])
            ->where('record.path.offices.2', ['label' => 'Junior Deacon', 'state' => 'now'])
            ->where('record.path.offices.3', ['label' => 'Senior Deacon', 'state' => 'to_come'])
            ->where('record.path.next', 'Senior Deacon')
            ->where('record.path.past_master', false));

        $this->actingAs($this->brother($club, ['date_of_initiation' => '2026-01-04', 'date_of_passing' => '2026-04-04']))->get(route('member.record', $club->slug))
            ->assertInertia(fn ($page) => $page->where('record.path.next', 'Raising to the Third Degree'));

        $this->actingAs($this->brother($club, [...$raised, 'current_office' => 'ipm']))->get(route('member.record', $club->slug))
            ->assertInertia(fn ($page) => $page->where('record.path.past_master', true)->where('record.path.next', null)->where('record.path.offices.6.state', 'done'));
    }

    public function test_the_year_compares_visits_with_the_year_before_and_names_his_next_anniversary(): void
    {
        $club = Club::factory()->create();
        $user = $this->brother($club, ['date_of_initiation' => now()->subYears(25)->addDays(20)->toDateString()]);
        foreach ([now()->subMonth(), now()->subMonths(3), now()->subMonths(18)] as $date) {
            MemberVisit::create(['user_id' => $user->id, 'lodge_name' => 'Unity Lodge', 'visited_on' => $date]);
        }

        $this->actingAs($user)->get(route('member.record', $club->slug))->assertInertia(fn ($page) => $page
            ->where('record.year.visits', 2)
            ->where('record.year.visits_before', 1)
            ->where('record.year.next_anniversary.label', '25 years a Freemason')
            ->where('record.year.next_anniversary.milestone', true));
    }

    public function test_the_master_secretary_and_almoner_see_the_lodges_coming_anniversaries_soonest_first(): void
    {
        $club = Club::factory()->create();
        $secretary = $this->brother($club, ['current_office' => 'secretary']);
        $this->brother($club, ['first_name' => 'Fifty', 'date_of_initiation' => now()->subYears(50)->addDays(10)->toDateString()]);
        $this->brother($club, ['first_name' => 'Seven', 'date_of_initiation' => now()->subYears(7)->addDays(5)->toDateString(), 'date_of_joining' => now()->subYears(3)->addDays(40)->toDateString()]);
        $this->brother($club, ['first_name' => 'Far', 'date_of_initiation' => now()->subYears(30)->addDays(120)->toDateString()]);
        $this->brother($club, ['first_name' => 'Gone', 'membership_status' => 'resigned', 'date_of_initiation' => now()->subYears(40)->addDays(3)->toDateString()]);

        $this->actingAs($secretary)->get(route('member.anniversaries', $club->slug))->assertInertia(fn ($page) => $page
            ->component('Member/Anniversaries')
            ->where('anniversaries', fn ($rows) => collect($rows)->map(fn ($row) => [$row['label'], $row['milestone']])->all() === [
                ['7 years a Freemason', false],
                ['50 years a Freemason', true],
                ['3 years in the lodge', false],
            ]));

        $this->actingAs($this->brother($club, []))->get(route('member.anniversaries', $club->slug))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('member.anniversaries', $club->slug))->assertForbidden();
    }
}
