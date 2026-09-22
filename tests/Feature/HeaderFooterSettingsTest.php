<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeaderFooterSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_header_footer_page_and_update_settings(): void
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

        $response = $this->get(route('admin.pages.header_footer', ['clubSlug' => $club->slug]));
        $response->assertOk();

        $updateResponse = $this->post(route('admin.pages.header_footer.update', ['clubSlug' => $club->slug]), [
            'header_layout' => 'logo_center',
            'header_show_logo' => false,
            'header_show_tagline' => false,
            'header_cta_enabled' => true,
            'header_cta_text' => 'Join Us',
            'header_cta_link' => '/site/lodge-of-fraternity/join-us',
            'header_show_account_links' => false,
            'footer_layout' => 'columns',
            'footer_show_social' => true,
            'footer_show_nav' => true,
            'footer_copyright' => '© 2026 Lodge of Fraternity.',
            'social_facebook' => 'https://facebook.com/lodgeoffraternity',
            'social_instagram' => 'https://instagram.com/lodgeoffraternity',
            'social_twitter' => 'https://x.com/lodgeoffraternity',
        ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHas('success', 'Header & footer settings saved successfully.');

        $club->refresh();
        $this->assertSame('logo_center', $club->settings['header_layout']);
        $this->assertFalse($club->settings['header_show_logo']);
        $this->assertTrue($club->settings['header_cta_enabled']);
        $this->assertSame('Join Us', $club->settings['header_cta_text']);
        $this->assertSame('columns', $club->settings['footer_layout']);
        $this->assertTrue($club->settings['footer_show_nav']);
        $this->assertSame('https://facebook.com/lodgeoffraternity', $club->settings['social_facebook']);
    }

    public function test_header_footer_update_validates_layout_values(): void
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

        $response = $this->post(route('admin.pages.header_footer.update', ['clubSlug' => $club->slug]), [
            'header_layout' => 'not_a_real_layout',
            'footer_layout' => 'also_not_real',
        ]);

        $response->assertSessionHasErrors(['header_layout', 'footer_layout']);
    }

    public function test_footer_link_columns_are_saved_and_incomplete_rows_are_dropped(): void
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

        $updateResponse = $this->post(route('admin.pages.header_footer.update', ['clubSlug' => $club->slug]), [
            'header_layout' => 'logo_left',
            'footer_layout' => 'columns',
            'footer_link_columns' => [
                [
                    'title' => 'Useful Links',
                    'links' => [
                        ['label' => 'United Grand Lodge of England', 'url' => 'https://ugle.org.uk'],
                        // Half-filled row (no url) — should be dropped rather than fail validation.
                        ['label' => 'Untitled', 'url' => ''],
                    ],
                ],
                // Column with a title but no complete links — the whole column should be dropped.
                ['title' => 'Empty Column', 'links' => [['label' => '', 'url' => '']]],
            ],
        ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHasNoErrors();

        $club->refresh();
        $columns = $club->settings['footer_link_columns'];
        $this->assertCount(1, $columns);
        $this->assertSame('Useful Links', $columns[0]['title']);
        $this->assertCount(1, $columns[0]['links']);
        $this->assertSame('https://ugle.org.uk', $columns[0]['links'][0]['url']);
    }

    public function test_public_site_exposes_header_and_footer_defaults(): void
    {
        $clubType = ClubType::create(['name' => 'Lodge', 'code' => 'lodge']);
        $club = Club::create([
            'name' => 'Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'email' => 'club@example.org',
        ]);

        $response = $this->get(route('public.site', ['clubSlug' => $club->slug]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('site.header_layout', 'logo_left')
            ->where('site.header_show_account_links', true)
            ->where('site.header_cta_enabled', false)
            ->where('site.footer_layout', 'simple')
            ->where('site.footer_show_social', true)
        );
    }
}
