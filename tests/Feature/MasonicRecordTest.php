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
}
