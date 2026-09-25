<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Page;
use App\Models\PageRedirect;
use App\Models\User;
use App\Support\ClubDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->admin = $this->join('admin');
        $this->member = $this->join('member');
    }

    private function join(string $role, ?Club $club = null): User
    {
        $user = User::factory()->create();
        ($club ?? $this->club)->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    /**
     * A page under a slug the default pages don't already use.
     */
    private function page(array $extra = []): Page
    {
        $slug = $extra['slug'] ?? 'about';
        Page::where('club_id', $this->club->id)->where('slug', $slug)->forceDelete();

        return Page::create($extra + ['club_id' => $this->club->id, 'title' => 'About', 'slug' => $slug, 'blocks' => [], 'is_published' => true, 'show_in_navigation' => true]);
    }

    // ---- per-page SEO fields -----------------------------------------------------------------------------------

    public function test_a_page_can_be_saved_with_its_own_meta_title_and_description(): void
    {
        $this->actingAs($this->admin)->post(route('admin.pages.store', ['clubSlug' => 'club-a']), [
            'title' => 'About Us', 'slug' => 'about-us', 'meta_title' => 'About Our Lodge', 'meta_description' => 'History and membership.', 'blocks' => [],
        ])->assertSessionHasNoErrors();

        $page = Page::where('slug', 'about-us')->sole();
        $this->assertSame('About Our Lodge', $page->meta_title);
        $this->assertSame('History and membership.', $page->meta_description);
    }

    public function test_the_public_page_carries_its_own_meta_title_and_falls_back_to_the_site_default(): void
    {
        $this->page(['meta_title' => 'Custom Title', 'meta_description' => 'Custom description.']);

        $this->get(route('public.site', ['clubSlug' => 'club-a', 'pageSlug' => 'about']))->assertInertia(fn ($p) => $p
            ->where('page.meta_title', 'Custom Title')
            ->where('page.meta_description', 'Custom description.')
            ->has('site.footer_copyright_holder')->has('site.footer_copyright_text'));
    }

    // ---- members-only pages -------------------------------------------------------------------------------------

    public function test_a_members_only_page_is_hidden_from_the_public_but_shown_to_an_active_member(): void
    {
        $this->page(['is_members_only' => true]);

        $this->get(route('public.site', ['clubSlug' => 'club-a', 'pageSlug' => 'about']))->assertRedirect(route('login'));
        $this->actingAs($this->member)->get(route('public.site', ['clubSlug' => 'club-a', 'pageSlug' => 'about']))->assertOk();

        $outsider = $this->join('member', Club::create(['club_type_id' => ClubType::first()->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']));
        $this->actingAs($outsider)->get(route('public.site', ['clubSlug' => 'club-a', 'pageSlug' => 'about']))->assertRedirect(route('login'));
    }

    public function test_the_homepage_can_never_be_marked_members_only(): void
    {
        $home = Page::where('club_id', $this->club->id)->where('slug', 'home')->firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.pages.store', ['clubSlug' => 'club-a']), [
            'id' => $home->id, 'title' => $home->title, 'slug' => 'home', 'is_members_only' => true, 'blocks' => [],
        ])->assertSessionHasNoErrors();

        $this->assertFalse($home->fresh()->is_members_only);
    }

    // ---- renaming a page keeps the old address working -----------------------------------------------------------

    public function test_renaming_a_pages_slug_redirects_the_old_address_and_a_later_rename_does_not_chain(): void
    {
        $page = $this->page();

        $this->actingAs($this->admin)->post(route('admin.pages.store', ['clubSlug' => 'club-a']), [
            'id' => $page->id, 'title' => 'About', 'slug' => 'about-us', 'blocks' => [],
        ])->assertSessionHasNoErrors();

        $this->get(route('public.site', ['clubSlug' => 'club-a', 'pageSlug' => 'about']))
            ->assertRedirect(route('public.site', ['clubSlug' => 'club-a', 'pageSlug' => 'about-us']));
        $this->get(route('public.site', ['clubSlug' => 'club-a', 'pageSlug' => 'about-us']))->assertOk();

        // Renamed again: the very first address still redirects (through the latest row), and no loop is created.
        $this->actingAs($this->admin)->post(route('admin.pages.store', ['clubSlug' => 'club-a']), [
            'id' => $page->id, 'title' => 'About', 'slug' => 'our-story', 'blocks' => [],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, PageRedirect::where('club_id', $this->club->id)->count());
        $this->get(route('public.site', ['clubSlug' => 'club-a', 'pageSlug' => 'about']))
            ->assertRedirect(route('public.site', ['clubSlug' => 'club-a', 'pageSlug' => 'our-story']));
        $this->get(route('public.site', ['clubSlug' => 'club-a', 'pageSlug' => 'about-us']))
            ->assertRedirect(route('public.site', ['clubSlug' => 'club-a', 'pageSlug' => 'our-story']));

        $this->get(route('public.site', ['clubSlug' => 'club-a', 'pageSlug' => 'no-such-page']))->assertNotFound();
    }

    public function test_a_redirect_is_reclaimed_if_a_new_page_later_takes_that_slug(): void
    {
        $page = $this->page();
        $this->actingAs($this->admin)->post(route('admin.pages.store', ['clubSlug' => 'club-a']), ['id' => $page->id, 'title' => 'About', 'slug' => 'about-us', 'blocks' => []]);

        $this->actingAs($this->admin)->post(route('admin.pages.store', ['clubSlug' => 'club-a']), ['title' => 'Fresh page called About', 'slug' => 'about', 'blocks' => []])->assertSessionHasNoErrors();

        $newPage = Page::where('slug', 'about')->sole();
        $this->get(route('public.site', ['clubSlug' => 'club-a', 'pageSlug' => 'about']))->assertOk();
        $this->assertSame($newPage->id, Page::where('slug', 'about')->value('id'));
    }

    // ---- duplicate and trash --------------------------------------------------------------------------------------

    public function test_a_page_can_be_duplicated_as_an_unpublished_draft_with_a_unique_slug(): void
    {
        $page = $this->page(['meta_title' => 'About Our Lodge']);

        $this->actingAs($this->admin)->post(route('admin.pages.duplicate', ['clubSlug' => 'club-a', 'id' => $page->id]))->assertRedirect();

        $copy = Page::where('slug', 'about-copy')->sole();
        $this->assertSame('about-copy', $copy->slug);
        $this->assertFalse($copy->is_published);
        $this->assertFalse($copy->show_in_navigation);
        $this->assertSame('About Our Lodge', $copy->meta_title);

        $this->actingAs($this->admin)->post(route('admin.pages.duplicate', ['clubSlug' => 'club-a', 'id' => $page->id]))->assertRedirect();
        $this->assertSame(2, Page::where('title', 'Copy of About')->count());
    }

    public function test_deleting_a_page_moves_it_to_trash_and_it_can_be_restored_or_removed_for_good(): void
    {
        $page = $this->page(['slug' => 'a-custom-page', 'title' => 'A Custom Page']);

        $this->actingAs($this->admin)->delete(route('admin.pages.destroy', ['clubSlug' => 'club-a', 'id' => $page->id]))->assertRedirect();
        $this->assertSoftDeleted('pages', ['id' => $page->id]);
        $this->get(route('public.site', ['clubSlug' => 'club-a', 'pageSlug' => 'a-custom-page']))->assertNotFound();

        $this->actingAs($this->admin)->get(route('admin.pages.index', ['clubSlug' => 'club-a']))->assertInertia(fn ($p) => $p->has('trashedPages', 1));

        $this->actingAs($this->admin)->post(route('admin.pages.restore', ['clubSlug' => 'club-a', 'id' => $page->id]))->assertRedirect();
        $this->assertNull($page->fresh()->deleted_at);

        $this->actingAs($this->admin)->delete(route('admin.pages.destroy', ['clubSlug' => 'club-a', 'id' => $page->id]));
        $this->actingAs($this->admin)->delete(route('admin.pages.force_delete', ['clubSlug' => 'club-a', 'id' => $page->id]))->assertRedirect();
        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    public function test_a_default_page_still_cannot_be_deleted_but_can_be_duplicated(): void
    {
        $about = Page::where('club_id', $this->club->id)->where('slug', 'about')->firstOrFail();

        $this->actingAs($this->admin)->delete(route('admin.pages.destroy', ['clubSlug' => 'club-a', 'id' => $about->id]))->assertSessionHas('error');
        $this->assertNull($about->fresh()->deleted_at);

        $this->actingAs($this->admin)->post(route('admin.pages.duplicate', ['clubSlug' => 'club-a', 'id' => $about->id]))->assertSessionHasNoErrors();
        $this->assertSame(1, Page::where('title', 'Copy of '.$about->title)->count());
    }

    // ---- the custom domain is now one shared rule and one shared apply --------------------------------------------

    public function test_the_website_settings_page_saves_a_custom_domain_as_pending_and_it_does_not_leak_into_the_settings_blob(): void
    {
        $this->actingAs($this->admin)->post(route('admin.pages.settings.update', ['clubSlug' => 'club-a']), [
            'custom_domain' => 'www.club-a-lodge.org',
        ])->assertSessionHasNoErrors();

        $club = $this->club->fresh();
        $this->assertSame('www.club-a-lodge.org', $club->custom_domain);
        $this->assertSame('pending', $club->domain_status);
        $this->assertNull($club->domain_verified_at);
        $this->assertArrayNotHasKey('custom_domain', $club->settings ?? []);
    }

    public function test_an_invalid_or_duplicate_domain_is_refused_by_every_screen_that_can_set_it(): void
    {
        Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active', 'custom_domain' => 'taken.example.com']);

        $this->actingAs($this->admin)->post(route('admin.pages.settings.update', ['clubSlug' => 'club-a']), ['custom_domain' => 'not a domain'])->assertSessionHasErrors('custom_domain');
        $this->actingAs($this->admin)->post(route('admin.pages.settings.update', ['clubSlug' => 'club-a']), ['custom_domain' => 'taken.example.com'])->assertSessionHasErrors('custom_domain');
        $this->assertNull($this->club->fresh()->custom_domain);
    }

    public function test_verifying_a_domain_without_dns_in_place_leaves_it_pending_and_a_verify_needs_a_domain_first(): void
    {
        $this->actingAs($this->admin)->post(route('admin.pages.settings.verify_domain', ['clubSlug' => 'club-a']))->assertSessionHas('error');

        $this->club->update(['custom_domain' => 'club-a-lodge.test', 'domain_status' => 'pending']);
        $this->actingAs($this->admin)->post(route('admin.pages.settings.verify_domain', ['clubSlug' => 'club-a']))->assertSessionHas('error');
        $this->assertSame('pending', $this->club->fresh()->domain_status);
    }

    public function test_the_domain_rule_and_apply_are_shared_so_all_three_screens_agree(): void
    {
        ClubDomain::apply($this->club, 'Members.Club-A.ORG');
        $this->club->save();

        $this->assertSame('members.club-a.org', $this->club->custom_domain);
        $this->assertSame('pending', $this->club->domain_status);

        $rule = ClubDomain::rule($this->club);
        $this->assertIsArray($rule);
        $this->assertSame('nullable', $rule[0]);
    }

    // ---- permissions -----------------------------------------------------------------------------------------------

    public function test_only_someone_who_can_edit_the_website_may_manage_pages(): void
    {
        $page = $this->page();

        $this->actingAs($this->member)->post(route('admin.pages.store', ['clubSlug' => 'club-a']), ['title' => 'X', 'slug' => 'x', 'blocks' => []])->assertForbidden();
        $this->actingAs($this->member)->delete(route('admin.pages.destroy', ['clubSlug' => 'club-a', 'id' => $page->id]))->assertForbidden();
        $this->actingAs($this->member)->post(route('admin.pages.duplicate', ['clubSlug' => 'club-a', 'id' => $page->id]))->assertForbidden();

        $outsider = $this->join('admin', Club::create(['club_type_id' => ClubType::first()->id, 'name' => 'Club C', 'slug' => 'club-c', 'status' => 'active']));
        $this->actingAs($outsider)->delete(route('admin.pages.destroy', ['clubSlug' => 'club-a', 'id' => $page->id]))->assertForbidden();

        $this->assertNull($page->fresh()->deleted_at);
    }
}
