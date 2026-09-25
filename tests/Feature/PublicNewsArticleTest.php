<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicNewsArticleTest extends TestCase
{
    use RefreshDatabase;

    private function club(string $slug = 'lodge-of-fraternity'): Club
    {
        $clubType = ClubType::firstOrCreate(['code' => 'lodge'], ['name' => 'Lodge']);

        return Club::create(['name' => 'Lodge of Fraternity', 'slug' => $slug, 'club_type_id' => $clubType->id, 'email' => 'club@example.org']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function article(Club $club, array $attributes = []): Post
    {
        return Post::create(array_merge([
            'club_id' => $club->id,
            'author_id' => User::factory()->create()->id,
            'title' => 'Installation night',
            'slug' => 'installation-night',
            'excerpt' => 'A great evening.',
            'content' => '<p>Full story</p>',
            'blocks' => [['id' => 'b1', 'type' => 'text', 'content' => '<p>The worshipful master was installed.</p>']],
            'status' => 'published',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
        ], $attributes));
    }

    public function test_a_public_article_is_shown_inside_the_site(): void
    {
        $club = $this->club();
        $this->article($club, ['attachments' => [['name' => 'Summons.pdf', 'url' => '/storage/summons.pdf', 'mime_type' => 'application/pdf', 'size' => '20 KB']]]);

        $this->get('/site/lodge-of-fraternity/news/installation-night')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Public/Site')
                ->where('page.title', 'Installation night')
                ->where('page.blocks.0.type', 'post_header')
                ->where('page.blocks.0.title', 'Installation night')
                ->where('page.blocks.1.content', '<p>The worshipful master was installed.</p>')
                ->where('page.blocks.2.type', 'post_attachments')
                ->where('page.blocks.2.items.0.name', 'Summons.pdf'));
    }

    public function test_an_article_with_only_body_text_still_shows_it(): void
    {
        $club = $this->club();
        $this->article($club, ['blocks' => []]);

        $this->get('/site/lodge-of-fraternity/news/installation-night')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('page.blocks.1.content', '<p>Full story</p>'));
    }

    public function test_the_back_link_points_to_the_page_that_lists_the_news(): void
    {
        $club = $this->club();
        Page::where('club_id', $club->id)->delete();
        Page::create(['club_id' => $club->id, 'title' => 'Lodge News', 'slug' => 'lodge-news', 'is_published' => true, 'blocks' => [['type' => 'news_feed', 'heading' => 'News']]]);
        $this->article($club);

        $this->get('/site/lodge-of-fraternity/news/installation-night')
            ->assertInertia(fn ($page) => $page->where('page.blocks.0.back_url', route('public.site', ['clubSlug' => 'lodge-of-fraternity', 'pageSlug' => 'lodge-news']))->where('page.blocks.0.back_label', 'Lodge News'));
    }

    public function test_an_unpublished_or_unknown_article_is_not_found(): void
    {
        $club = $this->club();
        $this->article($club, ['slug' => 'draft-one', 'status' => 'draft']);
        $this->article($club, ['slug' => 'later', 'published_at' => now()->addWeek()]);

        $this->get('/site/lodge-of-fraternity/news/draft-one')->assertNotFound();
        $this->get('/site/lodge-of-fraternity/news/later')->assertNotFound();
        $this->get('/site/lodge-of-fraternity/news/nothing-here')->assertNotFound();
    }

    public function test_an_article_cannot_be_read_through_another_clubs_address(): void
    {
        $this->article($this->club('lodge-a'));
        $this->club('lodge-b');

        $this->get('/site/lodge-b/news/installation-night')->assertNotFound();
    }

    public function test_a_members_only_article_sends_visitors_to_log_in_and_stays_hidden_from_non_members(): void
    {
        $club = $this->club();
        $this->article($club, ['visibility' => 'club']);

        $this->get('/site/lodge-of-fraternity/news/installation-night')->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())->get('/site/lodge-of-fraternity/news/installation-night')->assertNotFound();

        $member = User::factory()->create();
        $member->clubs()->attach($club->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($member)->get('/site/lodge-of-fraternity/news/installation-night')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('page.title', 'Installation night'));
    }

    public function test_a_news_page_still_loads_beside_its_articles(): void
    {
        $club = $this->club();
        Page::updateOrCreate(['club_id' => $club->id, 'slug' => 'news'], ['title' => 'News', 'is_published' => true, 'blocks' => [['type' => 'news_feed', 'heading' => 'News']]]);

        $this->get('/site/lodge-of-fraternity/news')->assertOk()->assertInertia(fn ($page) => $page->where('page.slug', 'news'));
    }
}
