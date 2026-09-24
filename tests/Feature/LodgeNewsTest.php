<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Lodge;
use App\Models\NewsletterType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LodgeNewsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Lodge $lodge;

    private User $follower;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Craft Lodge', 'code' => 'craft_lodge', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Lodge of Industry', 'slug' => 'lodge-of-industry', 'status' => 'active']);
        $this->lodge = Lodge::factory()->create(['club_type_id' => $type->id, 'name' => 'Lodge of Industry', 'number' => '48', 'slug' => 'lodge-of-industry-48']);
        $this->lodge->forceFill(['club_id' => $this->club->id])->save();

        $this->follower = User::factory()->create();
        $this->member = User::factory()->create();
        $this->club->users()->attach($this->member->id, ['role' => 'member', 'status' => 'active']);

        // The follower belongs to some other club, as most signed-in members do.
        $home = Club::create(['club_type_id' => $type->id, 'name' => 'Home Lodge', 'slug' => 'home-lodge', 'status' => 'active']);
        $home->users()->attach($this->follower->id, ['role' => 'member', 'status' => 'active']);
        $this->follower->followedLodges()->attach($this->lodge->id, ['in_calendar' => true]);
    }

    private function makePost(string $title, string $visibility, array $overrides = [], ?Club $club = null): Post
    {
        return Post::create([
            'club_id' => ($club ?? $this->club)->id,
            'author_id' => $this->member->id,
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'content' => '<p>Body of '.$title.'</p>',
            'excerpt' => 'About '.$title,
            'status' => 'published',
            'visibility' => $visibility,
            'published_at' => now()->subDay(),
            ...$overrides,
        ]);
    }

    private function titles($response): array
    {
        return collect($response->viewData('page')['props']['posts']['data'])->pluck('title')->all();
    }

    public function test_a_followed_lodges_shared_posts_appear_in_my_news_and_its_private_ones_do_not(): void
    {
        $this->makePost('Public news', 'public');
        $this->makePost('Network news', 'network');
        $this->makePost('Lodge only news', 'club');

        $titles = $this->titles($this->actingAs($this->follower)->get(route('members.news')));

        $this->assertEqualsCanonicalizing(['Public news', 'Network news'], $titles);
        $this->assertNotContains('Lodge only news', $titles);
    }

    public function test_a_follower_who_belongs_to_no_club_only_gets_public_posts(): void
    {
        $loner = User::factory()->create();
        $loner->followedLodges()->attach($this->lodge->id, ['in_calendar' => true]);
        $this->makePost('Public news', 'public');
        $this->makePost('Network news', 'network');

        // The all-clubs page needs a club to belong to, so the page itself is where the rule lives:
        // a follower without a club is sent on, and gets nothing that a club member would.
        $this->assertSame(['Public news'], Post::visibleTo($loner)->where('club_id', $this->club->id)->pluck('title')->all());
    }

    public function test_only_followed_lodges_show_up_and_a_single_club_page_never_does(): void
    {
        $other = Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Not Followed', 'slug' => 'not-followed', 'status' => 'active']);
        Lodge::factory()->create(['slug' => 'not-followed-lodge'])->forceFill(['club_id' => $other->id])->save();
        $this->makePost('Followed', 'public');
        $this->makePost('Stranger', 'public', club: $other);

        $this->assertSame(['Followed'], $this->titles($this->actingAs($this->follower)->get(route('members.news'))));
        $this->assertSame([], $this->titles($this->actingAs($this->follower)->get(route('member.news', 'home-lodge'))));
    }

    public function test_unfollowing_removes_the_news_and_members_are_not_shown_it_twice(): void
    {
        $this->makePost('Public news', 'public');
        $this->member->followedLodges()->attach($this->lodge->id, ['in_calendar' => true]);

        $this->assertSame(['Public news'], $this->titles($this->actingAs($this->member)->get(route('members.news'))));

        $this->follower->followedLodges()->detach($this->lodge->id);
        $this->assertSame([], $this->titles($this->actingAs($this->follower)->get(route('members.news'))));
    }

    public function test_a_follower_can_read_a_shared_post_but_not_a_private_one(): void
    {
        $public = $this->makePost('Public news', 'public');
        $private = $this->makePost('Lodge only news', 'club');

        $this->actingAs($this->follower)->get(route('member.posts.show', ['slug' => 'lodge-of-industry', 'id' => $public->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isMember', false)->where('lodgeSlug', 'lodge-of-industry-48')->where('post.title', 'Public news'));

        $this->actingAs($this->follower)->get(route('member.posts.show', ['slug' => 'lodge-of-industry', 'id' => $private->id]))->assertNotFound();

        $this->actingAs($this->member)->get(route('member.posts.show', ['slug' => 'lodge-of-industry', 'id' => $private->id]))
            ->assertOk()->assertInertia(fn ($page) => $page->where('isMember', true));
    }

    public function test_the_lodge_page_lists_the_news_each_viewer_may_read(): void
    {
        $this->makePost('Public news', 'public');
        $this->makePost('Lodge only news', 'club');
        $this->makePost('Expired news', 'public', ['expires_at' => now()->subHour()]);
        $this->makePost('Draft news', 'public', ['status' => 'draft']);

        $shows = fn (array $expected) => fn ($page) => $page->where('lodge.news', fn ($news) => collect($news)->pluck('title')->sort()->values()->all() === collect($expected)->sort()->values()->all());

        $this->get(route('lodges.show', $this->lodge->slug))->assertInertia($shows(['Public news']));
        $this->actingAs($this->follower)->get(route('lodges.show', $this->lodge->slug))->assertInertia($shows(['Public news']));
        $this->actingAs($this->member)->get(route('lodges.show', $this->lodge->slug))->assertInertia($shows(['Public news', 'Lodge only news']));
    }

    public function test_the_feed_is_public_valid_xml_of_public_posts_only(): void
    {
        $this->makePost('Fish & Chips <night>', 'public', ['excerpt' => 'Tickets "now" on sale']);
        $this->makePost('Network news', 'network');
        $this->makePost('Lodge only news', 'club');

        $response = $this->get(route('lodges.feed', $this->lodge->slug))->assertOk();
        $this->assertStringStartsWith('application/rss+xml', $response->headers->get('Content-Type'));

        $feed = simplexml_load_string($response->getContent());
        $this->assertNotFalse($feed, 'The feed is not valid XML.');
        $this->assertSame('Lodge of Industry', (string) $feed->channel->title);
        $this->assertCount(1, $feed->channel->item);
        $this->assertSame('Fish & Chips <night>', (string) $feed->channel->item[0]->title);
        $this->assertSame('Tickets "now" on sale', (string) $feed->channel->item[0]->description);
        $this->assertStringNotContainsString('Lodge only news', $response->getContent());
        $this->assertStringNotContainsString('Network news', $response->getContent());
    }

    public function test_an_unmanaged_or_unlisted_lodge_has_no_feed(): void
    {
        $plain = Lodge::factory()->create(['slug' => 'plain']);
        $this->get(route('lodges.feed', $plain->slug))->assertNotFound();

        $this->lodge->update(['status' => 'erased']);
        $this->get(route('lodges.feed', $this->lodge->slug))->assertNotFound();
    }

    public function test_the_lodge_page_offers_only_the_bulletins_that_are_open_to_anyone(): void
    {
        NewsletterType::create(['club_id' => $this->club->id, 'name' => 'Visitors bulletin', 'slug' => 'visitors', 'is_external_subscribable' => true, 'require_approval' => true]);
        NewsletterType::create(['club_id' => $this->club->id, 'name' => 'Members only', 'slug' => 'members', 'is_external_subscribable' => false]);

        $this->get(route('lodges.show', $this->lodge->slug))
            ->assertInertia(fn ($page) => $page
                ->where('lodge.club_slug', 'lodge-of-industry')
                ->has('lodge.bulletins', 1)
                ->where('lodge.bulletins.0.name', 'Visitors bulletin')
                ->where('lodge.bulletins.0.needs_approval', true)
                ->where('lodge.feed_url', route('lodges.feed', $this->lodge->slug)));
    }

    public function test_an_unmanaged_lodge_shows_no_news_or_bulletins(): void
    {
        $plain = Lodge::factory()->create(['slug' => 'plain']);

        $this->get(route('lodges.show', $plain->slug))
            ->assertInertia(fn ($page) => $page->has('lodge.news', 0)->has('lodge.bulletins', 0)->where('lodge.feed_url', null)->where('lodge.visitor', null));
    }
}
