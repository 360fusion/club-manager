<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\ClubLodgeLink;
use App\Models\Lodge;
use App\Models\MasonicHall;
use App\Models\User;
use App\Services\PassedOn\LodgeLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassOnListsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private MasonicHall $hall;

    private User $secretary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hall = MasonicHall::factory()->create();
        $this->club = Club::factory()->create();
        Lodge::factory()->create(['club_id' => $this->club->id, 'masonic_hall_id' => $this->hall->id]);

        // A plain member who holds the Secretary office, not the secretary role.
        $this->secretary = User::factory()->create();
        $this->club->users()->attach($this->secretary->id, ['role' => 'member', 'status' => 'active']);
        Member::create(['club_id' => $this->club->id, 'user_id' => $this->secretary->id, 'first_name' => 'Alan', 'last_name' => 'Smith', 'membership_status' => 'active', 'current_office' => 'secretary']);
    }

    public function test_sister_lodges_start_as_the_other_lodges_in_the_hall(): void
    {
        $sisters = Lodge::factory()->count(2)->create(['masonic_hall_id' => $this->hall->id]);
        Lodge::factory()->create();

        $this->assertEqualsCanonicalizing($sisters->pluck('id')->all(), app(LodgeLinks::class)->lodges($this->club, 'sister')->pluck('id')->all());
        $this->assertCount(0, app(LodgeLinks::class)->lodges($this->club, 'reciprocal'));
    }

    public function test_the_secretary_can_untick_a_hall_lodge_and_add_others_to_either_list(): void
    {
        $inHall = Lodge::factory()->create(['masonic_hall_id' => $this->hall->id]);
        $elsewhere = Lodge::factory()->create();
        $friend = Lodge::factory()->create();
        $update = fn (Lodge $lodge, string $relation, string $action) => $this->actingAs($this->secretary)
            ->post(route('member.pass_on.lists.update', $this->club->slug), ['lodge_slug' => $lodge->slug, 'relation' => $relation, 'action' => $action])
            ->assertSessionHasNoErrors();

        $update($inHall, 'sister', 'remove');
        $update($elsewhere, 'sister', 'add');
        $update($friend, 'reciprocal', 'add');

        $links = app(LodgeLinks::class);
        $this->assertSame([$elsewhere->id], $links->lodges($this->club, 'sister')->pluck('id')->all());
        $this->assertSame([$friend->id], $links->lodges($this->club, 'reciprocal')->pluck('id')->all());
        $this->assertTrue(ClubLodgeLink::where('lodge_id', $inHall->id)->value('excluded'));

        $update($inHall, 'sister', 'add');
        $this->assertTrue($links->lodges($this->club, 'sister')->contains('id', $inHall->id));

        $this->actingAs($this->secretary)->get(route('member.pass_on.lists', $this->club->slug))
            ->assertInertia(fn ($page) => $page->component('Member/PassOn/Lists')->has('sister', 2)->has('reciprocal', 1)->has('hall.lodges', 1));
    }

    public function test_a_plain_member_cannot_see_or_change_the_lists(): void
    {
        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $lodge = Lodge::factory()->create();

        $this->actingAs($member)->get(route('member.pass_on.lists', $this->club->slug))->assertForbidden();
        $this->actingAs($member)->post(route('member.pass_on.lists.update', $this->club->slug), ['lodge_slug' => $lodge->slug, 'relation' => 'reciprocal', 'action' => 'add'])->assertForbidden();
        $this->assertSame(0, ClubLodgeLink::count());
    }

    public function test_lists_are_kept_per_lodge(): void
    {
        $other = Club::factory()->create();
        Lodge::factory()->create(['club_id' => $other->id, 'masonic_hall_id' => $this->hall->id]);
        $sister = Lodge::factory()->create(['masonic_hall_id' => $this->hall->id]);
        app(LodgeLinks::class)->remove($other, $sister, 'sister');

        $this->assertTrue(app(LodgeLinks::class)->lodges($this->club, 'sister')->contains('id', $sister->id));
        $this->assertFalse(app(LodgeLinks::class)->lodges($other, 'sister')->contains('id', $sister->id));
    }

    public function test_clubs_listing_a_lodge_follow_the_hall_and_the_hand_made_lists(): void
    {
        $sister = Lodge::factory()->create(['masonic_hall_id' => $this->hall->id]);
        $farAway = Club::factory()->create();
        app(LodgeLinks::class)->add($farAway, $sister, 'reciprocal');

        $listing = app(LodgeLinks::class)->clubsListing($sister)->mapWithKeys(fn (array $row) => [$row['club']->id => $row['relation']])->all();

        $this->assertSame([$this->club->id => 'sister', $farAway->id => 'reciprocal'], $listing);
    }
}
