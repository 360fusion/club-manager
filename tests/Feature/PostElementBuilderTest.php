<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PostElementBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function createClub(): Club
    {
        $clubType = ClubType::firstOrCreate([
            'code' => 'masonic',
        ], [
            'name' => 'Masonic Lodge',
            'available_modules' => ['newsletters'],
            'default_settings' => [],
        ]);

        return Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Oxford Apollo Lodge',
            'slug' => 'oxford-apollo',
            'lodge_number' => '357',
            'province_region' => 'Oxfordshire',
            'town_city' => 'Oxford',
            'is_directory_listed' => true,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_create_post_with_structured_blocks_and_attachments(): void
    {
        Storage::fake('public');

        $club = $this->createClub();
        $admin = User::factory()->create();
        $club->users()->attach($admin->id, ['role' => 'admin']);

        $pdf = UploadedFile::fake()->create('Meeting-Minutes.pdf', 800, 'application/pdf');
        $cover = UploadedFile::fake()->image('banner.jpg', 1200, 400);

        $blocks = [
            [
                'id' => 'b1',
                'type' => 'text',
                'content' => '<p>Welcome to our annual regatta report.</p>',
            ],
            [
                'id' => 'b2',
                'type' => 'image',
                'url' => 'https://example.com/regatta.jpg',
                'caption' => 'Regatta Finish Line',
                'position' => 'center',
                'size' => 'large',
            ],
            [
                'id' => 'b3',
                'type' => 'notice',
                'style' => 'important',
                'title' => 'Bylaws Reminder',
                'text' => 'Please register before the deadline.',
            ],
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.posts.store', $club->slug), [
                'title' => 'Modular News Article',
                'slug' => 'modular-news-article',
                'excerpt' => 'A modular post with reorderable elements.',
                'content' => '<p>Welcome to our annual regatta report.</p>',
                'status' => 'published',
                'blocks' => $blocks,
                'cover_image' => $cover,
                'new_attachments' => [$pdf],
            ]);

        $response->assertRedirect(route('admin.posts.index', $club->slug));

        $post = Post::where('club_id', $club->id)->first();
        $this->assertNotNull($post);
        $this->assertEquals('Modular News Article', $post->title);
        $this->assertIsArray($post->blocks);
        $this->assertCount(3, $post->blocks);
        $this->assertEquals('image', $post->blocks[1]['type']);
        $this->assertEquals('large', $post->blocks[1]['size']);
        $this->assertEquals('center', $post->blocks[1]['position']);

        $this->assertIsArray($post->attachments);
        $this->assertCount(1, $post->attachments);
        $this->assertEquals('Meeting-Minutes.pdf', $post->attachments[0]['name']);
        $this->assertNotNull($post->cover_image_url);
    }
}
