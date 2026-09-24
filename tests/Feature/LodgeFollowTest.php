<?php

namespace Tests\Feature;

use App\Http\Controllers\LodgeFollowController;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Lodge;
use App\Models\LodgeSchedule;
use App\Models\MasonicHall;
use App\Models\Meeting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LodgeFollowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Lodge $lodge;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-09-24 12:00:00');

        $hall = MasonicHall::factory()->create(['name' => 'Gateshead Masonic Hall', 'address_line_1' => 'Alexandra Road', 'town' => 'Gateshead', 'postcode' => 'NE8 1RB']);
        $this->user = User::factory()->create();
        $this->lodge = Lodge::factory()->create(['name' => 'Lodge of Industry', 'number' => '48', 'slug' => 'lodge-of-industry-48', 'masonic_hall_id' => $hall->id]);
        LodgeSchedule::factory()->create(['lodge_id' => $this->lodge->id, 'occurrence' => '1st', 'day_of_week' => 'Tuesday', 'months' => range(1, 12), 'start_time' => '19:00']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function follow(?User $user = null, ?Lodge $lodge = null): void
    {
        ($user ?? $this->user)->followedLodges()->attach(($lodge ?? $this->lodge)->id, ['in_calendar' => true]);
    }

    /**
     * A user who belongs to a club, so the all-clubs calendar has somewhere to start.
     */
    private function memberOfAClub(): User
    {
        $type = ClubType::firstOrCreate(['code' => 'craft_lodge'], ['name' => 'Craft Lodge', 'available_modules' => [], 'default_settings' => []]);
        $club = Club::create(['club_type_id' => $type->id, 'name' => 'Home Lodge', 'slug' => 'home-lodge', 'status' => 'active']);
        $this->user->clubs()->attach($club->id, ['role' => 'member', 'status' => 'active']);

        return $this->user;
    }

    public function test_a_guest_cannot_follow(): void
    {
        $this->post(route('lodges.follow', $this->lodge->slug))->assertRedirect(route('login'));

        $this->assertSame(0, $this->lodge->refresh()->id ? \DB::table('lodge_follows')->count() : 1);
    }

    public function test_a_member_can_follow_and_unfollow_and_repeating_does_nothing(): void
    {
        $this->actingAs($this->user)->post(route('lodges.follow', $this->lodge->slug))->assertSessionHas('success');
        $this->actingAs($this->user)->post(route('lodges.follow', $this->lodge->slug));

        $this->assertSame(1, $this->user->followedLodges()->count());
        $this->assertTrue((bool) $this->user->followedLodges()->first()->pivot->in_calendar);

        $this->actingAs($this->user)->delete(route('lodges.unfollow', $this->lodge->slug))->assertSessionHas('success');
        $this->actingAs($this->user)->delete(route('lodges.unfollow', $this->lodge->slug))->assertSessionHasNoErrors();

        $this->assertSame(0, $this->user->followedLodges()->count());
    }

    public function test_only_listed_lodges_can_be_followed(): void
    {
        $gone = Lodge::factory()->create(['slug' => 'gone', 'status' => 'erased']);

        $this->actingAs($this->user)->post(route('lodges.follow', $gone->slug))->assertNotFound();
        $this->actingAs($this->user)->post(route('lodges.follow', 'no-such-lodge'))->assertNotFound();
        $this->assertSame(0, $this->user->followedLodges()->count());
    }

    public function test_a_member_cannot_follow_more_than_the_limit(): void
    {
        Lodge::factory()->count(LodgeFollowController::MAX_FOLLOWS)->create()
            ->each(fn (Lodge $lodge) => $this->follow(lodge: $lodge));

        $this->actingAs($this->user)->post(route('lodges.follow', $this->lodge->slug))->assertSessionHasErrors('follow');

        $this->assertSame(LodgeFollowController::MAX_FOLLOWS, $this->user->followedLodges()->count());
    }

    public function test_a_follow_can_be_kept_out_of_the_calendar_only_by_the_person_who_made_it(): void
    {
        $this->follow();
        $stranger = User::factory()->create();

        $this->actingAs($this->user)->patch(route('lodges.follow.update', $this->lodge->slug), ['in_calendar' => false])->assertSessionHasNoErrors();
        $this->assertFalse((bool) $this->user->followedLodges()->first()->pivot->in_calendar);

        $this->actingAs($stranger)->patch(route('lodges.follow.update', $this->lodge->slug), ['in_calendar' => true])->assertNotFound();
        $this->assertFalse((bool) $this->user->followedLodges()->first()->pivot->in_calendar);

        $this->actingAs($this->user)->patch(route('lodges.follow.update', $this->lodge->slug), ['in_calendar' => 'maybe'])->assertSessionHasErrors('in_calendar');
    }

    public function test_my_lodges_lists_only_my_own_follows(): void
    {
        $other = User::factory()->create();
        $theirs = Lodge::factory()->create(['name' => 'Their Secret Lodge', 'slug' => 'theirs']);
        $this->follow();
        $this->follow($other, $theirs);

        $this->actingAs($this->user)->get(route('members.lodges'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Members/Lodges')
                ->has('lodges', 1)
                ->where('lodges.0.slug', 'lodge-of-industry-48')
                ->where('lodges.0.next.0.date', '2026-10-06')
                ->where('lodges.0.in_calendar', true));

    }

    public function test_my_lodges_needs_a_sign_in(): void
    {
        $this->get(route('members.lodges'))->assertRedirect(route('login'));
    }

    public function test_the_lodge_and_directory_pages_show_what_i_follow(): void
    {
        $this->follow();

        $this->actingAs($this->user)->get(route('lodges.show', $this->lodge->slug))
            ->assertInertia(fn ($page) => $page->where('lodge.following', true)->where('lodge.in_calendar', true));

        $this->actingAs($this->user)->get(route('lodges.index'))
            ->assertInertia(fn ($page) => $page->where('lodges.data.0.following', true));

        $this->actingAs(User::factory()->create())->get(route('lodges.show', $this->lodge->slug))
            ->assertInertia(fn ($page) => $page->where('lodge.following', false));

        $this->get(route('lodges.show', $this->lodge->slug))
            ->assertInertia(fn ($page) => $page->where('lodge.following', false));
    }

    public function test_followed_lodges_appear_on_the_calendar_as_expected_dates(): void
    {
        $user = $this->memberOfAClub();
        $this->follow();

        $this->actingAs($user)->get(route('members.calendar', ['month' => '2026-10']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('items', function ($items) {
                $lodge = collect($items)->firstWhere('type', 'lodge');

                return $lodge !== null
                    && $lodge['title'] === 'Meeting (expected)'
                    && $lodge['club']['name'] === 'Lodge of Industry'
                    && str_starts_with($lodge['start'], '2026-10-06T19:00')
                    && $lodge['all_day'] === false
                    && str_contains($lodge['where'], 'Gateshead Masonic Hall')
                    && $lodge['url'] === '/lodges/lodge-of-industry-48';
            }));
    }

    public function test_a_lodge_taken_out_of_the_calendar_and_a_single_club_view_do_not_show_it(): void
    {
        $user = $this->memberOfAClub();
        $this->follow();

        $this->actingAs($user)->patch(route('lodges.follow.update', $this->lodge->slug), ['in_calendar' => false]);
        $this->actingAs($user)->get(route('members.calendar', ['month' => '2026-10']))
            ->assertInertia(fn ($page) => $page->where('items', fn ($items) => collect($items)->where('type', 'lodge')->isEmpty()));

        $this->actingAs($user)->patch(route('lodges.follow.update', $this->lodge->slug), ['in_calendar' => true]);
        $this->actingAs($user)->get(route('member.calendar', ['slug' => 'home-lodge', 'month' => '2026-10']))
            ->assertInertia(fn ($page) => $page->where('items', fn ($items) => collect($items)->where('type', 'lodge')->isEmpty()));
    }

    public function test_a_lodge_i_already_belong_to_is_not_added_twice(): void
    {
        $user = $this->memberOfAClub();
        $this->lodge->forceFill(['club_id' => Club::where('slug', 'home-lodge')->value('id')])->save();
        $this->follow();

        $this->actingAs($user)->get(route('members.calendar', ['month' => '2026-10']))
            ->assertInertia(fn ($page) => $page->where('items', fn ($items) => collect($items)->where('type', 'lodge')->isEmpty()));
    }

    public function test_an_expected_date_with_no_time_is_all_day_and_never_a_clash(): void
    {
        $user = $this->memberOfAClub();
        $untimed = Lodge::factory()->create(['name' => 'Untimed Lodge', 'slug' => 'untimed']);
        LodgeSchedule::factory()->create(['lodge_id' => $untimed->id, 'occurrence' => '1st', 'day_of_week' => 'Tuesday', 'months' => [10], 'start_time' => null]);
        $this->follow();
        $this->follow(lodge: $untimed);

        $this->actingAs($user)->get(route('members.calendar', ['month' => '2026-10']))
            ->assertInertia(fn ($page) => $page->where('items', function ($items) {
                $untimed = collect($items)->firstWhere('club.slug', 'untimed');
                $timed = collect($items)->firstWhere('club.slug', 'lodge-of-industry-48');

                return $untimed['all_day'] === true && $untimed['clash'] === false && $timed['clash'] === false;
            }));
    }

    public function test_the_personal_feed_carries_followed_lodges_and_nobody_elses(): void
    {
        $user = $this->memberOfAClub();
        $token = str_repeat('a', 48);
        $user->forceFill(['calendar_token' => $token])->save();
        $this->follow();

        $untimed = Lodge::factory()->create(['name' => 'Untimed Lodge', 'slug' => 'untimed']);
        LodgeSchedule::factory()->create(['lodge_id' => $untimed->id, 'occurrence' => '1st', 'day_of_week' => 'Tuesday', 'months' => [10], 'start_time' => null]);
        $this->follow(lodge: $untimed);

        $someoneElse = Lodge::factory()->create(['name' => 'Not Mine Lodge', 'slug' => 'not-mine']);
        LodgeSchedule::factory()->create(['lodge_id' => $someoneElse->id]);
        $this->follow(User::factory()->create(), $someoneElse);

        // Calendar apps join folded lines (a line break followed by a space) back together.
        $body = str_replace("\r\n ", '', $this->get(route('members.calendar.feed', ['token' => $token]))->assertOk()->getContent());

        $this->assertStringContainsString('SUMMARY:Lodge of Industry: Meeting (expected)', $body);
        $this->assertStringContainsString('DTSTART:20261006T180000Z', $body);
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20261006', $body);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20261007', $body);
        $this->assertStringContainsString('Confirm with the lodge', $body);
        $this->assertStringNotContainsString('Not Mine Lodge', $body);
    }

    public function test_a_lodge_has_a_public_calendar_anyone_can_subscribe_to(): void
    {
        $response = $this->get(route('lodges.ics', $this->lodge->slug))->assertOk();
        $body = $response->getContent();

        $this->assertStringStartsWith('text/calendar', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertSame(12, substr_count($body, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('SUMMARY:Lodge of Industry meeting (expected)', $body);
        $this->assertStringContainsString('LOCATION:Gateshead Masonic Hall\, Alexandra Road', $body);

        Lodge::factory()->create(['slug' => 'gone', 'status' => 'erased']);
        $this->get(route('lodges.ics', 'gone'))->assertNotFound();
    }

    public function test_the_installation_meeting_is_highlighted_on_the_calendar_feed_and_lodge_calendar(): void
    {
        $user = $this->memberOfAClub();
        $token = str_repeat('b', 48);
        $user->forceFill(['calendar_token' => $token])->save();
        $this->lodge->update(['installation_month' => 11]);
        $this->follow();

        $this->actingAs($user)->get(route('members.calendar', ['month' => '2026-11']))
            ->assertInertia(fn ($page) => $page->where('items', function ($items) {
                $item = collect($items)->firstWhere('type', 'lodge');

                return $item['installation'] === true && $item['title'] === 'Installation meeting (expected)';
            }));

        $this->actingAs($user)->get(route('members.calendar', ['month' => '2026-10']))
            ->assertInertia(fn ($page) => $page->where('items', fn ($items) => collect($items)->firstWhere('type', 'lodge')['installation'] === false));

        $feed = str_replace("\r\n ", '', $this->get(route('members.calendar.feed', ['token' => $token]))->getContent());
        $this->assertStringContainsString('SUMMARY:Lodge of Industry: Installation meeting (expected)', $feed);
        $this->assertStringContainsString('SUMMARY:Lodge of Industry: Meeting (expected)', $feed);

        $public = str_replace("\r\n ", '', $this->get(route('lodges.ics', $this->lodge->slug))->getContent());
        $this->assertStringContainsString('SUMMARY:Lodge of Industry installation meeting (expected)', $public);
    }

    public function test_my_own_clubs_installation_meeting_is_highlighted_too(): void
    {
        $user = $this->memberOfAClub();
        $club = Club::where('slug', 'home-lodge')->firstOrFail();
        $club->update(['settings' => ['installation_month' => 'October']]);

        foreach (['2026-09-15' => 'Regular meeting', '2026-10-13' => 'Regular meeting', '2026-11-10' => 'Installation Rehearsal Meeting'] as $date => $title) {
            Meeting::create(['club_id' => $club->id, 'title' => $title, 'meeting_date' => $date, 'starts_at' => '18:30:00', 'venue' => 'The Hall', 'dress_code' => 'Dark Suit', 'status' => 'published']);
        }

        foreach (['2026-09' => false, '2026-10' => true, '2026-11' => true] as $month => $expected) {
            $this->actingAs($user)->get(route('members.calendar', ['month' => $month]))
                ->assertInertia(fn ($page) => $page->where('items', fn ($items) => collect($items)->where('type', 'meeting')->contains(fn ($item) => str_starts_with($item['start'], $month) && $item['installation'] === $expected)));
        }
    }
}
