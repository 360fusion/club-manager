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
                'website_theme' => 'classic',
            ],
        ]);

        $user = User::factory()->create();
        $user->clubs()->attach($club->id, ['role' => 'admin']);

        $this->actingAs($user);

        // Test GET themes page
        $response = $this->get(route('admin.pages.themes', ['clubSlug' => $club->slug]));
        $response->assertOk();

        // Test POST update theme to white background theme
        $updateResponse = $this->post(route('admin.pages.themes.update', ['clubSlug' => $club->slug]), [
            'website_theme' => 'light_navy',
        ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHas('success', 'Website theme updated successfully.');

        $club->refresh();
        $this->assertEquals('light_navy', $club->settings['website_theme']);
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
