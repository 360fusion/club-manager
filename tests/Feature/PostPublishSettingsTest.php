<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostPublishSettingsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Boating Club',
            'code' => 'boating',
            'available_modules' => ['posts', 'events', 'memberships'],
        ]);

        $this->club = Club::create([
            'name' => 'Oxford Boating Club',
            'slug' => 'oxford-boating',
            'club_type_id' => $clubType->id,
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create();
        $this->club->users()->attach($this->admin->id, ['role' => 'admin']);
    }

    public function test_admin_can_save_post_with_publish_settings(): void
    {
        $showFrom = now()->addDays(2)->format('Y-m-d\TH:i');
        $showUntil = now()->addMonth()->format('Y-m-d\TH:i');

        $response = $this->actingAs($this->admin)
            ->post(route('admin.posts.store', ['clubSlug' => $this->club->slug]), [
                'title' => 'Scheduled Announcement',
                'slug' => 'scheduled-announcement',
                'excerpt' => 'This is a scheduled post',
                'content' => 'Full article text...',
                'status' => 'published',
                'published_at' => $showFrom,
                'expires_at' => $showUntil,
            ]);

        $response->assertRedirect(route('admin.posts.index', ['clubSlug' => $this->club->slug]));

        $this->assertDatabaseHas('posts', [
            'club_id' => $this->club->id,
            'title' => 'Scheduled Announcement',
            'status' => 'published',
        ]);

        $post = Post::where('slug', 'scheduled-announcement')->firstOrFail();
        $this->assertNotNull($post->published_at);
        $this->assertNotNull($post->expires_at);
    }

    public function test_future_scheduled_post_is_hidden_from_published_scope(): void
    {
        $futurePost = Post::create([
            'club_id' => $this->club->id,
            'author_id' => $this->admin->id,
            'title' => 'Future Post',
            'slug' => 'future-post',
            'content' => 'Content',
            'status' => 'published',
            'published_at' => now()->addDays(5),
        ]);

        $activePost = Post::create([
            'club_id' => $this->club->id,
            'author_id' => $this->admin->id,
            'title' => 'Active Post',
            'slug' => 'active-post',
            'content' => 'Content',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $publishedPosts = Post::where('club_id', $this->club->id)->published()->get();

        $this->assertTrue($publishedPosts->contains('id', $activePost->id));
        $this->assertFalse($publishedPosts->contains('id', $futurePost->id));
    }

    public function test_expired_post_is_hidden_from_published_scope(): void
    {
        $expiredPost = Post::create([
            'club_id' => $this->club->id,
            'author_id' => $this->admin->id,
            'title' => 'Expired Post',
            'slug' => 'expired-post',
            'content' => 'Content',
            'status' => 'published',
            'published_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
        ]);

        $activePost = Post::create([
            'club_id' => $this->club->id,
            'author_id' => $this->admin->id,
            'title' => 'Active Post with Expiry',
            'slug' => 'active-post-expiry',
            'content' => 'Content',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'expires_at' => now()->addDays(10),
        ]);

        $publishedPosts = Post::where('club_id', $this->club->id)->published()->get();

        $this->assertTrue($publishedPosts->contains('id', $activePost->id));
        $this->assertFalse($publishedPosts->contains('id', $expiredPost->id));
    }

    public function test_unpublishing_item_sets_status_to_draft(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.posts.store', ['clubSlug' => $this->club->slug]), [
                'title' => 'Draft News Item',
                'slug' => 'draft-news-item',
                'excerpt' => 'Draft item excerpt',
                'content' => 'Draft item content',
                'status' => 'draft',
            ]);

        $response->assertRedirect(route('admin.posts.index', ['clubSlug' => $this->club->slug]));

        $this->assertDatabaseHas('posts', [
            'club_id' => $this->club->id,
            'title' => 'Draft News Item',
            'status' => 'draft',
        ]);
    }
}
