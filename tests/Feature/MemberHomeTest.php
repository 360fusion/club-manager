<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\Post;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\Signatures\SignatureRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberHomeTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Club $otherClub;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => ['events'], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Oxford Lodge', 'slug' => 'oxford-lodge', 'status' => 'active']);
        $this->otherClub = Club::create(['club_type_id' => $type->id, 'name' => 'Bath Lodge', 'slug' => 'bath-lodge', 'status' => 'active']);

        $this->member = User::factory()->create();
        $this->club->users()->attach($this->member->id, ['role' => 'member', 'status' => 'active', 'member_number' => 'OX-1']);
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

    private function newsPost(Club $club, Visibility $visibility, string $title): Post
    {
        return Post::create([
            'club_id' => $club->id,
            'author_id' => $this->member->id,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'content' => '<p>Body</p>',
            'status' => 'published',
            'visibility' => $visibility,
        ]);
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        $this->get(route('members.dashboard'))->assertRedirect('/login');
    }

    public function test_signed_in_users_are_redirected_from_the_landing_page(): void
    {
        $this->actingAs($this->member)->get('/')->assertRedirect(route('members.dashboard'));
    }

    public function test_landing_page_is_still_shown_to_guests(): void
    {
        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Welcome'));
    }

    public function test_it_lists_only_the_users_own_clubs(): void
    {
        $pending = Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Pending Lodge', 'slug' => 'pending-lodge', 'status' => 'active']);
        $pending->users()->attach($this->member->id, ['role' => 'member', 'status' => 'pending']);

        $this->actingAs($this->member)->get(route('members.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Members/Dashboard')
                ->has('clubs', 1)
                ->where('clubs.0.slug', 'oxford-lodge')
                ->where('clubs.0.is_staff', false)
                ->has('pendingClubs', 1)
                ->where('pendingClubs.0.slug', 'pending-lodge'));
    }

    public function test_staff_roles_are_flagged_so_the_admin_link_can_show(): void
    {
        $this->otherClub->users()->attach($this->member->id, ['role' => 'treasurer', 'status' => 'active']);

        $this->actingAs($this->member)->get(route('members.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('clubs.1.slug', 'bath-lodge')
                ->where('clubs.1.is_staff', true));
    }

    public function test_unanswered_summonses_need_attention_until_answered(): void
    {
        $meeting = $this->meeting($this->club, ['title' => 'Installation']);
        $this->meeting($this->otherClub, ['title' => 'Not my lodge']);
        $this->meeting($this->club, ['title' => 'Closed', 'meeting_date' => now()->addDays(11)->toDateString(), 'rsvp_cutoff_at' => now()->subDay()]);
        $this->meeting($this->club, ['title' => 'Unpublished', 'meeting_date' => now()->addDays(12)->toDateString(), 'status' => 'draft']);

        $this->actingAs($this->member)->get(route('members.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('inbox', 1)
                ->where('inbox.0.title', 'Reply to Installation'));

        MeetingRsvp::create([
            'meeting_id' => $meeting->id,
            'user_id' => $this->member->id,
            'token_hash' => hash('sha256', 'test-token'),
            'token_expires_at' => now()->addWeek(),
            'attendance_status' => 'apologies',
            'responded_at' => now(),
        ]);

        $this->actingAs($this->member)->get(route('members.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('inbox', 0));
    }

    public function test_pending_signature_requests_need_attention(): void
    {
        Mail::fake();
        $auditorTwo = User::factory()->create();
        $auditorTwo->clubs()->attach($this->club->id, ['role' => 'member', 'status' => 'active']);

        $audit = app(AccountingService::class)->requestYearAudit($this->club, 2025, $this->member, $auditorTwo, null);
        app(SignatureRequestService::class)->request($audit, 'year_audit_auditor_one', $this->member, $this->member->name, $this->member->email, $this->member);

        $this->actingAs($this->member)->get(route('members.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('inbox', 1)
                ->where('inbox.0.kind', 'signature'));
    }

    public function test_up_next_only_shows_the_users_clubs_and_what_they_may_see(): void
    {
        $this->meeting($this->club, ['title' => 'Regular meeting']);
        Event::create(['club_id' => $this->club->id, 'title' => 'Club dinner', 'slug' => 'club-dinner', 'starts_at' => now()->addDays(3), 'status' => 'upcoming', 'visibility' => Visibility::Club]);
        Event::create(['club_id' => $this->otherClub->id, 'title' => 'Other club dinner', 'slug' => 'other-dinner', 'starts_at' => now()->addDays(2), 'status' => 'upcoming', 'visibility' => Visibility::Public]);
        Event::create(['club_id' => $this->club->id, 'title' => 'Past dinner', 'slug' => 'past-dinner', 'starts_at' => now()->subDay(), 'status' => 'upcoming', 'visibility' => Visibility::Club]);

        $this->actingAs($this->member)->get(route('members.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('upNext', 2)
                ->where('upNext.0.title', 'Club dinner')
                ->where('upNext.1.title', 'Regular meeting'));
    }

    public function test_feed_only_contains_content_from_the_users_clubs(): void
    {
        $this->newsPost($this->club, Visibility::Club, 'Members only news');
        $this->newsPost($this->club, Visibility::Public, 'Public news');
        $this->newsPost($this->otherClub, Visibility::Network, 'Other club network news');
        $this->newsPost($this->otherClub, Visibility::Public, 'Other club public news');

        $this->actingAs($this->member)->get(route('members.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('feed')
                ->loadDeferredProps(fn (Assert $reload) => $reload
                    ->has('feed', 2)
                    ->where('feed.0.club.slug', 'oxford-lodge')
                    ->where('feed.1.club.slug', 'oxford-lodge')));
    }

    public function test_a_user_with_no_clubs_gets_an_empty_home(): void
    {
        $this->newsPost($this->club, Visibility::Public, 'Public news');

        $this->actingAs(User::factory()->create())->get(route('members.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('clubs', 0)
                ->has('inbox', 0)
                ->has('upNext', 0)
                ->loadDeferredProps(fn (Assert $reload) => $reload->has('feed', 0)));
    }

    private function listInDirectory(Club $club, array $attributes = []): Club
    {
        $club->forceFill(array_merge(['is_directory_listed' => true], $attributes))->save();

        return $club;
    }

    public function test_the_directory_page_lists_listed_clubs(): void
    {
        $this->listInDirectory($this->club, ['lodge_number' => '357', 'town_city' => 'Oxford', 'province_region' => 'Oxfordshire']);
        $this->listInDirectory($this->otherClub, ['lodge_number' => '1234', 'town_city' => 'Bath', 'province_region' => 'Somerset']);

        $this->actingAs($this->member)->get(route('directory.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Members/Directory')
                ->has('directory.clubs', 2)
                ->where('directory.regions', ['Oxfordshire', 'Somerset']));
    }

    public function test_the_directory_can_be_searched_by_name_number_or_town(): void
    {
        $this->listInDirectory($this->club, ['lodge_number' => '357', 'town_city' => 'Oxford']);
        $this->listInDirectory($this->otherClub, ['lodge_number' => '1234', 'town_city' => 'Bath']);

        foreach ([['oxford', 'oxford-lodge'], ['1234', 'bath-lodge'], ['BATH', 'bath-lodge']] as [$term, $slug]) {
            $this->actingAs($this->member)->get(route('directory.index', ['search' => $term]))
                ->assertInertia(fn (Assert $page) => $page
                    ->has('directory.clubs', 1)
                    ->where('directory.clubs.0.slug', $slug)
                    ->where('directory.filters.search', $term));
        }
    }

    public function test_the_directory_can_be_filtered_by_region(): void
    {
        $this->listInDirectory($this->club, ['province_region' => 'Oxfordshire']);
        $this->listInDirectory($this->otherClub, ['province_region' => 'Somerset']);

        $this->actingAs($this->member)->get(route('directory.index', ['region' => 'Somerset']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('directory.clubs', 1)
                ->where('directory.clubs.0.slug', 'bath-lodge'));
    }

    public function test_unlisted_and_inactive_clubs_stay_out_of_the_directory(): void
    {
        $this->listInDirectory($this->club);
        $this->listInDirectory($this->otherClub, ['is_directory_listed' => false]);
        $inactive = Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Dormant Lodge', 'slug' => 'dormant-lodge', 'status' => 'inactive']);
        $this->listInDirectory($inactive);

        $this->actingAs($this->member)->get(route('directory.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('directory.clubs', 1)
                ->where('directory.clubs.0.slug', 'oxford-lodge'));
    }

    public function test_the_old_directory_address_redirects_permanently(): void
    {
        $this->get('/directory')->assertStatus(301)->assertRedirect('/members/directory');
        $this->get('/directory?search=bath&region=Somerset')->assertRedirect('/members/directory?region=Somerset&search=bath');
    }

    public function test_guests_cannot_see_the_directory(): void
    {
        $this->get(route('directory.index'))->assertRedirect('/login');
    }
}
