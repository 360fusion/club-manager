<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Models\Member;
use App\Mail\MeetingSummonsMail;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubType;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Problems found by the usability walkthroughs of a new secretary, a member on a phone and a visiting Mason.
 */
class WalkthroughFixesTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => ['meetings'], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Apollo Lodge', 'slug' => 'apollo', 'status' => 'active']);
        $this->admin = $this->makeClubAdmin(User::factory()->create(), $this->club);
    }

    private function join(string $role, string $status = 'active', array $pivot = []): User
    {
        $user = User::factory()->create();
        $this->club->users()->attach($user->id, ['role' => $role, 'status' => $status] + $pivot);

        return $user;
    }

    private function meeting(array $attributes = []): Meeting
    {
        return Meeting::create($attributes + ['club_id' => $this->club->id, 'title' => 'Regular', 'meeting_date' => now()->addWeeks(2)->toDateString(), 'starts_at' => '18:30', 'venue' => 'Hall', 'dress_code' => 'Dark suit', 'status' => 'draft']);
    }

    public function test_a_new_lodge_can_save_its_settings_with_the_default_roles_and_permissions(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update', $this->club->slug), [
            'permission_matrix' => config('club_permissions.capabilities'),
        ])->assertSessionHasNoErrors();
    }

    public function test_the_summons_goes_to_every_active_member_and_visitor_and_later_to_whoever_joins(): void
    {
        Mail::fake();
        $member = $this->join('member');
        $visitor = $this->join('visitor');
        $invited = $this->join('member', 'pending', ['invitation_token' => 'hash']);
        $meeting = $this->meeting();

        $this->actingAs($this->admin)->post(route('admin.meetings.publish', ['clubSlug' => $this->club->slug, 'id' => $meeting->id]))->assertSessionHas('success');

        Mail::assertQueuedCount(3);
        foreach ([$this->admin, $member, $visitor] as $person) {
            Mail::assertQueued(MeetingSummonsMail::class, fn ($mail) => $mail->hasTo($person->email));
        }
        Mail::assertNotQueued(MeetingSummonsMail::class, fn ($mail) => $mail->hasTo($invited->email));

        // Publishing again sends only to people who have not had it: the invite accepted since.
        $this->club->users()->updateExistingPivot($invited->id, ['status' => 'active', 'invitation_token' => null]);
        $this->actingAs($this->admin)->post(route('admin.meetings.publish', ['clubSlug' => $this->club->slug, 'id' => $meeting->id]));
        Mail::assertQueuedCount(4);
        Mail::assertQueued(MeetingSummonsMail::class, fn ($mail) => $mail->hasTo($invited->email));
    }

    public function test_approving_a_visitor_sends_the_summonses_already_out(): void
    {
        $meeting = $this->meeting(['status' => 'published']);
        $this->meeting(['status' => 'published', 'meeting_date' => now()->subWeek()->toDateString()]);
        $visitor = $this->join('visitor', 'pending', ['home_club_name' => 'Etruscan Lodge', 'home_club_number' => '546']);
        Mail::fake();

        $this->actingAs($this->admin)->get(route('admin.users.index', $this->club->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('pendingMembers.0.visiting_from', 'Etruscan Lodge No. 546'));

        $this->actingAs($this->admin)->post(route('clubs.members.approve', ['slug' => $this->club->slug, 'userId' => $visitor->id]));

        Mail::assertQueuedCount(1);
        Mail::assertQueued(MeetingSummonsMail::class, fn ($mail) => $mail->hasTo($visitor->email) && $mail->meeting->is($meeting));
    }

    public function test_the_summons_does_not_invent_officers_a_master_or_contact_details(): void
    {
        $meeting = $this->meeting();

        $this->actingAs($this->admin)->get(route('admin.meetings.pdf', ['clubSlug' => $this->club->slug, 'id' => $meeting->id]))
            ->assertOk()
            ->assertDontSee('07718 903347')
            ->assertDontSee('secretary@')
            ->assertDontSee('>MASTER<', false)
            ->assertDontSee('6:00pm');

        $this->actingAs($this->admin)->get(route('admin.meetings.edit', ['clubSlug' => $this->club->slug, 'id' => $meeting->id]))
            ->assertDontSee('K. D. Lord')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('lodgeOfficers', []));
    }

    public function test_visitors_are_not_staff_and_staff_are_pointed_at_real_requests(): void
    {
        $this->join('member', 'pending', ['invitation_token' => 'hash']);
        $visitor = $this->join('visitor');

        $this->actingAs($visitor)->get('/members/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('inbox', 0)->where('clubs.0.is_staff', false));
        $this->actingAs($this->admin)->get('/members/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('inbox', 0));

        $this->join('visitor', 'pending');
        $this->actingAs($this->admin)->get('/members/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('inbox.0.kind', 'approvals')->where('inbox.0.url', route('admin.users.index', $this->club->slug, false)));
    }

    public function test_a_new_lodge_dashboard_lists_its_first_steps_until_they_are_done(): void
    {
        $this->actingAs($this->admin)->get(route('admin.analytics', ['slug' => $this->club->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('gettingStarted', ['members' => false, 'payments' => false, 'meetings' => false]));

        foreach (['Chris', 'Neil'] as $name) {
            Member::create(['club_id' => $this->club->id, 'first_name' => $name, 'last_name' => 'Brother', 'masonic_rank' => 'Bro', 'membership_status' => 'active', 'current_office' => 'member']);
        }
        ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => ClubPaymentMethod::DOOR, 'label' => 'Pay on the night', 'is_active' => true, 'config' => []]);
        $this->meeting();

        $this->actingAs($this->admin)->get(route('admin.analytics', ['slug' => $this->club->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('gettingStarted', ['members' => true, 'payments' => true, 'meetings' => true]));
    }

    public function test_only_an_owner_is_offered_the_owner_role_and_a_role_change_reads_plainly(): void
    {
        $owner = $this->join('owner');
        $member = $this->join('member');

        $this->actingAs($this->admin)->get(route('admin.users.index', ['clubSlug' => $this->club->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canGrantOwner', false));
        $this->actingAs($owner)->get(route('admin.users.index', ['clubSlug' => $this->club->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canGrantOwner', true));

        $this->actingAs($owner)->post(route('admin.users.role.update', ['clubSlug' => $this->club->slug, 'userId' => $member->id]), ['role' => 'coach'])
            ->assertSessionHas('success', 'Role changed to Secretary.');
    }
}
