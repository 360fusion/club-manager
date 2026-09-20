<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use App\Support\ReservedClubSlugs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ClubUrlStructureTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Oxford Lodge', 'slug' => 'oxford-lodge', 'status' => 'active']);
    }

    private function userWithRole(string $role, string $status = 'active'): User
    {
        $user = User::factory()->create();
        $this->club->users()->attach($user->id, ['role' => $role, 'status' => $status]);

        return $user;
    }

    public function test_club_routes_live_at_the_top_level(): void
    {
        $this->assertSame('/members/oxford-lodge', route('member.dashboard', ['slug' => 'oxford-lodge'], false));
        $this->assertSame('/members/oxford-lodge/events', route('member.events', ['slug' => 'oxford-lodge'], false));
        $this->assertSame('/oxford-lodge/admin/pages', route('admin.pages.index', ['clubSlug' => 'oxford-lodge'], false));
        $this->assertSame('/site/oxford-lodge', route('public.site', ['clubSlug' => 'oxford-lodge'], false));
    }

    public function test_the_club_root_shows_members_the_member_area(): void
    {
        $this->actingAs($this->userWithRole('member'))
            ->get('/members/oxford-lodge')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Member/Dashboard'));
    }

    public function test_pending_members_still_reach_the_member_area(): void
    {
        $this->actingAs($this->userWithRole('member', 'pending'))
            ->get('/members/oxford-lodge')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isPending', true));
    }

    public function test_guests_and_non_members_are_sent_to_the_public_website(): void
    {
        $this->get('/oxford-lodge')->assertRedirect('/site/oxford-lodge');

        $this->actingAs(User::factory()->create())
            ->get('/oxford-lodge')
            ->assertRedirect('/site/oxford-lodge');
    }

    public function test_the_short_link_sends_members_to_their_member_area(): void
    {
        $this->actingAs($this->userWithRole('member'))->get('/oxford-lodge')->assertRedirect('/members/oxford-lodge');
    }

    public function test_non_members_are_sent_to_the_public_website_from_the_member_area(): void
    {
        $this->actingAs(User::factory()->create())->get('/members/oxford-lodge')->assertRedirect('/site/oxford-lodge');
    }

    public function test_unknown_clubs_are_a_404(): void
    {
        $this->get('/no-such-club')->assertNotFound();
    }

    public function test_reserved_words_are_never_treated_as_clubs(): void
    {
        foreach (ReservedClubSlugs::WORDS as $word) {
            try {
                $name = app('router')->getRoutes()->match(Request::create('/'.$word))->getName();
            } catch (HttpException) {
                continue;
            }

            $this->assertNotSame('member.dashboard', $name, "/{$word} must not resolve to a club");
        }

        $this->get('/login')->assertOk();
        $this->actingAs(User::factory()->create())->get('/members/dashboard')->assertOk();
    }

    public function test_a_club_cannot_be_saved_with_a_reserved_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Members Club', 'slug' => 'members', 'status' => 'active']);
    }

    public function test_slugs_that_merely_start_with_a_reserved_word_are_fine(): void
    {
        $club = Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Admin Lodge', 'slug' => 'admin-lodge', 'status' => 'active']);

        $this->assertFalse(ReservedClubSlugs::isReserved($club->slug));
        $this->get('/admin-lodge')->assertRedirect('/site/admin-lodge');
    }

    public function test_old_club_urls_redirect_permanently(): void
    {
        $this->get('/clubs/oxford-lodge/admin/pages')->assertStatus(301)->assertRedirect('/oxford-lodge/admin/pages');
        $this->get('/clubs/oxford-lodge')->assertStatus(301)->assertRedirect('/oxford-lodge');
    }

    public function test_the_old_portal_paths_map_onto_the_members_area(): void
    {
        $this->get('/clubs/oxford-lodge/portal')->assertStatus(301)->assertRedirect('/members/oxford-lodge');
        $this->get('/clubs/oxford-lodge/portal/events')->assertStatus(301)->assertRedirect('/members/oxford-lodge/events');
        $this->get('/clubs/oxford-lodge/portal/news/7')->assertStatus(301)->assertRedirect('/members/oxford-lodge/news/7');
    }

    public function test_old_urls_keep_their_query_string(): void
    {
        $this->get('/clubs/oxford-lodge/admin/media?page=2')->assertRedirect('/oxford-lodge/admin/media?page=2');
    }

    public function test_old_urls_that_are_posted_to_keep_their_method(): void
    {
        $this->post('/clubs/oxford-lodge/admin/pages')->assertStatus(308)->assertRedirect('/oxford-lodge/admin/pages');
    }

    public function test_admin_routes_named_with_slug_are_gated_like_the_rest(): void
    {
        $member = $this->userWithRole('member');

        // admin.analytics names its club {slug} rather than {clubSlug}; it must still be gated.
        $this->actingAs($member)->get('/oxford-lodge/admin/analytics')->assertForbidden();
        $this->actingAs($this->userWithRole('admin'))->get('/oxford-lodge/admin/analytics')->assertOk();
    }

    public function test_admin_area_still_requires_membership_of_that_club(): void
    {
        $this->actingAs(User::factory()->create())->get('/oxford-lodge/admin/pages')->assertForbidden();
        $this->actingAs($this->userWithRole('member'))->get('/oxford-lodge/admin/pages')->assertForbidden();
        $this->actingAs($this->userWithRole('admin'))->get('/oxford-lodge/admin/pages')->assertOk();
    }
}
