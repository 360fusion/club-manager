<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\ClubVisitorAccess;
use App\Models\Lodge;
use App\Models\LodgeSchedule;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\ClubNotification;
use App\Services\ClubNotifier;
use App\Support\VisitorSummons;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LodgeVisitorTest extends TestCase
{
    use RefreshDatabase;

    private const SECRETS = ['SECRET-SICK-LIST', 'SECRET-ALMONER', 'SECRET-BANK-123456', 'SECRET-PAYLINK', 'SECRET-HONORARY', 'SECRET-INTRO', 'SECRET-ROSTER', 'SECRET-PROVINCIAL', 'SECRET-VISITS'];

    private Club $club;

    private Lodge $lodge;

    private Meeting $meeting;

    private User $member;

    private User $secretary;

    private User $visitor;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-09-24 12:00:00');

        $type = ClubType::create(['name' => 'Craft Lodge', 'code' => 'craft_lodge', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Lodge of Industry', 'slug' => 'lodge-of-industry', 'status' => 'active', 'settings' => ['installation_month' => 'October']]);
        $this->lodge = Lodge::factory()->create(['club_type_id' => $type->id, 'name' => 'Lodge of Industry', 'number' => '48', 'slug' => 'lodge-of-industry-48']);
        $this->lodge->forceFill(['club_id' => $this->club->id])->save();
        LodgeSchedule::factory()->create(['lodge_id' => $this->lodge->id, 'occurrence' => '1st', 'day_of_week' => 'Tuesday', 'months' => range(1, 12)]);

        $this->member = User::factory()->create();
        $this->secretary = User::factory()->create();
        $this->visitor = User::factory()->create(['name' => 'Vic Visitor']);
        $this->club->users()->attach($this->member->id, ['role' => 'member', 'status' => 'active']);
        $this->club->users()->attach($this->secretary->id, ['role' => 'admin', 'status' => 'active']);

        $this->meeting = Meeting::create([
            'club_id' => $this->club->id,
            'title' => 'October Installation',
            'meeting_date' => '2026-10-06',
            'starts_at' => '18:30:00',
            'venue' => 'Gateshead Masonic Hall',
            'dress_code' => 'Dark suit, craft regalia',
            'festive_board_theme' => 'Roast beef',
            'festive_board_menu' => 'Soup, roast, trifle',
            'dining_cost_guest' => 32.50,
            'status' => 'published',
            'sick_distressed_notes' => 'SECRET-SICK-LIST',
            'almoner_notice' => 'SECRET-ALMONER',
            'bank_account_number' => 'SECRET-BANK-123456',
            'bank_sort_code' => '00-00-00',
            'payment_link' => 'https://pay.example/SECRET-PAYLINK',
            'honorary_members_text' => 'SECRET-HONORARY',
            'intro_text' => 'SECRET-INTRO',
            'officers_roster' => 'SECRET-ROSTER',
            'provincial_header_text' => 'SECRET-PROVINCIAL',
            'fraternal_visits_text' => 'SECRET-VISITS',
        ]);
        Meeting::create(['club_id' => $this->club->id, 'title' => 'Draft', 'meeting_date' => '2026-11-03', 'starts_at' => '18:30:00', 'venue' => 'Hall', 'dress_code' => 'Suit', 'status' => 'draft']);
        Meeting::create(['club_id' => $this->club->id, 'title' => 'Past', 'meeting_date' => '2026-09-01', 'starts_at' => '18:30:00', 'venue' => 'Hall', 'dress_code' => 'Suit', 'status' => 'published']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function setVisibility(string $value): void
    {
        $this->club->update(['settings' => [...$this->club->settings, 'summons_visibility' => $value]]);
    }

    private function page(?User $viewer = null)
    {
        $request = $viewer ? $this->actingAs($viewer) : $this;

        return $request->get(route('lodges.show', $this->lodge->slug));
    }

    private function assertNoSecrets($response): void
    {
        foreach (self::SECRETS as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent(), "{$secret} leaked into the page.");
        }
    }

    public function test_nothing_is_shared_by_default_but_the_lodges_own_members_see_their_meetings(): void
    {
        $this->assertSame('members', VisitorSummons::visibility($this->club));

        foreach ([null, $this->visitor] as $viewer) {
            $response = $this->page($viewer);
            $response->assertInertia(fn ($page) => $page->has('lodge.confirmed', 0)->where('lodge.visitor.can_see', false)->where('lodge.visitor.visibility', 'members')->where('lodge.visitor.can_request', false));
            $this->assertNoSecrets($response);
        }

        $this->page($this->member)->assertInertia(fn ($page) => $page->has('lodge.confirmed', 1)->where('lodge.visitor.is_member', true));
    }

    public function test_a_public_summary_is_a_short_fixed_list_and_never_the_private_parts(): void
    {
        $this->setVisibility('public_safe');

        $response = $this->page();
        $response->assertOk()->assertInertia(fn ($page) => $page
            ->has('lodge.confirmed', 1)
            ->where('lodge.confirmed.0.title', 'October Installation')
            ->where('lodge.confirmed.0.date', '2026-10-06')
            ->where('lodge.confirmed.0.time', '18:30')
            ->where('lodge.confirmed.0.venue', 'Gateshead Masonic Hall')
            ->where('lodge.confirmed.0.dress_code', 'Dark suit, craft regalia')
            ->where('lodge.confirmed.0.festive_board.theme', 'Roast beef')
            ->where('lodge.confirmed.0.festive_board.menu', 'Soup, roast, trifle')
            ->where('lodge.confirmed.0.festive_board.guest_cost', fn ($cost) => str_contains($cost, '32.50'))
            ->where('lodge.confirmed.0.installation', true));
        $this->assertNoSecrets($response);
        $this->assertNoSecrets($this->page($this->visitor));
    }

    public function test_the_shared_facts_are_exactly_this_list(): void
    {
        $facts = VisitorSummons::present($this->meeting, $this->club);

        $this->assertSame(['title', 'date', 'time', 'venue', 'dress_code', 'festive_board', 'installation'], array_keys($facts));
        $this->assertSame(['theme', 'menu', 'guest_cost'], array_keys($facts['festive_board']));
    }

    public function test_only_published_future_meetings_are_shown_and_a_confirmed_date_replaces_the_expected_one(): void
    {
        $this->setVisibility('public_safe');

        $this->page()->assertInertia(fn ($page) => $page
            ->has('lodge.confirmed', 1)
            ->where('lodge.upcoming', fn ($expected) => collect($expected)->doesntContain('date', '2026-10-06') && collect($expected)->contains('date', '2026-11-03')));
    }

    public function test_visitors_have_to_ask_when_a_lodge_shares_with_the_visitors_it_approves(): void
    {
        Notification::fake();
        $this->setVisibility('approved_visitors');

        $this->page()->assertInertia(fn ($page) => $page->has('lodge.confirmed', 0)->where('lodge.visitor.can_request', false));
        $this->page($this->visitor)->assertInertia(fn ($page) => $page->has('lodge.confirmed', 0)->where('lodge.visitor.can_request', true));

        $this->actingAs($this->visitor)->post(route('lodges.visitor_access', $this->lodge->slug), ['home_lodge_name' => 'Lodge of Fellowship', 'home_lodge_number' => '1418', 'rank' => 'WBro', 'message' => 'Visiting in October'])
            ->assertSessionHasNoErrors();

        $access = ClubVisitorAccess::firstOrFail();
        $this->assertSame('pending', $access->status);
        Notification::assertSentTo($this->secretary, ClubNotification::class, fn ($n) => str_contains($n->title, 'Vic Visitor') && $n->route === 'admin.visitors.index');
        Notification::assertNotSentTo($this->member, ClubNotification::class);

        $this->page($this->visitor)->assertInertia(fn ($page) => $page->has('lodge.confirmed', 0)->where('lodge.visitor.request_status', 'pending')->where('lodge.visitor.can_request', false));

        $this->actingAs($this->secretary)->post(route('admin.visitors.approve', ['clubSlug' => 'lodge-of-industry', 'id' => $access->id]))->assertSessionHasNoErrors();

        $response = $this->page($this->visitor);
        $response->assertInertia(fn ($page) => $page->has('lodge.confirmed', 1)->where('lodge.visitor.can_see', true));
        $this->assertNoSecrets($response);
        Notification::assertSentTo($this->visitor, ClubNotification::class, fn ($n) => str_contains($n->title, 'will share its summonses'));

        // Approval is for that person only.
        $this->page(User::factory()->create())->assertInertia(fn ($page) => $page->has('lodge.confirmed', 0));
        $this->page()->assertInertia(fn ($page) => $page->has('lodge.confirmed', 0));

        $this->actingAs($this->secretary)->post(route('admin.visitors.revoke', ['clubSlug' => 'lodge-of-industry', 'id' => $access->id]));
        $this->page($this->visitor)->assertInertia(fn ($page) => $page->has('lodge.confirmed', 0)->where('lodge.visitor.request_status', 'revoked')->where('lodge.visitor.can_request', true));
    }

    public function test_a_request_is_refused_when_it_makes_no_sense(): void
    {
        $data = ['home_lodge_name' => 'Lodge of Fellowship'];

        // The lodge does not take requests.
        $this->actingAs($this->visitor)->post(route('lodges.visitor_access', $this->lodge->slug), $data)->assertSessionHasErrors('access');

        $this->setVisibility('approved_visitors');

        // A member has no need to ask, a home lodge is required, and an unmanaged lodge has nobody to ask.
        $this->actingAs($this->member)->post(route('lodges.visitor_access', $this->lodge->slug), $data)->assertSessionHasErrors('access');
        $this->actingAs($this->visitor)->post(route('lodges.visitor_access', $this->lodge->slug), ['home_lodge_name' => ''])->assertSessionHasErrors('home_lodge_name');
        $this->actingAs($this->visitor)->post(route('lodges.visitor_access', Lodge::factory()->create(['slug' => 'nobody'])->slug), $data)->assertNotFound();

        $this->actingAs($this->visitor)->post(route('lodges.visitor_access', $this->lodge->slug), $data)->assertSessionHasNoErrors();
        $this->actingAs($this->visitor)->post(route('lodges.visitor_access', $this->lodge->slug), $data)->assertSessionHasErrors('access');

        $this->assertSame(1, ClubVisitorAccess::count());
        $this->app['auth']->forgetGuards();
        $this->post(route('lodges.visitor_access', $this->lodge->slug), $data)->assertRedirect();
        $this->assertSame(1, ClubVisitorAccess::count());
    }

    public function test_a_declined_visitor_can_ask_again_and_a_pending_request_can_be_withdrawn(): void
    {
        $this->setVisibility('approved_visitors');
        $data = ['home_lodge_name' => 'Lodge of Fellowship'];
        $this->actingAs($this->visitor)->post(route('lodges.visitor_access', $this->lodge->slug), $data);
        $access = ClubVisitorAccess::firstOrFail();

        $this->actingAs($this->secretary)->post(route('admin.visitors.decline', ['clubSlug' => 'lodge-of-industry', 'id' => $access->id]));
        $this->assertSame('declined', $access->refresh()->status);

        $this->actingAs($this->visitor)->post(route('lodges.visitor_access', $this->lodge->slug), $data)->assertSessionHasNoErrors();
        $this->assertSame('pending', $access->refresh()->status);
        $this->assertSame(1, ClubVisitorAccess::count());

        $this->actingAs($this->visitor)->delete(route('lodges.visitor_access.withdraw', $this->lodge->slug));
        $this->assertSame(0, ClubVisitorAccess::count());
    }

    public function test_only_the_lodges_secretary_can_answer_and_only_for_their_own_lodge(): void
    {
        $this->setVisibility('approved_visitors');
        $this->actingAs($this->visitor)->post(route('lodges.visitor_access', $this->lodge->slug), ['home_lodge_name' => 'Lodge of Fellowship']);
        $access = ClubVisitorAccess::firstOrFail();

        $this->actingAs($this->secretary)->get(route('admin.visitors.index', ['clubSlug' => 'lodge-of-industry']))
            ->assertOk()->assertInertia(fn ($page) => $page->component('Admin/Visitors/Index')->has('requests', 1)->where('requests.0.name', 'Vic Visitor'));

        $this->actingAs($this->member)->get(route('admin.visitors.index', ['clubSlug' => 'lodge-of-industry']))->assertForbidden();
        $this->actingAs($this->member)->post(route('admin.visitors.approve', ['clubSlug' => 'lodge-of-industry', 'id' => $access->id]))->assertForbidden();
        $this->actingAs($this->visitor)->post(route('admin.visitors.approve', ['clubSlug' => 'lodge-of-industry', 'id' => $access->id]))->assertForbidden();

        // Another lodge's secretary cannot use their own address with this lodge's request.
        $otherClub = Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        $otherSecretary = User::factory()->create();
        $otherClub->users()->attach($otherSecretary->id, ['role' => 'admin', 'status' => 'active']);
        $this->actingAs($otherSecretary)->post(route('admin.visitors.approve', ['clubSlug' => 'other', 'id' => $access->id]))->assertNotFound();
        $this->actingAs($otherSecretary)->post(route('admin.visitors.approve', ['clubSlug' => 'lodge-of-industry', 'id' => $access->id]))->assertForbidden();

        $this->assertSame('pending', $access->refresh()->status);
    }

    public function test_an_answered_request_cannot_be_answered_again(): void
    {
        Notification::fake();
        $this->setVisibility('approved_visitors');
        $this->actingAs($this->visitor)->post(route('lodges.visitor_access', $this->lodge->slug), ['home_lodge_name' => 'Lodge of Fellowship']);
        $access = ClubVisitorAccess::firstOrFail();

        $this->actingAs($this->secretary)->post(route('admin.visitors.approve', ['clubSlug' => 'lodge-of-industry', 'id' => $access->id]));
        $this->actingAs($this->secretary)->post(route('admin.visitors.decline', ['clubSlug' => 'lodge-of-industry', 'id' => $access->id]))->assertSessionHasErrors('access');

        $this->assertSame('approved', $access->refresh()->status);
    }

    public function test_the_club_setting_only_takes_a_known_choice(): void
    {
        $owner = User::factory()->create();
        $this->club->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingAs($owner)->put(route('admin.settings.update', ['clubSlug' => 'lodge-of-industry']), ['summons_visibility' => 'everyone'])->assertSessionHasErrors('summons_visibility');
        $this->actingAs($owner)->put(route('admin.settings.update', ['clubSlug' => 'lodge-of-industry']), ['summons_visibility' => 'public_safe'])->assertSessionHasNoErrors();

        $this->assertSame('public_safe', VisitorSummons::visibility($this->club->refresh()));
        $this->assertSame('October', $this->club->settings['installation_month']);
    }

    public function test_followers_who_asked_are_told_of_a_new_summons_if_they_may_see_it(): void
    {
        Notification::fake();
        $this->setVisibility('public_safe');

        $wants = User::factory()->create();
        $quiet = User::factory()->create();
        foreach ([$wants, $quiet, $this->member] as $user) {
            $user->followedLodges()->attach($this->lodge->id, ['in_calendar' => true, 'notify_summons' => $user->id !== $quiet->id]);
        }

        app(ClubNotifier::class)->toFollowers($this->club, $this->meeting);

        Notification::assertSentTo($wants, ClubNotification::class, fn ($n) => $n->route === 'lodges.show' && $n->params === ['slug' => 'lodge-of-industry-48'] && $n->category === 'summons');
        Notification::assertNotSentTo($quiet, ClubNotification::class);
        Notification::assertNotSentTo($this->member, ClubNotification::class); // members are told the usual way
        Notification::assertCount(1);
    }

    public function test_a_follower_who_may_not_see_the_meetings_is_not_told_about_them(): void
    {
        Notification::fake();
        $this->setVisibility('approved_visitors');
        $this->visitor->followedLodges()->attach($this->lodge->id, ['in_calendar' => true, 'notify_summons' => true]);

        app(ClubNotifier::class)->toFollowers($this->club, $this->meeting);
        Notification::assertNothingSent();

        ClubVisitorAccess::create(['club_id' => $this->club->id, 'user_id' => $this->visitor->id, 'home_lodge_name' => 'X'])->forceFill(['status' => 'approved'])->save();
        app(ClubNotifier::class)->toFollowers($this->club, $this->meeting);
        Notification::assertSentTo($this->visitor, ClubNotification::class);
    }

    public function test_a_follower_can_choose_to_hear_about_summonses(): void
    {
        $this->visitor->followedLodges()->attach($this->lodge->id, ['in_calendar' => true]);

        $this->actingAs($this->visitor)->patch(route('lodges.follow.update', $this->lodge->slug), ['notify_summons' => true])->assertSessionHas('success');
        $this->assertTrue((bool) $this->visitor->followedLodges()->first()->pivot->notify_summons);
        $this->assertTrue((bool) $this->visitor->followedLodges()->first()->pivot->in_calendar);

        $this->actingAs($this->visitor)->get(route('lodges.show', $this->lodge->slug))->assertInertia(fn ($page) => $page->where('lodge.notify_summons', true));
        $this->actingAs($this->visitor)->get(route('members.lodges'))->assertInertia(fn ($page) => $page->where('lodges.0.notify_summons', true));
    }

    public function test_publishing_a_summons_tells_followers_who_asked_as_well_as_members(): void
    {
        Notification::fake();
        $this->setVisibility('public_safe');
        $draft = Meeting::where('status', 'draft')->firstOrFail();
        $this->visitor->followedLodges()->attach($this->lodge->id, ['in_calendar' => true, 'notify_summons' => true]);

        $this->actingAs($this->secretary)->post(route('admin.meetings.publish', ['clubSlug' => 'lodge-of-industry', 'id' => $draft->id]))->assertSessionHasNoErrors();

        Notification::assertSentTo($this->visitor, ClubNotification::class, fn ($n) => $n->route === 'lodges.show' && str_contains($n->title, 'Draft'));
        Notification::assertSentTo($this->member, ClubNotification::class, fn ($n) => $n->route === 'member.meetings.summons');

        // Publishing again does not tell anyone twice.
        Notification::fake();
        $this->actingAs($this->secretary)->post(route('admin.meetings.publish', ['clubSlug' => 'lodge-of-industry', 'id' => $draft->id]));
        Notification::assertNothingSent();
    }
}
