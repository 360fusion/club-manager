<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Club $otherClub;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'masonic',
            'available_modules' => ['events', 'posts'],
            'default_settings' => [],
        ]);

        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Oxford Lodge', 'slug' => 'oxford-lodge', 'status' => 'active']);
        $this->otherClub = Club::create(['club_type_id' => $type->id, 'name' => 'Bath Lodge', 'slug' => 'bath-lodge', 'status' => 'active']);
    }

    private function memberOf(Club $club, string $status = 'active', string $role = 'member'): User
    {
        $user = User::factory()->create();
        $club->users()->attach($user->id, ['role' => $role, 'status' => $status]);

        return $user;
    }

    private function postFor(Club $club, Visibility $visibility): Post
    {
        return Post::create([
            'club_id' => $club->id,
            'author_id' => User::factory()->create()->id,
            'title' => ucfirst($visibility->value).' news',
            'slug' => $visibility->value.'-news-'.$club->id,
            'content' => '',
            'status' => 'published',
            'visibility' => $visibility,
        ]);
    }

    private function eventFor(Club $club, Visibility $visibility, ?Visibility $rsvp = null): Event
    {
        return Event::create([
            'club_id' => $club->id,
            'title' => ucfirst($visibility->value).' event',
            'slug' => $visibility->value.'-event',
            'starts_at' => now()->addWeek(),
            'status' => 'upcoming',
            'visibility' => $visibility,
            'rsvp_audience' => $rsvp ?? Visibility::Club,
        ]);
    }

    /**
     * @return list<string>
     */
    private function visiblePostVisibilities(?User $viewer): array
    {
        return Post::visibleTo($viewer)->orderBy('id')->get()
            ->map(fn (Post $post) => $post->visibility->value)->all();
    }

    private function allPosts(): void
    {
        foreach (Visibility::cases() as $visibility) {
            $this->postFor($this->club, $visibility);
        }
    }

    public function test_new_content_defaults_to_club_members_only(): void
    {
        $post = Post::create([
            'club_id' => $this->club->id,
            'author_id' => User::factory()->create()->id,
            'title' => 'Draft',
            'slug' => 'draft',
            'content' => '',
            'status' => 'draft',
        ]);

        $this->assertSame(Visibility::Club, $post->visibility);
        $this->assertSame(Visibility::Club, $post->fresh()->visibility);
    }

    public function test_guests_only_see_public_content(): void
    {
        $this->allPosts();

        $this->assertSame(['public'], $this->visiblePostVisibilities(null));
    }

    public function test_members_of_another_club_see_public_and_network_content(): void
    {
        $this->allPosts();

        $this->assertSame(['network', 'public'], $this->visiblePostVisibilities($this->memberOf($this->otherClub)));
    }

    public function test_pending_members_are_not_treated_as_members(): void
    {
        $this->allPosts();

        $this->assertSame(['public'], $this->visiblePostVisibilities($this->memberOf($this->club, 'pending')));
    }

    public function test_active_club_members_see_everything_in_their_club(): void
    {
        $this->allPosts();

        $this->assertSame(['club', 'network', 'public'], $this->visiblePostVisibilities($this->memberOf($this->club)));
    }

    public function test_club_only_content_stays_private_to_its_own_club(): void
    {
        $this->postFor($this->club, Visibility::Club);
        $this->postFor($this->otherClub, Visibility::Club);

        $viewer = $this->memberOf($this->club);

        $this->assertSame([$this->club->id], Post::visibleTo($viewer)->pluck('club_id')->all());
    }

    public function test_super_admins_see_everything(): void
    {
        $this->allPosts();

        $superAdmin = User::factory()->create();
        $superAdmin->forceFill(['is_super_admin' => true])->save();

        $this->assertCount(3, Post::visibleTo($superAdmin)->get());
    }

    public function test_public_site_only_lists_content_the_viewer_can_see(): void
    {
        $this->allPosts();
        $this->eventFor($this->club, Visibility::Club);
        $this->eventFor($this->club, Visibility::Public);

        $this->get(route('public.site', ['clubSlug' => $this->club->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('latestPosts', 1)
                ->where('latestPosts.0.title', 'Public news')
                ->has('upcomingEvents', 1)
                ->where('upcomingEvents.0.title', 'Public event'));

        $this->actingAs($this->memberOf($this->club))
            ->get(route('public.site', ['clubSlug' => $this->club->slug]))
            ->assertInertia(fn ($page) => $page->has('latestPosts', 3)->has('upcomingEvents', 2));
    }

    public function test_club_landing_page_only_lists_content_the_viewer_can_see(): void
    {
        $this->allPosts();

        $this->actingAs($this->memberOf($this->otherClub))
            ->get(route('clubs.show', ['slug' => $this->club->slug]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('club.posts', 2));
    }

    public function test_member_portal_hides_club_only_news_from_other_clubs_members(): void
    {
        $club = $this->postFor($this->club, Visibility::Club);
        $network = $this->postFor($this->club, Visibility::Network);
        $outsider = $this->memberOf($this->otherClub);

        $this->actingAs($outsider)
            ->get(route('member.posts.show', ['slug' => $this->club->slug, 'id' => $club->id]))
            ->assertNotFound();

        $this->actingAs($outsider)
            ->get(route('member.posts.show', ['slug' => $this->club->slug, 'id' => $network->id]))
            ->assertOk();
    }

    public function test_rsvp_audience_decides_who_can_respond(): void
    {
        $clubOnly = $this->eventFor($this->club, Visibility::Public, Visibility::Club);
        $network = Event::create([
            'club_id' => $this->club->id, 'title' => 'Open evening', 'slug' => 'open-evening', 'starts_at' => now()->addWeek(),
            'status' => 'upcoming', 'visibility' => Visibility::Public, 'rsvp_audience' => Visibility::Network,
        ]);
        $public = Event::create([
            'club_id' => $this->club->id, 'title' => 'Open day', 'slug' => 'open-day', 'starts_at' => now()->addWeek(),
            'status' => 'upcoming', 'visibility' => Visibility::Public, 'rsvp_audience' => Visibility::Public,
        ]);

        $member = $this->memberOf($this->club);
        $visitor = $this->memberOf($this->otherClub);

        $this->assertTrue($clubOnly->canBeRsvpedBy($member));
        $this->assertFalse($clubOnly->canBeRsvpedBy($visitor));
        $this->assertFalse($clubOnly->canBeRsvpedBy(null));

        $this->assertTrue($network->canBeRsvpedBy($visitor));
        $this->assertFalse($network->canBeRsvpedBy(null));

        $this->assertTrue($public->canBeRsvpedBy(null));
    }

    public function test_someone_who_cannot_see_an_event_cannot_rsvp_to_it(): void
    {
        $event = $this->eventFor($this->club, Visibility::Club, Visibility::Public);

        $this->assertFalse($event->canBeRsvpedBy($this->memberOf($this->otherClub)));
    }

    public function test_portal_rsvp_is_refused_outside_the_rsvp_audience(): void
    {
        $event = $this->eventFor($this->club, Visibility::Public, Visibility::Club);

        $this->actingAs($this->memberOf($this->otherClub))
            ->post(route('member.rsvp', ['slug' => $this->club->slug, 'id' => $event->id]), [
                'attendance_status' => 'attending',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_set_post_visibility(): void
    {
        $admin = $this->memberOf($this->club, 'active', 'admin');

        $this->actingAs($admin)
            ->post(route('admin.posts.store', ['clubSlug' => $this->club->slug]), [
                'title' => 'Open evening',
                'slug' => 'open-evening',
                'status' => 'published',
                'visibility' => 'network',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Visibility::Network, Post::where('slug', 'open-evening')->firstOrFail()->visibility);
    }

    public function test_admin_cannot_choose_an_invalid_visibility(): void
    {
        $admin = $this->memberOf($this->club, 'active', 'admin');

        $this->actingAs($admin)
            ->post(route('admin.posts.store', ['clubSlug' => $this->club->slug]), [
                'title' => 'Bad',
                'slug' => 'bad',
                'status' => 'published',
                'visibility' => 'everyone',
            ])
            ->assertSessionHasErrors('visibility');
    }

    public function test_editing_a_post_without_a_visibility_keeps_the_existing_one(): void
    {
        $admin = $this->memberOf($this->club, 'active', 'admin');
        $post = $this->postFor($this->club, Visibility::Public);

        $this->actingAs($admin)
            ->post(route('admin.posts.store', ['clubSlug' => $this->club->slug]), [
                'id' => $post->id,
                'title' => 'Renamed',
                'slug' => $post->slug,
                'status' => 'published',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Visibility::Public, $post->fresh()->visibility);
    }

    public function test_admin_can_set_event_visibility_and_rsvp_audience(): void
    {
        $admin = $this->memberOf($this->club, 'active', 'admin');

        $this->actingAs($admin)
            ->post(route('admin.events.store', ['clubSlug' => $this->club->slug]), [
                'title' => 'Open evening',
                'starts_at' => now()->addWeek()->format('Y-m-d\TH:i'),
                'status' => 'upcoming',
                'visibility' => 'public',
                'rsvp_audience' => 'network',
            ])
            ->assertSessionHasNoErrors();

        $event = Event::where('title', 'Open evening')->firstOrFail();
        $this->assertSame(Visibility::Public, $event->visibility);
        $this->assertSame(Visibility::Network, $event->rsvp_audience);
    }

    public function test_rsvp_audience_cannot_be_wider_than_who_can_see_the_event(): void
    {
        $admin = $this->memberOf($this->club, 'active', 'admin');

        $this->actingAs($admin)
            ->post(route('admin.events.store', ['clubSlug' => $this->club->slug]), [
                'title' => 'Mismatch',
                'starts_at' => now()->addWeek()->format('Y-m-d\TH:i'),
                'status' => 'upcoming',
                'visibility' => 'club',
                'rsvp_audience' => 'public',
            ])
            ->assertSessionHasErrors('rsvp_audience');

        $this->assertDatabaseMissing('events', ['title' => 'Mismatch']);
    }
}
