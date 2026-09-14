<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterNewsItemsTest extends TestCase
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

    public function test_admin_edit_form_passes_published_posts(): void
    {
        $club = $this->createClub();
        $admin = User::factory()->create();
        $club->users()->attach($admin->id, ['role' => 'admin']);

        Post::create([
            'club_id' => $club->id,
            'author_id' => $admin->id,
            'title' => 'Annual Summer Regatta Winners',
            'slug' => 'annual-summer-regatta-winners',
            'excerpt' => 'Congratulations to the winning teams in this year regatta.',
            'content' => '<p>Full regatta report details...</p>',
            'cover_image_url' => 'https://example.com/trophy.jpg',
            'status' => 'published',
            'published_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.newsletters.create', $club->slug));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Newsletters/Form')
            ->has('posts', 1)
        );
    }
}
