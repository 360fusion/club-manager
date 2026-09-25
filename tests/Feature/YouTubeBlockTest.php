<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Page;
use App\Models\User;
use App\Support\YouTubeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class YouTubeBlockTest extends TestCase
{
    use RefreshDatabase;

    private const ID = 'dQw4w9WgXcQ';

    private function club(): Club
    {
        $clubType = ClubType::create(['name' => 'Lodge', 'code' => 'lodge']);

        return Club::create([
            'name' => 'Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'email' => 'club@example.org',
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: array{id: string, start: int|null}|null}>
     */
    public static function links(): array
    {
        $id = self::ID;

        return [
            'watch' => ["https://www.youtube.com/watch?v=$id", ['id' => $id, 'start' => null]],
            'watch with extra params' => ["https://youtube.com/watch?feature=share&v=$id&t=90", ['id' => $id, 'start' => 90]],
            'short link with time' => ["https://youtu.be/$id?t=1m30s", ['id' => $id, 'start' => 90]],
            'embed' => ["https://www.youtube.com/embed/$id", ['id' => $id, 'start' => null]],
            'shorts' => ["https://youtube.com/shorts/$id", ['id' => $id, 'start' => null]],
            'live' => ["https://www.youtube.com/live/$id", ['id' => $id, 'start' => null]],
            'mobile' => ["https://m.youtube.com/watch?v=$id", ['id' => $id, 'start' => null]],
            'nocookie' => ["https://www.youtube-nocookie.com/embed/$id", ['id' => $id, 'start' => null]],
            'no scheme' => ["youtu.be/$id", ['id' => $id, 'start' => null]],
            'other host' => ["https://evil.example/watch?v=$id", null],
            'lookalike host' => ["https://youtube.com.evil.example/watch?v=$id", null],
            'userinfo trick' => ["https://youtube.com@evil.example/watch?v=$id", null],
            'javascript' => ['javascript:alert(1)', null],
            'too short id' => ['https://youtu.be/abc', null],
            'too long id' => ['https://youtu.be/dQw4w9WgXcQextra', null],
            'channel page' => ['https://www.youtube.com/@somechannel', null],
            'empty' => ['', null],
        ];
    }

    /**
     * @param  array{id: string, start: int|null}|null  $expected
     */
    #[DataProvider('links')]
    public function test_youtube_links_are_parsed_to_a_video_id_or_rejected(string $link, ?array $expected): void
    {
        $this->assertSame($expected, YouTubeUrl::parse($link));
    }

    public function test_a_youtube_block_is_cleaned_when_a_page_is_saved(): void
    {
        $club = $this->club();

        $page = Page::create([
            'club_id' => $club->id, 'title' => 'Videos', 'slug' => 'videos',
            'blocks' => [
                [
                    'type' => 'youtube',
                    'url' => 'https://youtu.be/'.self::ID.'?t=43',
                    'title' => '<b>Installation</b> 2026',
                    'description' => "Line one\n<script>alert(1)</script>Line two",
                    'layout' => 'diagonal',
                    'width' => 'wide',
                    'aspect' => '9:16',
                    'text_align' => 'sideways',
                    'cover_url' => 'javascript:alert(1)',
                    'start' => '43',
                    'end' => '10',
                    'loop' => 'true',
                    'button_enabled' => true,
                    'button_label' => 'Book',
                    'button_url' => 'javascript:alert(1)',
                ],
                ['type' => 'youtube', 'url' => 'https://evil.example/watch?v='.self::ID],
            ],
        ]);

        [$video, $bad] = $page->fresh()->blocks;

        $this->assertSame('https://www.youtube.com/watch?v='.self::ID, $video['url']);
        $this->assertSame(self::ID, $video['video_id']);
        $this->assertSame('Installation 2026', $video['title']);
        $this->assertStringNotContainsString('<', $video['description']);
        $this->assertSame('stacked', $video['layout']);
        $this->assertSame('wide', $video['width']);
        $this->assertSame('9:16', $video['aspect']);
        $this->assertSame('left', $video['text_align']);
        $this->assertSame('', $video['cover_url']);
        $this->assertSame('', $video['button_url']);
        $this->assertSame(43, $video['start']);
        $this->assertNull($video['end'], 'a stop time before the start is dropped');
        $this->assertTrue($video['loop']);
        $this->assertTrue($video['show_youtube_link'], 'the YouTube link defaults on');

        $this->assertSame('', $bad['url']);
        $this->assertSame('', $bad['video_id']);
    }

    public function test_a_youtube_block_saved_through_the_builder_shows_on_the_public_page(): void
    {
        $club = $this->club();
        $user = User::factory()->create();
        $user->clubs()->attach($club->id, ['role' => 'admin']);

        $this->actingAs($user)->post(route('admin.pages.store', ['clubSlug' => $club->slug]), [
            'title' => 'Videos',
            'slug' => 'videos',
            'is_published' => true,
            'show_in_navigation' => true,
            'blocks' => [['id' => 'block-1', 'type' => 'youtube', 'url' => 'https://youtu.be/'.self::ID, 'title' => 'Our dinner']],
        ])->assertRedirect();

        $this->get(route('public.site', ['clubSlug' => $club->slug, 'pageSlug' => 'videos']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('page.blocks.0.type', 'youtube')
                ->where('page.blocks.0.video_id', self::ID)
                ->where('page.blocks.0.title', 'Our dinner'));
    }

    public function test_the_security_policy_allows_the_embeds_the_blocks_use(): void
    {
        $policy = $this->get('/login')->headers->get('Content-Security-Policy-Report-Only');

        $this->assertStringContainsString('frame-src', $policy);
        $this->assertStringContainsString('https://www.youtube-nocookie.com', $policy);
        $this->assertStringContainsString('https://www.openstreetmap.org', $policy);
    }
}
