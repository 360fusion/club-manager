<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\NewsTag;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class NewsTagsTest extends TestCase
{
    use RefreshDatabase;

    private Club $oxford;

    private Club $bath;

    private User $admin;

    private User $secretary;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->oxford = Club::create(['club_type_id' => $type->id, 'name' => 'Oxford Lodge', 'slug' => 'oxford-lodge', 'status' => 'active']);
        $this->bath = Club::create(['club_type_id' => $type->id, 'name' => 'Bath Lodge', 'slug' => 'bath-lodge', 'status' => 'active']);

        $this->admin = User::factory()->create();
        $this->secretary = User::factory()->create();
        $this->member = User::factory()->create();
        $this->oxford->users()->attach($this->admin->id, ['role' => 'admin', 'status' => 'active']);
        $this->oxford->users()->attach($this->secretary->id, ['role' => 'coach', 'status' => 'active']);
        $this->oxford->users()->attach($this->member->id, ['role' => 'member', 'status' => 'active']);
    }

    private function tag(Club $club, string $slug): NewsTag
    {
        return NewsTag::where('club_id', $club->id)->where('slug', $slug)->firstOrFail();
    }

    private function newsPost(Club $club, string $title, array $overrides = []): Post
    {
        return Post::create(array_merge([
            'club_id' => $club->id,
            'author_id' => $this->admin->id,
            'title' => $title,
            'slug' => str($title)->slug().'-'.uniqid(),
            'content' => '<p>Body</p>',
            'status' => 'published',
            'visibility' => Visibility::Club,
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function tagged(Club $club, string $title, array $slugs, array $overrides = []): Post
    {
        $post = $this->newsPost($club, $title, $overrides);
        $post->tags()->sync(NewsTag::where('club_id', $club->id)->whereIn('slug', $slugs)->pluck('id'));

        return $post;
    }

    // ---- default tags -------------------------------------------------------

    public function test_a_new_club_starts_with_the_default_tags(): void
    {
        $names = NewsTag::where('club_id', $this->oxford->id)->orderBy('name')->pluck('name')->all();

        $this->assertSame([
            'Ceremonies & Initiations',
            'Charity & Community',
            'Events & Social',
            'Lodge News',
            'Long Service Awards',
            'Provincial News',
        ], $names);
    }

    public function test_ensuring_defaults_twice_does_not_duplicate_or_restore_edits(): void
    {
        $this->tag($this->oxford, 'lodge-news')->update(['color' => 'red']);

        NewsTag::ensureDefaults($this->oxford);

        $this->assertSame(6, NewsTag::where('club_id', $this->oxford->id)->count());
        $this->assertSame('red', $this->tag($this->oxford, 'lodge-news')->color);
    }

    // ---- tagging a post -----------------------------------------------------

    public function test_a_secretary_can_tag_a_post_from_the_clubs_list(): void
    {
        $lodgeNews = $this->tag($this->oxford, 'lodge-news');
        $events = $this->tag($this->oxford, 'events-social');

        $this->actingAs($this->secretary)
            ->post(route('admin.posts.store', ['clubSlug' => 'oxford-lodge']), [
                'title' => 'Tagged',
                'slug' => 'tagged',
                'status' => 'published',
                'tag_ids' => [$lodgeNews->id, $events->id],
            ])
            ->assertSessionHasNoErrors();

        $post = Post::where('slug', 'tagged')->firstOrFail();
        $this->assertEqualsCanonicalizing([$lodgeNews->id, $events->id], $post->tags()->pluck('news_tags.id')->all());

        // Saving again without tags clears them.
        $this->actingAs($this->secretary)->post(route('admin.posts.store', ['clubSlug' => 'oxford-lodge']), [
            'id' => $post->id, 'title' => 'Tagged', 'slug' => 'tagged', 'status' => 'published',
        ]);
        $this->assertSame(0, $post->tags()->count());
    }

    public function test_another_clubs_tag_cannot_be_attached(): void
    {
        $foreign = $this->tag($this->bath, 'lodge-news');

        $this->actingAs($this->admin)
            ->post(route('admin.posts.store', ['clubSlug' => 'oxford-lodge']), [
                'title' => 'Sneaky', 'slug' => 'sneaky', 'status' => 'published', 'tag_ids' => [$foreign->id],
            ])
            ->assertSessionHasErrors('tag_ids.0');

        $this->assertDatabaseMissing('posts', ['slug' => 'sneaky']);
    }

    public function test_the_post_form_offers_only_this_clubs_tags_and_the_posts_selection(): void
    {
        $post = $this->tagged($this->oxford, 'Selected', ['lodge-news']);

        $this->actingAs($this->secretary)
            ->get(route('admin.posts.edit', ['clubSlug' => 'oxford-lodge', 'id' => $post->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('tags', 6)
                ->where('post.tag_ids', [$this->tag($this->oxford, 'lodge-news')->id]));
    }

    public function test_duplicating_a_post_copies_its_tags(): void
    {
        $post = $this->tagged($this->oxford, 'Original', ['provincial-news']);

        $this->actingAs($this->admin)->post(route('admin.posts.store', ['clubSlug' => 'oxford-lodge']), [
            'id' => $post->id, 'title' => 'Original', 'slug' => $post->slug, 'status' => 'published',
            'tag_ids' => [$this->tag($this->oxford, 'provincial-news')->id], 'action_type' => 'save_and_duplicate',
        ]);

        $copy = Post::where('title', 'Original (Copy)')->firstOrFail();
        $this->assertSame(['provincial-news'], $copy->tags()->pluck('news_tags.slug')->all());
    }

    // ---- managing the tag list ----------------------------------------------

    public function test_an_admin_can_add_rename_and_delete_tags(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.news_tags.store', ['clubSlug' => 'oxford-lodge']), ['name' => 'Masonic Charitable Foundation', 'color' => 'teal'])
            ->assertSessionHasNoErrors();

        $tag = $this->tag($this->oxford, 'masonic-charitable-foundation');
        $this->assertSame('teal', $tag->color);

        $this->actingAs($this->admin)
            ->put(route('admin.settings.news_tags.update', ['clubSlug' => 'oxford-lodge', 'id' => $tag->id]), ['name' => 'MCF Appeals', 'color' => 'teal'])
            ->assertSessionHasNoErrors();
        $this->assertSame('mcf-appeals', $tag->refresh()->slug);

        $post = $this->tagged($this->oxford, 'Uses tag', ['mcf-appeals']);
        $this->actingAs($this->admin)->delete(route('admin.settings.news_tags.destroy', ['clubSlug' => 'oxford-lodge', 'id' => $tag->id]));

        $this->assertDatabaseMissing('news_tags', ['id' => $tag->id]);
        $this->assertSame(0, $post->tags()->count());
    }

    public function test_tag_names_must_be_unique_within_a_club_but_not_across_clubs(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.news_tags.store', ['clubSlug' => 'oxford-lodge']), ['name' => 'Lodge News'])
            ->assertSessionHasErrors('name');

        $this->oxford->newsTags()->create(['name' => 'Only Here', 'slug' => 'only-here']);
        $baths = User::factory()->create();
        $this->bath->users()->attach($baths->id, ['role' => 'admin', 'status' => 'active']);

        $this->actingAs($baths)
            ->post(route('admin.settings.news_tags.store', ['clubSlug' => 'bath-lodge']), ['name' => 'Only Here'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_secretary_cannot_manage_tags_and_no_admin_can_touch_another_clubs(): void
    {
        $routeArgs = ['clubSlug' => 'oxford-lodge'];

        $this->actingAs($this->secretary)
            ->post(route('admin.settings.news_tags.store', $routeArgs), ['name' => 'Nope'])
            ->assertForbidden();
        $this->actingAs($this->member)
            ->post(route('admin.settings.news_tags.store', $routeArgs), ['name' => 'Nope'])
            ->assertForbidden();
        $this->assertDatabaseMissing('news_tags', ['name' => 'Nope']);

        $bathTag = $this->tag($this->bath, 'lodge-news');
        $this->actingAs($this->admin)
            ->delete(route('admin.settings.news_tags.destroy', ['clubSlug' => 'oxford-lodge', 'id' => $bathTag->id]))
            ->assertNotFound();
        $this->assertDatabaseHas('news_tags', ['id' => $bathTag->id]);
    }

    public function test_the_settings_page_lists_tags_with_their_use(): void
    {
        $this->tagged($this->oxford, 'One', ['lodge-news']);
        $this->tagged($this->oxford, 'Two', ['lodge-news']);

        $this->actingAs($this->admin)
            ->get(route('admin.settings.show', ['clubSlug' => 'oxford-lodge']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('newsTags', 6)
                ->where('newsTags', fn ($tags) => collect($tags)->firstWhere('slug', 'lodge-news')['posts_count'] === 2));
    }

    // ---- members list filters -----------------------------------------------

    public function test_the_members_list_filters_by_tag_search_and_date(): void
    {
        $this->tagged($this->oxford, 'Charity dinner', ['charity-community'], ['published_at' => now()->subDays(2)]);
        $this->tagged($this->oxford, 'Installation night', ['ceremonies-initiations'], ['published_at' => now()->subDays(30)]);
        $this->newsPost($this->oxford, 'Untagged notice', ['content' => '<p>Bring a torch</p>', 'published_at' => now()->subDays(10)]);

        $titles = fn (string $query) => collect($this->actingAs($this->member)->get('/members/news'.$query)->viewData('page')['props']['posts']['data'])->pluck('title')->all();

        $this->assertCount(3, $titles(''));
        $this->assertSame(['Charity dinner'], $titles('?tags[]=charity-community'));
        $this->assertEqualsCanonicalizing(['Charity dinner', 'Installation night'], $titles('?tags[]=charity-community&tags[]=ceremonies-initiations'));
        $this->assertSame(['Installation night'], $titles('?q=installation'));
        $this->assertSame(['Untagged notice'], $titles('?q=torch'));
        $this->assertEqualsCanonicalizing(['Charity dinner', 'Untagged notice'], $titles('?from='.now()->subDays(12)->toDateString()));
        $this->assertSame(['Installation night'], $titles('?to='.now()->subDays(20)->toDateString()));
        $this->assertSame([], $titles('?q=nothing-like-this'));
    }

    public function test_search_treats_wildcards_literally_and_ignores_bad_input(): void
    {
        $this->newsPost($this->oxford, 'Plain title');

        $this->actingAs($this->member)->get('/members/news?q=%25')->assertInertia(fn (Assert $page) => $page->has('posts.data', 0));
        $this->actingAs($this->member)->get('/members/news?from=not-a-date&tags=oops')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('posts.data', 1)->where('filters.from', null));
    }

    public function test_club_filter_and_tag_options_respect_scope_and_visibility(): void
    {
        $this->bath->users()->attach($this->member->id, ['role' => 'member', 'status' => 'active']);
        $this->tagged($this->oxford, 'Oxford one', ['lodge-news']);
        $this->tagged($this->bath, 'Bath one', ['lodge-news']);
        $this->tagged($this->bath, 'Bath draft', ['provincial-news'], ['status' => 'draft']);
        $this->tagged($this->bath, 'Bath expired', ['long-service-awards'], ['expires_at' => now()->subDay()]);

        $this->actingAs($this->member)->get('/members/news')->assertInertia(fn (Assert $page) => $page
            ->has('posts.data', 2)
            ->has('tagOptions', 1)
            ->where('tagOptions.0.slug', 'lodge-news')
            ->where('tagOptions.0.count', 2));

        $this->actingAs($this->member)->get('/members/news?club=bath-lodge')->assertInertia(fn (Assert $page) => $page
            ->has('posts.data', 1)->where('posts.data.0.title', 'Bath one')->where('tagOptions.0.count', 1));

        // A club the member does not belong to never contributes tags or posts.
        $outsider = User::factory()->create();
        $this->oxford->users()->attach($outsider->id, ['role' => 'member', 'status' => 'active']);
        $this->tagged($this->bath, 'Bath members only', ['events-social']);
        $this->actingAs($outsider)->get('/members/news')->assertInertia(fn (Assert $page) => $page->has('posts.data', 1)->has('tagOptions', 1));
    }

    // ---- members detail sidebar ---------------------------------------------

    public function test_the_detail_page_lists_tags_latest_and_related_news_for_this_club_only(): void
    {
        $current = $this->tagged($this->oxford, 'Current', ['charity-community', 'lodge-news'], ['published_at' => now()->subDays(5)]);
        $this->tagged($this->oxford, 'Shares two', ['charity-community', 'lodge-news'], ['published_at' => now()->subDays(9)]);
        $this->tagged($this->oxford, 'Shares one', ['charity-community'], ['published_at' => now()->subDay()]);
        $this->tagged($this->oxford, 'Unrelated', ['events-social'], ['published_at' => now()->subDays(2)]);
        $this->tagged($this->oxford, 'Hidden draft', ['charity-community'], ['status' => 'draft']);
        $this->tagged($this->bath, 'Other club', ['charity-community']);

        $this->actingAs($this->member)
            ->get(route('member.posts.show', ['slug' => 'oxford-lodge', 'id' => $current->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.tags.0.slug', fn ($slug) => in_array($slug, ['charity-community', 'lodge-news'], true))
                ->has('post.tags', 2)
                ->where('related.0.title', 'Shares two')
                ->where('related.1.title', 'Shares one')
                ->has('related', 2)
                ->where('latest', fn ($latest) => collect($latest)->pluck('title')->all() === ['Shares one', 'Unrelated', 'Shares two'])
                ->where('tagOptions', fn ($tags) => collect($tags)->firstWhere('slug', 'charity-community')['count'] === 3));
    }

    public function test_an_untagged_post_has_no_related_news_and_a_private_post_stays_private(): void
    {
        $untagged = $this->newsPost($this->oxford, 'Untagged');
        $this->tagged($this->oxford, 'Members only', ['lodge-news'], ['visibility' => Visibility::Club]);

        $this->actingAs($this->member)
            ->get(route('member.posts.show', ['slug' => 'oxford-lodge', 'id' => $untagged->id]))
            ->assertInertia(fn (Assert $page) => $page->has('related', 0)->has('latest', 1));

        $stranger = User::factory()->create();
        $this->bath->users()->attach($stranger->id, ['role' => 'member', 'status' => 'active']);
        $public = $this->tagged($this->oxford, 'Public one', ['lodge-news'], ['visibility' => Visibility::Public]);

        $this->actingAs($stranger)
            ->get(route('member.posts.show', ['slug' => 'oxford-lodge', 'id' => $public->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('latest', 0)
                ->has('related', 0)
                ->where('tagOptions.0.count', 1));
    }
}
