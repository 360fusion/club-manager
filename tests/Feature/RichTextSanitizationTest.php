<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Support\RichTextSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RichTextSanitizationTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'code' => 'craft_lodge',
            'name' => 'Craft Lodge',
            'available_modules' => [],
        ]);

        $this->club = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Alpha Lodge',
            'slug' => 'alpha-lodge',
        ]);

        $this->author = User::factory()->create();
    }

    public function test_script_tags_are_stripped_from_post_content(): void
    {
        $post = Post::create([
            'club_id' => $this->club->id,
            'author_id' => $this->author->id,
            'title' => 'Hello',
            'slug' => 'hello',
            'content' => '<p>Welcome</p><script>alert("xss")</script>',
        ]);

        $this->assertStringNotContainsString('<script', $post->fresh()->content);
        $this->assertStringContainsString('Welcome', $post->fresh()->content);
    }

    public function test_inline_event_handlers_are_stripped(): void
    {
        $post = Post::create([
            'club_id' => $this->club->id,
            'author_id' => $this->author->id,
            'title' => 'Hello',
            'slug' => 'hello-2',
            'content' => '<p onclick="steal()">Click me</p>',
        ]);

        $content = $post->fresh()->content;

        $this->assertStringNotContainsString('onclick', $content);
        $this->assertStringContainsString('Click me', $content);
    }

    public function test_javascript_urls_are_stripped_from_links(): void
    {
        $post = Post::create([
            'club_id' => $this->club->id,
            'author_id' => $this->author->id,
            'title' => 'Hello',
            'slug' => 'hello-3',
            'content' => '<a href="javascript:alert(1)">tap</a>',
        ]);

        $this->assertStringNotContainsString('javascript:', $post->fresh()->content);
    }

    public function test_safe_formatting_survives(): void
    {
        $html = '<p><strong>Bold</strong> and <em>italic</em> with a <a href="https://example.com">link</a></p>';

        $post = Post::create([
            'club_id' => $this->club->id,
            'author_id' => $this->author->id,
            'title' => 'Hello',
            'slug' => 'hello-4',
            'content' => $html,
        ]);

        $content = $post->fresh()->content;

        $this->assertStringContainsString('<strong>Bold</strong>', $content);
        $this->assertStringContainsString('<em>italic</em>', $content);
        $this->assertStringContainsString('https://example.com', $content);
    }

    public function test_block_content_is_sanitized(): void
    {
        $page = Page::create([
            'club_id' => $this->club->id,
            'title' => 'Landing',
            'slug' => 'landing',
            'blocks' => [
                ['type' => 'text', 'content' => '<p>Fine</p><script>alert(1)</script>'],
                ['type' => 'text', 'content' => '<img src=x onerror="alert(1)">'],
            ],
        ]);

        $blocks = $page->fresh()->blocks;

        $this->assertStringNotContainsString('<script', $blocks[0]['content']);
        $this->assertStringContainsString('Fine', $blocks[0]['content']);
        $this->assertStringNotContainsString('onerror', $blocks[1]['content']);
    }

    public function test_empty_content_is_left_alone(): void
    {
        $post = Post::create([
            'club_id' => $this->club->id,
            'author_id' => $this->author->id,
            'title' => 'Hello',
            'slug' => 'hello-5',
            'content' => '',
        ]);

        $this->assertSame('', $post->fresh()->content);
    }

    public function test_sanitizer_keeps_the_editors_text_size_classes(): void
    {
        $html = RichTextSanitizer::sanitize('<p>Normal <span class="rt-size-large">big</span> text</p>');

        $this->assertStringContainsString('<span class="rt-size-large">big</span>', $html);
    }

    public function test_sanitizer_leaves_null_untouched(): void
    {
        $this->assertNull(RichTextSanitizer::sanitize(null));
    }
}
