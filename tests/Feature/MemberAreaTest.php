<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Enums\Visibility;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\Post;
use App\Models\User;
use App\Notifications\ClubNotification;
use App\Support\IcsCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberAreaTest extends TestCase
{
    use RefreshDatabase;

    private Club $oxford;

    private Club $bath;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->oxford = Club::create(['club_type_id' => $type->id, 'name' => 'Oxford Lodge', 'slug' => 'oxford-lodge', 'status' => 'active']);
        $this->bath = Club::create(['club_type_id' => $type->id, 'name' => 'Bath Lodge', 'slug' => 'bath-lodge', 'status' => 'active']);

        $this->member = User::factory()->create(['name' => 'Alex Morgan']);
        $this->oxford->users()->attach($this->member->id, ['role' => 'member', 'status' => 'active', 'dietary_notes' => 'No shellfish']);
    }

    private function join(Club $club, ?User $user = null, string $role = 'member', string $status = 'active'): User
    {
        $user ??= User::factory()->create();
        $club->users()->attach($user->id, ['role' => $role, 'status' => $status]);

        return $user;
    }

    private function meeting(Club $club, array $overrides = []): Meeting
    {
        return Meeting::create(array_merge([
            'club_id' => $club->id,
            'title' => 'Regular meeting',
            'meeting_date' => now()->addDays(10)->toDateString(),
            'starts_at' => '19:00:00',
            'venue' => 'Masonic Hall',
            'dress_code' => 'Dark suit',
            'status' => 'published',
        ], $overrides));
    }

    private function event(Club $club, array $overrides = []): Event
    {
        return Event::create(array_merge([
            'club_id' => $club->id,
            'title' => 'Club dinner',
            'slug' => 'club-dinner-'.uniqid(),
            'starts_at' => now()->addDays(5),
            'status' => 'upcoming',
            'visibility' => Visibility::Club,
            'rsvp_audience' => Visibility::Club,
            'requires_payment' => false,
            'has_dining' => false,
        ], $overrides));
    }

    private function newsPost(Club $club, string $title, Visibility $visibility = Visibility::Club): Post
    {
        return Post::create([
            'club_id' => $club->id,
            'author_id' => $this->member->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'content' => '<p>Body</p>',
            'status' => 'published',
            'visibility' => $visibility,
        ]);
    }

    // ---- structure and scope ------------------------------------------------

    public function test_old_member_addresses_redirect_to_the_new_ones(): void
    {
        $this->actingAs($this->member);

        $this->get('/members')->assertStatus(301)->assertRedirect('/members/dashboard');
        $this->get('/portal/subscriptions')->assertStatus(301)->assertRedirect('/members/subscriptions');
        $this->get('/admin/profile')->assertStatus(301)->assertRedirect('/members/profile');
        $this->get('/admin/profile/two-factor')->assertStatus(301)->assertRedirect('/members/security');
        $this->get('/oxford-lodge/events')->assertStatus(301)->assertRedirect('/members/oxford-lodge/events');
        $this->get('/oxford-lodge/news/4')->assertStatus(301)->assertRedirect('/members/oxford-lodge/news/4');
    }

    public function test_profile_and_security_live_under_members(): void
    {
        $this->assertSame('/members/profile', route('profile.edit', [], false));
        $this->assertSame('/members/security', route('admin.profile.two-factor', [], false));

        $this->actingAs($this->member)->get('/members/profile')->assertOk();
        $this->actingAs($this->member)->get('/members/security')->assertOk();
    }

    public function test_the_all_clubs_pages_only_show_the_users_clubs(): void
    {
        $this->newsPost($this->oxford, 'Oxford news');
        $this->newsPost($this->bath, 'Bath news');
        $this->meeting($this->oxford, ['title' => 'Oxford meeting']);
        $this->meeting($this->bath, ['title' => 'Bath meeting']);
        $this->event($this->oxford, ['title' => 'Oxford dinner']);
        $this->event($this->bath, ['title' => 'Bath dinner', 'visibility' => Visibility::Public]);

        $this->actingAs($this->member);

        $this->get('/members/news')->assertInertia(fn (Assert $page) => $page->has('posts.data', 1)->where('posts.data.0.title', 'Oxford news'));
        $this->get('/members/meetings')->assertInertia(fn (Assert $page) => $page->has('meetings', 1)->where('meetings.0.title', 'Oxford meeting'));
        $this->get('/members/events')->assertInertia(fn (Assert $page) => $page->has('events', 1)->where('events.0.title', 'Oxford dinner'));
    }

    public function test_the_lists_can_be_narrowed_to_one_club_by_url_or_filter(): void
    {
        $this->join($this->bath, $this->member);
        $this->newsPost($this->oxford, 'Oxford news');
        $this->newsPost($this->bath, 'Bath news');

        $this->actingAs($this->member);

        $this->get('/members/news')->assertInertia(fn (Assert $page) => $page->has('posts.data', 2));
        $this->get('/members/news?club=bath-lodge')->assertInertia(fn (Assert $page) => $page->has('posts.data', 1)->where('posts.data.0.title', 'Bath news')->where('scopeClub.slug', 'bath-lodge'));
        $this->get('/members/bath-lodge/news')->assertInertia(fn (Assert $page) => $page->has('posts.data', 1)->where('posts.data.0.title', 'Bath news'));
    }

    public function test_an_unknown_club_filter_is_ignored_but_a_club_url_is_a_404(): void
    {
        $this->newsPost($this->oxford, 'Oxford news');

        $this->actingAs($this->member)->get('/members/news?club=bath-lodge')
            ->assertInertia(fn (Assert $page) => $page->has('posts.data', 1)->where('scopeClub', null));

        $this->actingAs($this->member)->get('/members/bath-lodge/news')->assertNotFound();
        $this->actingAs($this->member)->get('/members/bath-lodge/meetings')->assertNotFound();
    }

    public function test_pending_members_do_not_get_club_pages(): void
    {
        $pending = $this->join($this->bath, null, 'member', 'pending');

        $this->actingAs($pending)->get('/members/bath-lodge/news')->assertNotFound();
    }

    public function test_dues_across_clubs_only_show_the_users_own_subscriptions(): void
    {
        $mine = Member::create(['club_id' => $this->oxford->id, 'user_id' => $this->member->id, 'first_name' => 'Alex', 'last_name' => 'Morgan', 'email' => $this->member->email]);
        $other = Member::create(['club_id' => $this->oxford->id, 'user_id' => $this->join($this->oxford)->id, 'first_name' => 'Sam', 'last_name' => 'Other', 'email' => 'other@example.com']);

        foreach ([$mine, $other] as $person) {
            MemberSubscription::create(['club_id' => $this->oxford->id, 'member_id' => $person->id, 'billing_year' => 2026, 'due_date' => now()->addMonth(), 'amount_due' => 100, 'amount_paid' => 0, 'status' => 'unpaid']);
        }

        $this->actingAs($this->member)->get('/members/dues')
            ->assertInertia(fn (Assert $page) => $page->has('subscriptions', 1)->where('subscriptions.0.balance', '100.00')->where('subscriptions.0.outstanding', true));
    }

    // ---- action inbox ---------------------------------------------------------

    public function test_the_inbox_lists_outstanding_dues(): void
    {
        $mine = Member::create(['club_id' => $this->oxford->id, 'user_id' => $this->member->id, 'first_name' => 'Alex', 'last_name' => 'Morgan', 'email' => $this->member->email]);
        MemberSubscription::create(['club_id' => $this->oxford->id, 'member_id' => $mine->id, 'billing_year' => 2026, 'due_date' => now()->addMonth(), 'amount_due' => 45, 'amount_paid' => 0, 'status' => 'unpaid']);

        $this->actingAs($this->member)->get('/members/dashboard')
            ->assertInertia(fn (Assert $page) => $page->where('inbox.0.kind', 'dues')->where('inbox.0.detail', fn ($detail) => str_contains($detail, '£45.00')));
    }

    public function test_the_inbox_lists_unpaid_dining_and_soon_closing_event_replies(): void
    {
        $meeting = $this->meeting($this->oxford, ['dining_cost_member' => 30]);
        MeetingRsvp::create(['meeting_id' => $meeting->id, 'user_id' => $this->member->id, 'token_hash' => 'x', 'token_expires_at' => now()->addWeek(), 'attendance_status' => 'attending_dining', 'payment_status' => 'unpaid', 'responded_at' => now()]);
        $this->event($this->oxford, ['title' => 'Summer supper', 'rsvp_deadline' => now()->addDays(3)]);

        $this->actingAs($this->member)->get('/members/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->has('inbox', 2)
                ->where('inbox', fn ($inbox) => collect($inbox)->pluck('kind')->sort()->values()->all() === ['dining_payment', 'event_rsvp']));
    }

    public function test_only_club_staff_are_told_about_members_waiting_for_approval(): void
    {
        $this->join($this->oxford, null, 'member', 'pending');
        $admin = $this->join($this->oxford, null, 'admin');

        $this->actingAs($this->member)->get('/members/dashboard')->assertInertia(fn (Assert $page) => $page->has('inbox', 0));
        $this->actingAs($admin)->get('/members/dashboard')->assertInertia(fn (Assert $page) => $page->has('inbox', 1)->where('inbox.0.kind', 'approvals'));
    }

    // ---- calendar --------------------------------------------------------------

    public function test_the_calendar_shows_meetings_and_events_and_flags_clashes(): void
    {
        $this->join($this->bath, $this->member);
        $day = now()->addDays(8)->setTime(19, 0);
        $this->meeting($this->oxford, ['title' => 'Oxford meeting', 'meeting_date' => $day->toDateString()]);
        $this->event($this->bath, ['title' => 'Bath dinner', 'starts_at' => $day->copy()->addHour()]);
        $this->event($this->oxford, ['title' => 'Later on', 'starts_at' => now()->addDays(9)->setTime(12, 0)]);

        $this->actingAs($this->member)->get('/members/calendar?month='.$day->format('Y-m'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Members/Calendar')
                ->where('items', fn ($items) => collect($items)->firstWhere('title', 'Oxford meeting')['clash'] === true
                    && collect($items)->firstWhere('title', 'Bath dinner')['clash'] === true
                    && collect($items)->firstWhere('title', 'Later on')['clash'] === false));
    }

    public function test_the_calendar_respects_club_scope_and_visibility(): void
    {
        $this->join($this->bath, $this->member);
        $when = now()->addDays(4)->setTime(12, 0);
        $this->event($this->oxford, ['title' => 'Oxford dinner', 'starts_at' => $when]);
        $this->event($this->bath, ['title' => 'Bath dinner', 'starts_at' => $when->copy()->addDay()]);
        $outsider = Club::create(['club_type_id' => $this->oxford->club_type_id, 'name' => 'Elsewhere', 'slug' => 'elsewhere', 'status' => 'active']);
        $this->event($outsider, ['title' => 'Not mine', 'starts_at' => $when, 'visibility' => Visibility::Public]);

        $this->actingAs($this->member);
        $titles = fn (Assert $page) => $page->where('items', fn ($items) => collect($items)->pluck('title')->sort()->values()->all());

        $this->get('/members/calendar?month='.$when->format('Y-m'))->assertInertia(fn (Assert $page) => $page->where('items', fn ($items) => collect($items)->pluck('title')->sort()->values()->all() === ['Bath dinner', 'Oxford dinner']));
        $this->get('/members/oxford-lodge/calendar?month='.$when->format('Y-m'))->assertInertia(fn (Assert $page) => $page->where('items', fn ($items) => collect($items)->pluck('title')->all() === ['Oxford dinner']));
    }

    public function test_the_calendar_feed_is_authenticated_by_its_token(): void
    {
        $this->join($this->bath, User::factory()->create());
        $this->event($this->oxford, ['title' => 'Oxford dinner, with comma', 'starts_at' => now()->addDays(3)]);
        $this->event($this->bath, ['title' => 'Bath private']);

        $this->actingAs($this->member)->get('/members/calendar')->assertOk();
        $url = route('members.calendar.feed', ['token' => $this->member->fresh()->calendar_token], false);

        $this->flushSession();
        auth()->logout();

        $response = $this->get($url)->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $body = $response->getContent();

        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('SUMMARY:Oxford Lodge: Oxford dinner\, with comma', $body);
        $this->assertStringNotContainsString('Bath private', $body);

        $this->get('/calendar/not-a-real-token.ics')->assertNotFound();
    }

    public function test_replacing_the_calendar_link_invalidates_the_old_one(): void
    {
        $this->actingAs($this->member)->get('/members/calendar');
        $old = $this->member->fresh()->calendar_token;

        $this->post('/members/calendar/link')->assertRedirect();

        $this->assertNotSame($old, $this->member->fresh()->calendar_token);
        $this->get('/calendar/'.$old.'.ics')->assertNotFound();
    }

    public function test_ics_lines_are_escaped_and_folded(): void
    {
        $ics = new IcsCalendar('Test', 'example.test');
        $ics->add('uid-1', str_repeat('Long title ', 12).'; with, special\\chars', now(), now()->addHour(), 'Hall, Room 1');
        $out = $ics->render();

        $this->assertStringContainsString("\r\n ", $out);
        foreach (explode("\r\n", $out) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
        }
        $this->assertStringContainsString('LOCATION:Hall\, Room 1', $out);
    }

    // ---- one-tap replies ---------------------------------------------------------

    public function test_a_one_tap_meeting_reply_keeps_the_dietary_note_from_the_club_profile(): void
    {
        $meeting = $this->meeting($this->oxford);

        $this->actingAs($this->member)
            ->post(route('member.meetings.quick_rsvp', ['slug' => 'oxford-lodge', 'id' => $meeting->id]), ['attendance_status' => 'attending_dining'])
            ->assertSessionHasNoErrors();

        $rsvp = MeetingRsvp::where('meeting_id', $meeting->id)->where('user_id', $this->member->id)->firstOrFail();
        $this->assertSame('attending_dining', $rsvp->attendance_status);
        $this->assertSame('No shellfish', $rsvp->dietary_requirements);
        $this->assertNotNull($rsvp->responded_at);
    }

    public function test_changing_a_one_tap_reply_does_not_wipe_dietary_notes_given_earlier(): void
    {
        $meeting = $this->meeting($this->oxford);
        MeetingRsvp::create(['meeting_id' => $meeting->id, 'user_id' => $this->member->id, 'token_hash' => 'x', 'token_expires_at' => now()->addWeek(), 'attendance_status' => 'attending_dining', 'dietary_requirements' => 'Vegetarian', 'responded_at' => now()]);

        $this->actingAs($this->member)->post(route('member.meetings.quick_rsvp', ['slug' => 'oxford-lodge', 'id' => $meeting->id]), ['attendance_status' => 'apologies']);

        $this->assertSame('Vegetarian', MeetingRsvp::where('meeting_id', $meeting->id)->first()->dietary_requirements);
    }

    public function test_one_tap_replies_are_refused_after_the_cutoff_and_for_other_clubs(): void
    {
        $closed = $this->meeting($this->oxford, ['rsvp_cutoff_at' => now()->subDay()]);
        $elsewhere = $this->meeting($this->bath);

        $this->actingAs($this->member);

        $this->post(route('member.meetings.quick_rsvp', ['slug' => 'oxford-lodge', 'id' => $closed->id]), ['attendance_status' => 'apologies'])->assertSessionHasErrors('cutoff');
        $this->assertDatabaseCount('meeting_rsvps', 0);

        $this->post(route('member.meetings.quick_rsvp', ['slug' => 'bath-lodge', 'id' => $elsewhere->id]), ['attendance_status' => 'apologies'])->assertNotFound();
    }

    public function test_a_simple_event_can_be_answered_in_one_tap(): void
    {
        $event = $this->event($this->oxford);

        $this->actingAs($this->member)->post(route('member.events.quick_rsvp', ['slug' => 'oxford-lodge', 'id' => $event->id]), ['attendance_status' => 'attending'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('event_user', ['event_id' => $event->id, 'user_id' => $this->member->id, 'attendance_status' => 'attending']);
    }

    public function test_an_event_that_needs_choices_only_allows_a_one_tap_decline(): void
    {
        $event = $this->event($this->oxford, ['requires_payment' => true, 'has_dining' => true]);
        $url = route('member.events.quick_rsvp', ['slug' => 'oxford-lodge', 'id' => $event->id]);

        $this->actingAs($this->member)->post($url, ['attendance_status' => 'attending'])->assertSessionHasErrors('rsvp');
        $this->assertDatabaseCount('event_user', 0);

        $this->post($url, ['attendance_status' => 'declined'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('event_user', ['event_id' => $event->id, 'attendance_status' => 'declined']);
    }

    // ---- notifications -------------------------------------------------------------

    public function test_publishing_a_summons_notifies_the_other_members_once(): void
    {
        $admin = $this->join($this->oxford, null, 'admin');
        $meeting = $this->meeting($this->oxford, ['status' => 'draft']);

        $this->actingAs($admin)->post(route('admin.meetings.publish', ['clubSlug' => 'oxford-lodge', 'id' => $meeting->id]));
        $this->actingAs($admin)->post(route('admin.meetings.publish', ['clubSlug' => 'oxford-lodge', 'id' => $meeting->id]));

        $this->assertCount(1, $this->member->notifications);
        $this->assertSame('summons', $this->member->notifications->first()->data['category']);
        $this->assertCount(0, $admin->notifications);
    }

    public function test_news_and_events_notify_members_of_that_club_only(): void
    {
        $admin = $this->join($this->oxford, null, 'admin');
        $outsider = $this->join($this->bath);

        $this->actingAs($admin)->post(route('admin.posts.store', ['clubSlug' => 'oxford-lodge']), ['title' => 'Big news', 'slug' => 'big-news', 'status' => 'published', 'visibility' => 'public']);
        $this->actingAs($admin)->post(route('admin.events.store', ['clubSlug' => 'oxford-lodge']), ['title' => 'Open evening', 'starts_at' => now()->addWeek()->format('Y-m-d\TH:i'), 'status' => 'upcoming']);

        $this->assertEqualsCanonicalizing(['news', 'event'], $this->member->notifications->pluck('data.category')->all());
        $this->assertCount(0, $outsider->notifications);
    }

    public function test_saving_a_draft_or_editing_a_live_post_does_not_notify(): void
    {
        $admin = $this->join($this->oxford, null, 'admin');

        $this->actingAs($admin)->post(route('admin.posts.store', ['clubSlug' => 'oxford-lodge']), ['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft']);
        $this->assertCount(0, $this->member->fresh()->notifications);

        $this->post(route('admin.posts.store', ['clubSlug' => 'oxford-lodge']), ['title' => 'Live', 'slug' => 'live', 'status' => 'published']);
        $post = Post::where('slug', 'live')->firstOrFail();
        $this->post(route('admin.posts.store', ['clubSlug' => 'oxford-lodge']), ['id' => $post->id, 'title' => 'Live, edited', 'slug' => 'live', 'status' => 'published']);

        $this->assertCount(1, $this->member->fresh()->notifications);
    }

    public function test_approving_a_member_notifies_them(): void
    {
        $admin = $this->join($this->oxford, null, 'admin');
        $applicant = $this->join($this->oxford, null, 'member', 'pending');

        $this->actingAs($admin)->post(route('clubs.members.approve', ['slug' => 'oxford-lodge', 'userId' => $applicant->id]));

        $this->assertSame('membership', $applicant->notifications->first()->data['category']);
    }

    public function test_the_bell_data_is_shared_and_the_notifications_page_is_private(): void
    {
        $this->member->notify(ClubNotification::news($this->newsPost($this->oxford, 'Hello'), $this->oxford));
        $other = $this->join($this->oxford);
        $other->notify(ClubNotification::news($this->newsPost($this->oxford, 'Other person'), $this->oxford));

        $this->actingAs($this->member)->get('/members/notifications')
            ->assertInertia(fn (Assert $page) => $page
                ->where('notifications.data.0.title', 'Hello')
                ->has('notifications.data', 1)
                ->where('unreadCount', 1)
                ->where('auth.user.id', $this->member->id)
                ->where('notifications.data.0.club.slug', 'oxford-lodge')
                ->where('bell.unread', 1));

        $this->actingAs($this->member)->get('/members/dashboard')->assertInertia(fn (Assert $page) => $page->has('bell.unread')->has('bell.recent'));
    }

    public function test_opening_a_notification_marks_it_read_and_goes_to_its_page(): void
    {
        $post = $this->newsPost($this->oxford, 'Hello');
        $this->member->notify(ClubNotification::news($post, $this->oxford));
        $notification = $this->member->notifications()->first();

        $this->actingAs($this->member)->get(route('members.notifications.open', ['id' => $notification->id]))
            ->assertRedirect(route('member.posts.show', ['slug' => 'oxford-lodge', 'id' => $post->id], false));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_someone_elses_notification_cannot_be_opened(): void
    {
        $other = $this->join($this->oxford);
        $other->notify(ClubNotification::membershipApproved($this->oxford));

        $this->actingAs($this->member)->get(route('members.notifications.open', ['id' => $other->notifications()->first()->id]))->assertNotFound();
    }

    public function test_notification_links_cannot_redirect_off_site(): void
    {
        $this->member->notifications()->create([
            'id' => (string) str()->uuid(),
            'type' => ClubNotification::class,
            'data' => ['category' => 'notice', 'title' => 'x', 'url' => 'https://evil.example/phish'],
        ]);

        $this->actingAs($this->member)->get(route('members.notifications.open', ['id' => $this->member->notifications()->first()->id]))
            ->assertRedirect(route('members.dashboard'));
    }

    public function test_notifications_can_be_filtered_and_marked_all_read(): void
    {
        $this->member->notify(ClubNotification::news($this->newsPost($this->oxford, 'News item'), $this->oxford));
        $this->member->notify(ClubNotification::membershipApproved($this->oxford));

        $this->actingAs($this->member)->get('/members/notifications?category=news')
            ->assertInertia(fn (Assert $page) => $page->has('notifications.data', 1)->where('notifications.data.0.category', 'news'));

        $this->post('/members/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $this->member->unreadNotifications()->count());

        $this->get('/members/notifications?unread=1')->assertInertia(fn (Assert $page) => $page->has('notifications.data', 0));
    }
}
