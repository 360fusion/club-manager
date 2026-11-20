<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\GrandLodge;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnualReturnOfMembersTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $treasurer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-20 12:00:00');
        $grandLodge = GrandLodge::create(['name' => 'United Grand Lodge of England', 'code' => 'ugle', 'country' => 'England', 'per_capita_rate' => 40]);
        $province = Province::create(['grand_lodge_id' => $grandLodge->id, 'name' => 'Cornwall', 'code' => 'cornwall', 'per_capita_rate' => 15, 'festival_contribution_rate' => 5]);
        $this->club = Club::factory()->create(['province_id' => $province->id]);
        $this->treasurer = User::factory()->create();
        $this->club->users()->attach($this->treasurer->id, ['role' => 'treasurer', 'status' => 'active']);
    }

    private function member(string $first, MembershipStatus $status, array $extra = []): Member
    {
        return Member::create($extra + ['club_id' => $this->club->id, 'first_name' => $first, 'last_name' => 'Smith', 'membership_status' => $status]);
    }

    public function test_the_return_counts_movements_in_the_year_and_the_dues_for_each_subscribing_member(): void
    {
        // Financial year 2026 with the default April year end: 1 May 2025 to 30 April 2026.
        $this->member('Old', MembershipStatus::Active, ['date_of_joining' => '2010-03-01']);
        $this->member('Newly', MembershipStatus::Active, ['date_of_initiation' => '2025-10-10']);
        $this->member('Joiner', MembershipStatus::Active, ['date_of_joining' => '2026-02-02', 'date_of_initiation' => '1999-01-01']);
        $this->member('Honour', MembershipStatus::Honorary, ['date_of_joining' => '1980-01-01']);
        $this->member('Gone', MembershipStatus::Resigned, ['date_of_joining' => '2001-01-01', 'left_on' => '2026-01-15']);
        $this->member('Later', MembershipStatus::Deceased, ['date_of_joining' => '2001-01-01', 'left_on' => '2026-08-01']);
        $this->member('Undated', MembershipStatus::Resigned, ['date_of_joining' => '2001-01-01']);
        Member::where('first_name', 'Undated')->update(['left_on' => null]);

        $response = $this->actingAs($this->treasurer)->get(route('admin.accounting.annual_return', ['clubSlug' => $this->club->slug, 'year' => 2026]))->assertOk();

        $this->assertSame([
            'at_start' => 4,      // Old, Honour, Gone, Later
            'initiated' => 1,     // Newly
            'joined' => 1,        // Joiner
            'left' => 1,          // Gone
            'at_end' => 5,        // Old, Newly, Joiner, Honour, Later (who died after the year)
            'subscribing' => 4,
            'honorary' => 1,
        ], $response->viewData('counts'));
        $this->assertSame(['grand_lodge' => 160.0, 'province' => 60.0, 'festival' => 20.0], $response->viewData('dues'));
        $this->assertSame(240.0, $response->viewData('dues_total'));
        $response->assertSee('No leaving date is recorded for');
    }

    public function test_a_member_who_leaves_is_stamped_with_the_day_and_unstamped_if_they_come_back(): void
    {
        $member = $this->member('Tom', MembershipStatus::Active);

        $member->update(['membership_status' => MembershipStatus::Resigned]);
        $this->assertSame('2026-11-20', $member->fresh()->left_on->toDateString());

        $member->update(['membership_status' => MembershipStatus::Active]);
        $this->assertNull($member->fresh()->left_on);
    }

    public function test_only_people_who_may_see_the_accounts_can_open_it(): void
    {
        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($member)->get(route('admin.accounting.annual_return', ['clubSlug' => $this->club->slug]))->assertForbidden();
        $this->actingAs($this->treasurer)->get(route('admin.accounting.annual_return', ['clubSlug' => $this->club->slug, 'download' => 1]))->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
