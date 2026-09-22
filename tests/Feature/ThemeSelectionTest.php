<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_themes_page_and_update_website_theme(): void
    {
        $clubType = ClubType::create(['name' => 'Lodge', 'code' => 'lodge']);
        $club = Club::create([
            'name' => 'Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'email' => 'club@example.org',
            'settings' => [
                'website_theme' => 'masonic',
            ],
        ]);

        $user = User::factory()->create();
        $user->clubs()->attach($club->id, ['role' => 'admin']);

        $this->actingAs($user);

        // Test GET themes page
        $response = $this->get(route('admin.pages.themes', ['clubSlug' => $club->slug]));
        $response->assertOk();

        // Test POST update theme to the banded layout with the Navy & Gold colour scheme
        $updateResponse = $this->post(route('admin.pages.themes.update', ['clubSlug' => $club->slug]), [
            'website_theme' => 'banded:navy_gold',
        ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHas('success', 'Website theme updated successfully.');

        $club->refresh();
        $this->assertEquals('banded:navy_gold', $club->settings['website_theme']);
    }

    public function test_public_site_renders_with_the_clubs_saved_theme(): void
    {
        $clubType = ClubType::create(['name' => 'Lodge', 'code' => 'lodge']);
        $club = Club::create([
            'name' => 'Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'email' => 'club@example.org',
            'settings' => [
                'website_theme' => 'bold:violet_coral',
            ],
        ]);

        $response = $this->get(route('public.site', ['clubSlug' => $club->slug]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('club.website_theme', 'bold:violet_coral'));
    }

    public function test_theme_update_validates_theme_name(): void
    {
        $clubType = ClubType::create(['name' => 'Lodge', 'code' => 'lodge']);
        $club = Club::create([
            'name' => 'Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'email' => 'club@example.org',
        ]);

        $user = User::factory()->create();
        $user->clubs()->attach($club->id, ['role' => 'admin']);

        $this->actingAs($user);

        $response = $this->post(route('admin.pages.themes.update', ['clubSlug' => $club->slug]), [
            'website_theme' => 'invalid_theme_name',
        ]);

        $response->assertSessionHasErrors(['website_theme']);
    }
}
