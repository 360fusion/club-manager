<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\ClubRedirect;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\Page;
use App\Models\User;
use App\Support\FooterCopyright;
use App\Support\SiteAnnouncement;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSearchAndToolsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Club $otherClub;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Lodge', 'code' => 'lodge']);
        $this->club = Club::create(['name' => 'Lodge of Fraternity', 'slug' => 'lodge-of-fraternity', 'club_type_id' => $type->id, 'email' => 'club@example.org']);
        $this->otherClub = Club::create(['name' => 'Bath Lodge', 'slug' => 'bath-lodge', 'club_type_id' => $type->id, 'email' => 'bath@example.org']);
    }

    private function page(array $attributes = []): Page
    {
        return Page::create(array_merge([
            'club_id' => $this->club->id,
            'title' => 'History',
            'slug' => 'history',
            'is_published' => true,
            'blocks' => [['type' => 'text', 'content' => '<p>Hello</p>']],
        ], $attributes));
    }

    private function setting(string $key, mixed $value, ?Club $club = null): void
    {
        $club ??= $this->club;
        $club->settings = array_merge($club->settings ?? [], [$key => $value]);
        $club->save();
    }

    private function admin(?Club $club = null): User
    {
        $user = User::factory()->create();
        ($club ?? $this->club)->users()->attach($user->id, ['role' => 'admin', 'status' => 'active']);

        return $user;
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_merge(['seo_title_suffix' => '| Lodge'], $overrides);
    }

    // ---- The head of the page -------------------------------------------------

    public function test_the_page_head_is_written_by_the_server_for_link_previews(): void
    {
        $this->page(['meta_title' => 'Our History', 'meta_description' => 'Since 1839.', 'share_image' => '/storage/history.jpg']);
        $this->setting('site_icon_url', 'https://cdn.example.org/icon.png');
        $this->setting('seo_title_suffix', '| Lodge of Fraternity');

        $html = $this->get(route('public.site', ['clubSlug' => $this->club->slug, 'pageSlug' => 'history']))->assertOk()->getContent();

        $this->assertStringContainsString('<title>Our History | Lodge of Fraternity</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Since 1839.">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/site/lodge-of-fraternity/history').'">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="'.url('/storage/history.jpg').'">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertStringContainsString('<link rel="icon" href="https://cdn.example.org/icon.png">', $html);
        $this->assertStringNotContainsString('name="robots"', $html);
    }

    public function test_a_page_without_its_own_share_image_uses_the_site_one(): void
    {
        $this->page();
        $this->setting('share_image_url', 'https://cdn.example.org/site.jpg');

        $this->get(route('public.site', ['clubSlug' => $this->club->slug, 'pageSlug' => 'history']))
            ->assertSee('<meta property="og:image" content="https://cdn.example.org/site.jpg">', false);
    }

    public function test_a_page_marked_noindex_asks_search_engines_to_skip_it(): void
    {
        $this->page(['noindex' => true]);

        $html = $this->get(route('public.site', ['clubSlug' => $this->club->slug, 'pageSlug' => 'history']))->getContent();

        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        $this->assertStringNotContainsString('application/ld+json', $html);
    }

    public function test_hiding_the_whole_site_marks_every_page_noindex(): void
    {
        $this->setting('noindex_site', true);

        $this->get(route('public.site', ['clubSlug' => $this->club->slug]))
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_a_theme_preview_is_never_indexed(): void
    {
        $this->get(route('public.site', ['clubSlug' => $this->club->slug]).'?preview_theme=banded:navy_gold')
            ->assertSee('name="robots"', false);
    }

    public function test_structured_data_describes_the_club_and_its_public_events_safely(): void
    {
        $this->club->update(['name' => 'Lodge </script><b>x']);
        $this->setting('social_facebook', 'https://facebook.com/lodge');
        $this->page(['slug' => 'diary', 'blocks' => [['type' => 'calendar', 'heading' => 'Diary']]]);
        Event::create([
            'club_id' => $this->club->id, 'title' => 'Installation', 'slug' => 'installation',
            'starts_at' => CarbonImmutable::now()->addDays(5)->utc(), 'status' => 'upcoming',
            'visibility' => Visibility::Public, 'rsvp_audience' => Visibility::Club,
        ]);
        Event::create([
            'club_id' => $this->club->id, 'title' => 'Private Dinner', 'slug' => 'private-dinner',
            'starts_at' => CarbonImmutable::now()->addDays(6)->utc(), 'status' => 'upcoming',
            'visibility' => Visibility::Club, 'rsvp_audience' => Visibility::Club,
        ]);

        $html = $this->get(route('public.site', ['clubSlug' => $this->club->slug, 'pageSlug' => 'diary']))->getContent();

        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $match);
        $this->assertNotEmpty($match, 'expected a JSON-LD block');

        $data = json_decode($match[1], true, 512, JSON_THROW_ON_ERROR);
        $types = array_column($data['@graph'], '@type');

        $this->assertSame(['Organization', 'Event'], $types);
        $this->assertSame('Lodge </script><b>x', $data['@graph'][0]['name']);
        $this->assertSame(['https://facebook.com/lodge'], $data['@graph'][0]['sameAs']);
        $this->assertSame('Installation', $data['@graph'][1]['name']);
        $this->assertStringNotContainsString('Private Dinner', $html);
    }

    public function test_non_production_servers_send_a_noindex_header(): void
    {
        $this->get(route('public.site', ['clubSlug' => $this->club->slug]))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    // ---- Sitemap --------------------------------------------------------------

    public function test_the_sitemap_lists_only_pages_that_may_be_indexed(): void
    {
        $this->page(['slug' => 'visible']);
        $this->page(['slug' => 'hidden', 'noindex' => true]);
        $this->page(['slug' => 'members', 'is_members_only' => true]);
        $this->page(['slug' => 'draft', 'is_published' => false]);
        Event::create([
            'club_id' => $this->club->id, 'title' => 'Open Night', 'slug' => 'open-night',
            'starts_at' => CarbonImmutable::now()->addDays(5)->utc(), 'status' => 'upcoming',
            'visibility' => Visibility::Public, 'rsvp_audience' => Visibility::Club,
        ]);

        $xml = $this->get(route('public.site.sitemap', ['clubSlug' => $this->club->slug]))
            ->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8')->getContent();

        $this->assertStringContainsString(url('/site/lodge-of-fraternity/visible'), $xml);
        $this->assertStringContainsString(url('/site/lodge-of-fraternity/events/open-night'), $xml);
        $this->assertStringNotContainsString('/hidden', $xml);
        $this->assertStringNotContainsString('/members', $xml);
        $this->assertStringNotContainsString('/draft', $xml);
    }

    public function test_the_sitemap_index_skips_hidden_sites(): void
    {
        $this->setting('noindex_site', true, $this->otherClub);

        $this->get(route('sitemap.index'))->assertOk()
            ->assertSee(route('public.site.sitemap', ['clubSlug' => 'lodge-of-fraternity']), false)
            ->assertDontSee('bath-lodge/sitemap.xml', false);

        $this->get(route('public.site.sitemap', ['clubSlug' => 'bath-lodge']))->assertNotFound();
    }

    // ---- Redirects ------------------------------------------------------------

    public function test_a_redirect_sends_an_old_address_to_its_new_home(): void
    {
        ClubRedirect::create(['club_id' => $this->club->id, 'from_path' => '/old-history', 'to_url' => '/site/lodge-of-fraternity/history', 'is_permanent' => true]);
        ClubRedirect::create(['club_id' => $this->club->id, 'from_path' => '/about/team', 'to_url' => 'https://example.org/team', 'is_permanent' => false]);

        $this->get('/site/lodge-of-fraternity/Old-History/')->assertRedirect('/site/lodge-of-fraternity/history')->assertStatus(301);
        $this->get('/site/lodge-of-fraternity/about/team')->assertRedirect('https://example.org/team')->assertStatus(302);
    }

    public function test_a_redirect_never_leaks_across_clubs_or_hides_a_live_page(): void
    {
        ClubRedirect::create(['club_id' => $this->club->id, 'from_path' => '/old-history', 'to_url' => '/elsewhere', 'is_permanent' => true]);
        $this->page(['slug' => 'old-history']);

        $this->get('/site/lodge-of-fraternity/old-history')->assertOk();
        $this->get('/site/bath-lodge/old-history')->assertNotFound();
    }

    public function test_an_admin_can_add_and_remove_a_redirect(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.pages.redirects.store', ['clubSlug' => $this->club->slug]), [
            'from_path' => 'Old/Page/', 'to_url' => '/site/lodge-of-fraternity/history', 'is_permanent' => true,
        ])->assertSessionHasNoErrors();

        $redirect = ClubRedirect::where('club_id', $this->club->id)->firstOrFail();
        $this->assertSame('/old/page', $redirect->from_path);

        $this->delete(route('admin.pages.redirects.destroy', ['clubSlug' => $this->club->slug, 'id' => $redirect->id]))->assertRedirect();
        $this->assertDatabaseCount('club_redirects', 0);
    }

    public function test_redirect_input_is_checked(): void
    {
        $this->actingAs($this->admin());
        $this->page(['slug' => 'history']);
        $url = route('admin.pages.redirects.store', ['clubSlug' => $this->club->slug]);

        $this->post($url, ['from_path' => '/x', 'to_url' => 'javascript:alert(1)'])->assertSessionHasErrors('to_url');
        $this->post($url, ['from_path' => '/x', 'to_url' => '//evil.example'])->assertSessionHasErrors('to_url');
        $this->post($url, ['from_path' => '/history', 'to_url' => '/elsewhere'])->assertSessionHasErrors('from_path');
        $this->post($url, ['from_path' => '/loop', 'to_url' => '/site/lodge-of-fraternity/loop'])->assertSessionHasErrors('from_path');
        $this->post($url, ['from_path' => '/', 'to_url' => '/elsewhere'])->assertSessionHasErrors('from_path');
        $this->assertDatabaseCount('club_redirects', 0);
    }

    public function test_another_clubs_admin_cannot_touch_this_clubs_redirects(): void
    {
        $redirect = ClubRedirect::create(['club_id' => $this->club->id, 'from_path' => '/old', 'to_url' => '/new', 'is_permanent' => true]);

        $this->actingAs($this->admin($this->otherClub))
            ->delete(route('admin.pages.redirects.destroy', ['clubSlug' => $this->club->slug, 'id' => $redirect->id]))
            ->assertForbidden();

        $this->assertDatabaseCount('club_redirects', 1);
    }

    // ---- Not-found page -------------------------------------------------------

    public function test_a_missing_address_shows_the_chosen_page_with_a_404_status(): void
    {
        $missing = $this->page(['slug' => 'lost', 'title' => 'Page not found']);
        $this->setting('not_found_page_id', $missing->id);

        $this->get('/site/lodge-of-fraternity/no-such-page')
            ->assertNotFound()
            ->assertSee('Page not found', false);
    }

    public function test_without_a_chosen_page_a_missing_address_is_an_ordinary_404(): void
    {
        $this->get('/site/lodge-of-fraternity/no-such-page')->assertNotFound()->assertDontSee('data-page=', false);
    }

    public function test_a_members_only_or_unpublished_not_found_page_is_ignored(): void
    {
        $private = $this->page(['slug' => 'private', 'is_members_only' => true]);
        $this->setting('not_found_page_id', $private->id);

        $this->get('/site/lodge-of-fraternity/nope')->assertNotFound()->assertDontSee('data-page=', false);
    }

    // ---- Announcement bar and tracking ---------------------------------------

    public function test_the_announcement_only_shows_inside_its_dates(): void
    {
        $this->setting('announcement_enabled', true);
        $this->setting('announcement_text', 'Installation on 12 April');
        $this->setting('announcement_starts_on', '2026-04-01');
        $this->setting('announcement_ends_on', '2026-04-12');

        $club = $this->club->fresh();

        $this->assertNull(SiteAnnouncement::forClub($club, CarbonImmutable::parse('2026-03-31 23:00')));
        $this->assertSame('Installation on 12 April', SiteAnnouncement::forClub($club, CarbonImmutable::parse('2026-04-12 22:00'))['text']);
        $this->assertNull(SiteAnnouncement::forClub($club, CarbonImmutable::parse('2026-04-13 00:30')));
    }

    public function test_a_switched_off_or_empty_announcement_sends_nothing(): void
    {
        $this->setting('announcement_text', 'Hello');
        $this->assertNull(SiteAnnouncement::forClub($this->club->fresh()));

        $this->setting('announcement_enabled', true);
        $this->setting('announcement_text', '   ');
        $this->assertNull(SiteAnnouncement::forClub($this->club->fresh()));
    }

    public function test_the_public_page_receives_the_announcement_and_tracking(): void
    {
        $this->setting('announcement_enabled', true);
        $this->setting('announcement_text', 'Open evening on Friday');
        $this->setting('analytics_provider', 'plausible');
        $this->setting('analytics_id', 'lodge.org.uk');

        $this->get(route('public.site', ['clubSlug' => $this->club->slug]))->assertInertia(fn ($page) => $page
            ->where('site.announcement.text', 'Open evening on Friday')
            ->where('site.tracking.provider', 'plausible')
            ->where('site.tracking.id', 'lodge.org.uk')
            ->where('site.tracking.banner', false));
    }

    public function test_google_analytics_always_brings_up_the_cookie_notice_and_a_bad_id_is_ignored(): void
    {
        $this->setting('analytics_provider', 'google');
        $this->setting('analytics_id', 'G-ABC123XYZ');

        $this->get(route('public.site', ['clubSlug' => $this->club->slug]))->assertInertia(fn ($page) => $page
            ->where('site.tracking.provider', 'google')->where('site.tracking.banner', true));

        $this->setting('analytics_id', '"><script>alert(1)</script>');

        $this->get(route('public.site', ['clubSlug' => $this->club->slug]))->assertInertia(fn ($page) => $page
            ->where('site.tracking.provider', 'none')->where('site.tracking.id', ''));
    }

    // ---- Saving the settings --------------------------------------------------

    public function test_site_settings_save_and_are_validated(): void
    {
        $this->actingAs($this->admin());
        $url = route('admin.pages.settings.update', ['clubSlug' => $this->club->slug]);
        $notFound = $this->page(['slug' => 'lost']);

        $this->post($url, $this->settingsPayload([
            'share_image_url' => '/storage/share.jpg',
            'site_icon_url' => 'https://cdn.example.org/icon.png',
            'noindex_site' => false,
            'analytics_provider' => 'google',
            'analytics_id' => 'G-ABC123XYZ',
            'cookie_banner_enabled' => true,
            'cookie_banner_text' => 'We use cookies to count visits.',
            'cookie_banner_link_url' => '/site/lodge-of-fraternity/privacy',
            'not_found_page_id' => $notFound->id,
        ]))->assertSessionHasNoErrors();

        $settings = $this->club->fresh()->settings;
        $this->assertSame('G-ABC123XYZ', $settings['analytics_id']);
        $this->assertSame($notFound->id, $settings['not_found_page_id']);
        $this->assertTrue($settings['cookie_banner_enabled']);

        $this->post($url, $this->settingsPayload(['share_image_url' => 'javascript:alert(1)']))->assertSessionHasErrors('share_image_url');
        $this->post($url, $this->settingsPayload(['site_icon_url' => '//evil.example/x.png']))->assertSessionHasErrors('site_icon_url');
        $this->post($url, $this->settingsPayload(['analytics_provider' => 'google', 'analytics_id' => 'UA-123']))->assertSessionHasErrors('analytics_id');
        $this->post($url, $this->settingsPayload(['analytics_provider' => 'google']))->assertSessionHasErrors('analytics_id');
        $this->post($url, $this->settingsPayload(['analytics_provider' => 'plausible', 'analytics_id' => 'not a domain']))->assertSessionHasErrors('analytics_id');
        $this->post($url, $this->settingsPayload(['analytics_provider' => 'sneaky']))->assertSessionHasErrors('analytics_provider');
    }

    public function test_the_not_found_page_must_belong_to_this_club(): void
    {
        $this->actingAs($this->admin());
        $foreign = Page::create(['club_id' => $this->otherClub->id, 'title' => 'Other', 'slug' => 'other', 'is_published' => true, 'blocks' => []]);

        $this->post(route('admin.pages.settings.update', ['clubSlug' => $this->club->slug]), $this->settingsPayload(['not_found_page_id' => $foreign->id]))
            ->assertSessionHasErrors('not_found_page_id');
    }

    public function test_a_page_saves_its_share_image_and_noindex_and_duplicating_keeps_them(): void
    {
        $this->actingAs($this->admin());
        $page = $this->page();

        $this->post(route('admin.pages.store', ['clubSlug' => $this->club->slug]), [
            'id' => $page->id, 'title' => 'History', 'slug' => 'history', 'blocks' => [],
            'share_image' => '/storage/history.jpg', 'noindex' => true,
        ])->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertSame('/storage/history.jpg', $page->share_image);
        $this->assertTrue($page->noindex);

        $this->post(route('admin.pages.duplicate', ['clubSlug' => $this->club->slug, 'id' => $page->id]));
        $copy = Page::where('club_id', $this->club->id)->where('id', '!=', $page->id)->where('title', 'Copy of History')->firstOrFail();
        $this->assertSame('/storage/history.jpg', $copy->share_image);
        $this->assertTrue($copy->noindex);

        $this->post(route('admin.pages.store', ['clubSlug' => $this->club->slug]), [
            'id' => $page->id, 'title' => 'History', 'slug' => 'history', 'blocks' => [], 'share_image' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('share_image');
    }

    public function test_the_announcement_and_extra_social_links_save_and_are_validated(): void
    {
        $this->actingAs($this->admin());
        $url = route('admin.pages.header_footer.update', ['clubSlug' => $this->club->slug]);
        $base = ['header_layout' => 'logo_left', 'footer_layout' => 'simple'];

        $this->post($url, $base + [
            'announcement_enabled' => true,
            'announcement_text' => 'Installation on 12 April',
            'announcement_link_label' => 'Book',
            'announcement_link_url' => '/site/lodge-of-fraternity/events',
            'announcement_style' => 'warning',
            'announcement_dismissible' => false,
            'announcement_starts_on' => '2026-04-01',
            'announcement_ends_on' => '2026-04-12',
            'social_youtube' => 'https://youtube.com/@lodge',
            'social_whatsapp' => 'https://chat.whatsapp.com/abc',
        ])->assertSessionHasNoErrors();

        $settings = $this->club->fresh()->settings;
        $this->assertSame('warning', $settings['announcement_style']);
        $this->assertFalse($settings['announcement_dismissible']);
        $this->assertSame('https://youtube.com/@lodge', $settings['social_youtube']);

        $this->post($url, $base + ['announcement_link_url' => 'javascript:alert(1)'])->assertSessionHasErrors('announcement_link_url');
        $this->post($url, $base + ['announcement_style' => 'flashing'])->assertSessionHasErrors('announcement_style');
        $this->post($url, $base + ['announcement_starts_on' => '2026-04-10', 'announcement_ends_on' => '2026-04-01'])->assertSessionHasErrors('announcement_ends_on');
        $this->post($url, $base + ['social_youtube' => 'javascript:alert(1)'])->assertSessionHasErrors('social_youtube');
    }

    public function test_font_and_corner_style_save_with_the_theme(): void
    {
        $this->actingAs($this->admin());
        $url = route('admin.pages.themes.update', ['clubSlug' => $this->club->slug]);

        $this->post($url, ['website_theme' => 'banded:navy_gold', 'font_pairing' => 'georgia', 'corner_style' => 'round'])->assertSessionHasNoErrors();

        $settings = $this->club->fresh()->settings;
        $this->assertSame('georgia', $settings['font_pairing']);
        $this->assertSame('round', $settings['corner_style']);

        $this->post($url, ['website_theme' => 'banded:navy_gold', 'font_pairing' => 'comic'])->assertSessionHasErrors('font_pairing');

        $this->get(route('public.site', ['clubSlug' => $this->club->slug]))->assertInertia(fn ($page) => $page
            ->where('site.font_pairing', 'georgia')->where('site.corner_style', 'round'));
    }

    public function test_the_custom_link_columns_can_be_hidden_without_losing_them(): void
    {
        $this->actingAs($this->admin());
        $url = route('admin.pages.header_footer.update', ['clubSlug' => $this->club->slug]);
        $columns = [['title' => 'Useful Links', 'links' => [['label' => 'UGLE', 'url' => 'https://ugle.org.uk']]]];

        $this->post($url, ['header_layout' => 'logo_left', 'footer_layout' => 'columns', 'footer_link_columns' => $columns, 'footer_show_custom_columns' => false])->assertSessionHasNoErrors();

        $settings = $this->club->fresh()->settings;
        $this->assertFalse($settings['footer_show_custom_columns']);
        $this->assertSame('Useful Links', $settings['footer_link_columns'][0]['title']);

        $this->get(route('public.site', ['clubSlug' => $this->club->slug]))->assertInertia(fn ($page) => $page
            ->where('site.footer_show_custom_columns', false)->where('site.footer_link_columns.0.title', 'Useful Links'));

        $this->post($url, ['header_layout' => 'logo_left', 'footer_layout' => 'columns', 'footer_show_custom_columns' => 'not-a-boolean'])->assertSessionHasErrors('footer_show_custom_columns');
    }

    public function test_the_footer_navigation_pages_can_be_chosen(): void
    {
        $this->actingAs($this->admin());
        $url = route('admin.pages.header_footer.update', ['clubSlug' => $this->club->slug]);
        $base = ['header_layout' => 'logo_left', 'footer_layout' => 'columns'];
        $history = $this->page(['slug' => 'history', 'show_in_navigation' => true]);
        $foreign = Page::create(['club_id' => $this->otherClub->id, 'title' => 'Other', 'slug' => 'other', 'is_published' => true, 'blocks' => []]);

        $this->post($url, $base + ['footer_nav_page_ids' => [$history->id, $history->id]])->assertSessionHasNoErrors();
        $this->assertSame([$history->id], $this->club->fresh()->settings['footer_nav_page_ids']);

        $this->get(route('public.site', ['clubSlug' => $this->club->slug]))->assertInertia(fn ($page) => $page->where('site.footer_nav_page_ids', [$history->id]));

        // An empty list means "none"; null goes back to "all".
        $this->post($url, $base + ['footer_nav_page_ids' => []])->assertSessionHasNoErrors();
        $this->assertSame([], $this->club->fresh()->settings['footer_nav_page_ids']);

        $this->post($url, $base + ['footer_nav_page_ids' => null])->assertSessionHasNoErrors();
        $this->assertNull($this->club->fresh()->settings['footer_nav_page_ids']);

        $this->post($url, $base + ['footer_nav_page_ids' => [$foreign->id]])->assertSessionHasErrors('footer_nav_page_ids.0');
        $this->post($url, $base + ['footer_nav_page_ids' => ['x']])->assertSessionHasErrors('footer_nav_page_ids.0');
    }

    public function test_the_copyright_line_never_stores_the_year(): void
    {
        $club = $this->club->fresh();

        // Nothing chosen: the club's name and the usual wording, with whatever year it is when shown.
        $this->assertSame('© 2031 Lodge of Fraternity. All rights reserved.', FooterCopyright::line($club, 2031));

        // A line saved before the split, year and all, is read back without the year.
        $this->setting('footer_copyright', '© 2026 Lodge of Fraternity. All rights reserved.');
        $this->assertSame('© 2040 Lodge of Fraternity. All rights reserved.', FooterCopyright::line($this->club->fresh(), 2040));

        $this->setting('footer_copyright', 'Copyright 2019-2026 Some Trust Ltd.');
        $this->assertSame(['holder' => 'Some Trust Ltd', 'text' => ''], FooterCopyright::parts($this->club->fresh()));
    }

    public function test_the_copyright_holder_and_wording_save_and_the_public_page_gets_no_year(): void
    {
        $this->actingAs($this->admin());
        $this->setting('footer_copyright', '© 2026 Lodge of Fraternity. All rights reserved.');

        $this->post(route('admin.pages.header_footer.update', ['clubSlug' => $this->club->slug]), [
            'header_layout' => 'logo_left', 'footer_layout' => 'simple',
            'footer_copyright_holder' => 'The Lodge of Fraternity No. 1234', 'footer_copyright_text' => 'Registered charity 12345.',
        ])->assertSessionHasNoErrors();

        $settings = $this->club->fresh()->settings;
        $this->assertNull($settings['footer_copyright']);
        $this->assertSame('The Lodge of Fraternity No. 1234', $settings['footer_copyright_holder']);
        $this->assertSame('© 2050 The Lodge of Fraternity No. 1234. Registered charity 12345.', FooterCopyright::line($this->club->fresh(), 2050));

        $this->get(route('public.site', ['clubSlug' => $this->club->slug]))->assertInertia(fn ($page) => $page
            ->where('site.footer_copyright_holder', 'The Lodge of Fraternity No. 1234')
            ->where('site.footer_copyright_text', 'Registered charity 12345.')
            ->missing('site.footer_copyright'));

        // Cleared wording stays cleared; a blank holder goes back to the club's name.
        $this->post(route('admin.pages.header_footer.update', ['clubSlug' => $this->club->slug]), [
            'header_layout' => 'logo_left', 'footer_layout' => 'simple', 'footer_copyright_holder' => '', 'footer_copyright_text' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('© 2050 Lodge of Fraternity.', FooterCopyright::line($this->club->fresh(), 2050));
    }

    public function test_the_text_under_the_club_name_in_the_footer_saves_and_reaches_the_site(): void
    {
        $this->actingAs($this->admin());
        $url = route('admin.pages.header_footer.update', ['clubSlug' => $this->club->slug]);
        $base = ['header_layout' => 'logo_left', 'footer_layout' => 'columns'];

        $this->post($url, $base + ['footer_about_text' => "Meeting at the Masonic Hall.\n4th Thursday, September to May."])->assertSessionHasNoErrors();

        $this->get(route('public.site', ['clubSlug' => $this->club->slug]))->assertInertia(fn ($page) => $page
            ->where('site.footer_about_text', "Meeting at the Masonic Hall.\n4th Thursday, September to May."));

        $this->post($url, $base + ['footer_about_text' => str_repeat('x', 301)])->assertSessionHasErrors('footer_about_text');
    }

    public function test_every_builder_screen_has_its_own_address(): void
    {
        $this->actingAs($this->admin());
        $history = $this->page(['slug' => 'history']);
        $slug = $this->club->slug;

        foreach ([
            'settings' => route('admin.pages.settings', ['clubSlug' => $slug]),
            'themes' => route('admin.pages.themes', ['clubSlug' => $slug]),
            'header_footer' => route('admin.pages.header_footer', ['clubSlug' => $slug]),
            'redirects' => route('admin.pages.redirects', ['clubSlug' => $slug]),
            'overview' => route('admin.pages.overview', ['clubSlug' => $slug]),
        ] as $selected => $url) {
            $this->get($url)->assertOk()->assertInertia(fn ($page) => $page->component('Admin/PageList')->where('selectedId', $selected));
        }

        $this->get(route('admin.pages.edit', ['clubSlug' => $slug, 'id' => $history->id]))
            ->assertOk()->assertInertia(fn ($page) => $page->where('selectedId', $history->id));

        $this->assertStringEndsWith('/'.$slug.'/admin/pages/redirects', route('admin.pages.redirects', ['clubSlug' => $slug]));
        $this->assertStringEndsWith('/'.$slug.'/admin/pages/overview', route('admin.pages.overview', ['clubSlug' => $slug]));
    }

    public function test_the_header_button_and_announcement_links_are_completed_with_https(): void
    {
        $this->actingAs($this->admin());
        $url = route('admin.pages.header_footer.update', ['clubSlug' => $this->club->slug]);

        $this->post($url, ['header_layout' => 'logo_left', 'footer_layout' => 'simple', 'header_cta_link' => 'www.ugle.org.uk/join', 'announcement_link_url' => 'example.org'])
            ->assertSessionHasNoErrors();

        $settings = $this->club->fresh()->settings;
        $this->assertSame('https://www.ugle.org.uk/join', $settings['header_cta_link']);
        $this->assertSame('https://example.org', $settings['announcement_link_url']);

        // A page on this site is kept as it is, and an unsafe scheme is still refused.
        $this->post($url, ['header_layout' => 'logo_left', 'footer_layout' => 'simple', 'header_cta_link' => '/site/lodge-of-fraternity/join-us'])->assertSessionHasNoErrors();
        $this->assertSame('/site/lodge-of-fraternity/join-us', $this->club->fresh()->settings['header_cta_link']);

        $this->post($url, ['header_layout' => 'logo_left', 'footer_layout' => 'simple', 'header_cta_link' => 'javascript:alert(1)'])->assertSessionHasErrors('header_cta_link');
    }
}
