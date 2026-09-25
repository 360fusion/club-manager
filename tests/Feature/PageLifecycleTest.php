<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Services\PagePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Club $otherClub;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Lodge', 'code' => 'lodge']);
        $this->club = Club::create(['name' => 'Lodge of Fraternity', 'slug' => 'lodge-of-fraternity', 'club_type_id' => $type->id, 'email' => 'club@example.org']);
        $this->otherClub = Club::create(['name' => 'Bath Lodge', 'slug' => 'bath-lodge', 'club_type_id' => $type->id, 'email' => 'bath@example.org']);

        $this->admin = User::factory()->create();
        $this->club->users()->attach($this->admin->id, ['role' => 'admin', 'status' => 'active']);
    }

    private function page(array $attributes = []): Page
    {
        return Page::create(array_merge([
            'club_id' => $this->club->id,
            'title' => 'History',
            'slug' => 'history',
            'is_published' => true,
            'blocks' => [['id' => 'b1', 'type' => 'text', 'content' => '<p>Live text</p>']],
        ], $attributes));
    }

    private function publicUrl(string $slug = 'history', ?string $query = null): string
    {
        return "/site/lodge-of-fraternity/{$slug}".($query ? "?{$query}" : '');
    }

    private function draftContent(string $text = 'Draft text', string $title = 'History'): array
    {
        return ['title' => $title, 'blocks' => [['id' => 'b1', 'type' => 'text', 'content' => "<p>{$text}</p>"]]];
    }

    private function saveUrl(): string
    {
        return route('admin.pages.store', ['clubSlug' => $this->club->slug]);
    }

    // ---- Scheduling -----------------------------------------------------------

    public function test_a_page_is_only_visible_between_its_dates(): void
    {
        $this->page(['publish_at' => now()->addDay(), 'unpublish_at' => now()->addDays(3)]);

        $this->get($this->publicUrl())->assertNotFound();

        $this->travelTo(now()->addDays(2));
        $this->get($this->publicUrl())->assertOk();

        $this->travelTo(now()->addDays(2));
        $this->get($this->publicUrl())->assertNotFound();
    }

    public function test_the_homepage_ignores_its_dates(): void
    {
        Page::where('club_id', $this->club->id)->where('is_homepage', true)->update(['publish_at' => now()->addYear(), 'unpublish_at' => now()->subDay()]);

        $this->get('/site/lodge-of-fraternity')->assertOk();
    }

    public function test_a_page_outside_its_dates_leaves_the_menu_and_the_sitemap(): void
    {
        $this->page(['slug' => 'soon', 'show_in_navigation' => true, 'publish_at' => now()->addDay()]);
        $this->page(['slug' => 'now', 'show_in_navigation' => true]);

        $this->get('/site/lodge-of-fraternity')->assertInertia(fn ($page) => $page
            ->where('navigation', fn ($nav) => collect($nav)->pluck('slug')->contains('now') && ! collect($nav)->pluck('slug')->contains('soon')));

        $xml = $this->get(route('public.site.sitemap', ['clubSlug' => $this->club->slug]))->getContent();
        $this->assertStringContainsString('/now', $xml);
        $this->assertStringNotContainsString('/soon', $xml);
    }

    public function test_dates_are_saved_checked_and_dropped_for_the_homepage(): void
    {
        $this->actingAs($this->admin);
        $page = $this->page();
        $base = ['id' => $page->id, 'title' => 'History', 'slug' => 'history', 'blocks' => []];

        $this->post($this->saveUrl(), $base + ['publish_at' => '2027-01-10T09:00:00Z', 'unpublish_at' => '2027-01-05T09:00:00Z'])->assertSessionHasErrors('unpublish_at');

        $this->post($this->saveUrl(), $base + ['publish_at' => '2027-01-10T09:00:00Z', 'unpublish_at' => '2027-02-10T09:00:00Z'])->assertSessionHasNoErrors();
        $this->assertSame('2027-01-10 09:00:00', $page->fresh()->publish_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('scheduled', $page->fresh()->lifecycle());

        $home = Page::where('club_id', $this->club->id)->where('is_homepage', true)->firstOrFail();
        $this->post($this->saveUrl(), ['id' => $home->id, 'title' => 'Home', 'slug' => 'home', 'blocks' => [], 'publish_at' => '2027-01-10T09:00:00Z'])->assertSessionHasNoErrors();
        $this->assertNull($home->fresh()->publish_at);
    }

    // ---- Drafts and previews --------------------------------------------------

    public function test_a_draft_stays_unseen_until_it_is_published(): void
    {
        $this->actingAs($this->admin);
        $page = $this->page();

        $this->post(route('admin.pages.draft.save', ['clubSlug' => $this->club->slug, 'id' => $page->id]), $this->draftContent('Draft text', 'History (new)'))->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertTrue($page->hasDraft());
        $this->assertSame('History', $page->title);

        $this->get($this->publicUrl())->assertInertia(fn ($p) => $p->where('page.blocks.0.content', '<p>Live text</p>')->where('page.title', 'History'));

        $this->post(route('admin.pages.draft.publish', ['clubSlug' => $this->club->slug, 'id' => $page->id]))->assertRedirect();

        $page->refresh();
        $this->assertFalse($page->hasDraft());
        $this->assertSame('History (new)', $page->title);
        $this->assertSame('publish', $page->revisions()->latest('id')->first()->source);
        $this->get($this->publicUrl())->assertInertia(fn ($p) => $p->where('page.blocks.0.content', '<p>Draft text</p>'));
    }

    public function test_a_draft_can_be_discarded_and_a_full_save_replaces_it(): void
    {
        $this->actingAs($this->admin);
        $page = $this->page();
        $draft = route('admin.pages.draft.save', ['clubSlug' => $this->club->slug, 'id' => $page->id]);

        $this->post($draft, $this->draftContent());
        $this->delete(route('admin.pages.draft.discard', ['clubSlug' => $this->club->slug, 'id' => $page->id]));
        $this->assertFalse($page->fresh()->hasDraft());

        $this->post($draft, $this->draftContent());
        $this->post($this->saveUrl(), ['id' => $page->id, 'title' => 'History', 'slug' => 'history'] + $this->draftContent('Saved live'))->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertFalse($page->hasDraft());
        $this->assertSame('<p>Saved live</p>', $page->blocks[0]['content']);
    }

    public function test_only_a_published_page_can_have_a_draft_and_publishing_needs_one(): void
    {
        $this->actingAs($this->admin);
        $page = $this->page(['is_published' => false]);

        $this->post(route('admin.pages.draft.save', ['clubSlug' => $this->club->slug, 'id' => $page->id]), $this->draftContent())->assertSessionHasErrors('title');
        $this->post(route('admin.pages.draft.publish', ['clubSlug' => $this->club->slug, 'id' => $page->id]))->assertStatus(422);
    }

    public function test_the_private_link_shows_the_draft_of_any_page_and_only_to_its_holder(): void
    {
        $this->actingAs($this->admin);
        $page = $this->page(['is_published' => false, 'is_members_only' => true]);
        app(PagePublisher::class)->saveDraft($page, $this->draftContent('Secret draft'));

        $link = $this->postJson(route('admin.pages.preview_link', ['clubSlug' => $this->club->slug, 'id' => $page->id]))->assertOk()->json('url');
        $this->assertStringContainsString('?preview=', $link);

        auth()->logout();
        $this->app['auth']->forgetGuards();

        $response = $this->get($link)->assertOk();
        $response->assertInertia(fn ($p) => $p->where('page.blocks.0.content', '<p>Secret draft</p>')->where('preview.has_draft', true));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->get($this->publicUrl())->assertNotFound();
        $this->get($this->publicUrl('history', 'preview=wrong-token'))->assertNotFound();
    }

    public function test_asking_for_a_new_link_switches_the_old_one_off(): void
    {
        $this->actingAs($this->admin);
        $page = $this->page(['is_published' => false]);
        $url = route('admin.pages.preview_link', ['clubSlug' => $this->club->slug, 'id' => $page->id]);

        $first = $this->postJson($url)->json('url');
        $this->assertSame($first, $this->postJson($url)->json('url'));

        $second = $this->postJson($url, ['renew' => true])->json('url');
        $this->assertNotSame($first, $second);

        $this->get($first)->assertNotFound();
        $this->get($second)->assertOk();
    }

    public function test_a_private_link_belongs_to_one_page_only(): void
    {
        $this->actingAs($this->admin);
        $one = $this->page(['slug' => 'one', 'is_published' => false]);
        $this->page(['slug' => 'two', 'is_published' => false]);

        $token = basename(parse_url($this->postJson(route('admin.pages.preview_link', ['clubSlug' => $this->club->slug, 'id' => $one->id]))->json('url'), PHP_URL_QUERY), '');
        $token = str_replace('preview=', '', $token);

        $this->get($this->publicUrl('two', 'preview='.$token))->assertNotFound();
    }

    // ---- History --------------------------------------------------------------

    public function test_each_change_is_remembered_and_an_unchanged_save_is_not(): void
    {
        $this->actingAs($this->admin);
        $page = $this->page();
        $base = ['id' => $page->id, 'slug' => 'history'];

        $this->post($this->saveUrl(), $base + $this->draftContent('One'));
        $this->post($this->saveUrl(), $base + $this->draftContent('One'));
        $this->post($this->saveUrl(), $base + $this->draftContent('Two'));

        $this->assertSame(2, $page->revisions()->count());

        $list = $this->getJson(route('admin.pages.revisions', ['clubSlug' => $this->club->slug, 'id' => $page->id]))->assertOk()->json('revisions');
        $this->assertCount(2, $list);
        $this->assertSame($this->admin->name, $list[0]['user']);
        $this->assertSame(1, $list[0]['block_count']);

        $one = $page->revisions()->orderBy('id')->first();
        $this->getJson(route('admin.pages.revision', ['clubSlug' => $this->club->slug, 'id' => $page->id, 'revisionId' => $one->id]))
            ->assertOk()->assertJsonPath('content.blocks.0.content', '<p>One</p>');

        // Looking at a version changes nothing.
        $this->assertSame('<p>Two</p>', $page->fresh()->blocks[0]['content']);
    }

    public function test_only_the_newest_thirty_versions_are_kept(): void
    {
        $page = $this->page();
        $publisher = app(PagePublisher::class);

        foreach (range(1, 35) as $n) {
            $page->blocks = [['id' => 'b', 'type' => 'text', 'content' => "<p>v{$n}</p>"]];
            $page->save();
            $publisher->snapshot($page, $this->admin, 'save');
        }

        $this->assertSame(PagePublisher::MAX_REVISIONS, $page->revisions()->count());
        $this->assertSame('<p>v35</p>', $page->revisions()->latest('id')->first()->blocks[0]['content']);
        $this->assertFalse($page->revisions()->get()->contains(fn (PageRevision $r) => $r->blocks[0]['content'] === '<p>v1</p>'));
    }

    public function test_history_is_private_to_the_pages_own_club(): void
    {
        $page = $this->page();
        $revision = app(PagePublisher::class)->snapshot($page, $this->admin, 'save');

        $outsider = User::factory()->create();
        $this->otherClub->users()->attach($outsider->id, ['role' => 'admin', 'status' => 'active']);
        $this->actingAs($outsider);

        $this->getJson(route('admin.pages.revisions', ['clubSlug' => $this->club->slug, 'id' => $page->id]))->assertForbidden();
        $this->getJson(route('admin.pages.revision', ['clubSlug' => $this->club->slug, 'id' => $page->id, 'revisionId' => $revision->id]))->assertForbidden();
        $this->postJson(route('admin.pages.preview_link', ['clubSlug' => $this->club->slug, 'id' => $page->id]))->assertForbidden();
    }

    // ---- Copying an element ---------------------------------------------------

    public function test_an_element_can_be_copied_to_another_page(): void
    {
        $this->actingAs($this->admin);
        $target = $this->page(['slug' => 'target', 'blocks' => [['id' => 'keep', 'type' => 'text', 'content' => '<p>Existing</p>']]]);
        $block = ['id' => 'original', 'type' => 'text', 'heading' => 'Hello', 'content' => '<p>Copied</p><script>alert(1)</script>'];

        $this->postJson(route('admin.pages.copy_block', ['clubSlug' => $this->club->slug, 'id' => $target->id]), ['block' => $block])
            ->assertOk()->assertJson(['ok' => true, 'to_draft' => false]);

        $blocks = $target->fresh()->blocks;
        $this->assertCount(2, $blocks);
        $this->assertSame('keep', $blocks[0]['id']);
        $this->assertNotSame('original', $blocks[1]['id']);
        $this->assertSame('Hello', $blocks[1]['heading']);
        $this->assertStringNotContainsString('<script', $blocks[1]['content']);
        $this->assertSame('copy', $target->revisions()->latest('id')->first()->source);
    }

    public function test_a_copy_goes_into_the_targets_draft_when_it_has_one(): void
    {
        $this->actingAs($this->admin);
        $target = $this->page(['slug' => 'target']);
        app(PagePublisher::class)->saveDraft($target, $this->draftContent('Draft'));

        $this->postJson(route('admin.pages.copy_block', ['clubSlug' => $this->club->slug, 'id' => $target->id]), ['block' => ['type' => 'notice', 'title' => 'Note', 'text' => 'x']])
            ->assertOk()->assertJson(['to_draft' => true]);

        $target->refresh();
        $this->assertCount(1, $target->blocks);
        $this->assertCount(2, $target->draft_blocks);
    }

    public function test_a_copy_cannot_reach_another_clubs_page_or_carry_junk(): void
    {
        $foreign = Page::create(['club_id' => $this->otherClub->id, 'title' => 'Other', 'slug' => 'other', 'is_published' => true, 'blocks' => []]);
        $mine = $this->page(['slug' => 'mine']);

        $this->actingAs($this->admin);
        $this->postJson(route('admin.pages.copy_block', ['clubSlug' => $this->club->slug, 'id' => $foreign->id]), ['block' => ['type' => 'text']])->assertNotFound();
        $this->postJson(route('admin.pages.copy_block', ['clubSlug' => $this->club->slug, 'id' => $mine->id]), ['block' => ['no_type' => true]])->assertStatus(422);
        $this->postJson(route('admin.pages.copy_block', ['clubSlug' => $this->club->slug, 'id' => $mine->id]), [])->assertStatus(422);

        $outsider = User::factory()->create();
        $this->otherClub->users()->attach($outsider->id, ['role' => 'admin', 'status' => 'active']);
        $this->actingAs($outsider)->postJson(route('admin.pages.copy_block', ['clubSlug' => $this->club->slug, 'id' => $mine->id]), ['block' => ['type' => 'text']])->assertForbidden();

        $this->assertSame([], $foreign->fresh()->blocks);
    }

    // ---- Header style and footer menu ----------------------------------------

    public function test_the_top_menu_style_is_validated_and_reaches_the_page(): void
    {
        $this->actingAs($this->admin);
        $page = $this->page();
        $base = ['id' => $page->id, 'title' => 'History', 'slug' => 'history', 'blocks' => []];

        $this->post($this->saveUrl(), $base + ['header_style' => 'logo_only'])->assertSessionHasNoErrors();
        $this->get($this->publicUrl())->assertInertia(fn ($p) => $p->where('page.header_style', 'logo_only'));

        $this->post($this->saveUrl(), $base + ['header_style' => 'sideways'])->assertSessionHasErrors('header_style');

        $this->post($this->saveUrl(), $base)->assertSessionHasNoErrors();
        $this->assertSame('full', $page->fresh()->header_style);
    }

    public function test_footer_pinned_pages_are_listed_only_while_live(): void
    {
        $this->page(['slug' => 'privacy', 'title' => 'Privacy', 'show_in_footer' => true, 'show_in_navigation' => false]);
        $this->page(['slug' => 'later', 'title' => 'Later', 'show_in_footer' => true, 'publish_at' => now()->addDay()]);
        $this->page(['slug' => 'plain', 'title' => 'Plain']);

        $this->get('/site/lodge-of-fraternity')->assertInertia(fn ($p) => $p
            ->has('footerNavigation', 1)->where('footerNavigation.0.slug', 'privacy'));
    }

    public function test_copying_a_page_keeps_its_menu_choices_but_not_its_schedule_draft_or_link(): void
    {
        $this->actingAs($this->admin);
        $page = $this->page(['header_style' => 'hidden', 'publish_at' => now()->addDay(), 'preview_token' => 'secret']);
        app(PagePublisher::class)->saveDraft($page, $this->draftContent());

        $this->post(route('admin.pages.duplicate', ['clubSlug' => $this->club->slug, 'id' => $page->id]));

        $copy = Page::where('club_id', $this->club->id)->where('title', 'Copy of History')->firstOrFail();
        $this->assertSame('hidden', $copy->header_style);
        $this->assertNull($copy->publish_at);
        $this->assertNull($copy->preview_token);
        $this->assertFalse($copy->hasDraft());
    }
}
