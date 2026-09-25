<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Page;
use App\Models\User;
use App\Support\BlockIcons;
use App\Support\SiteThemes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionStylingAndLodgeBlocksTest extends TestCase
{
    use RefreshDatabase;

    private function club(string $slug = 'lodge-of-fraternity'): Club
    {
        $clubType = ClubType::firstOrCreate(['code' => 'lodge'], ['name' => 'Lodge']);

        return Club::create(['name' => 'Lodge of Fraternity', 'slug' => $slug, 'club_type_id' => $clubType->id, 'email' => 'club@example.org']);
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function saved(array $block): array
    {
        $club = Club::firstWhere('slug', 'lodge-of-fraternity') ?? $this->club();

        return Page::create(['club_id' => $club->id, 'title' => 'Blocks', 'slug' => 'blocks-'.uniqid(), 'blocks' => [$block]])->fresh()->blocks[0];
    }

    public function test_a_block_without_section_settings_is_stored_untouched(): void
    {
        $block = $this->saved(['type' => 'text', 'content' => '<p>Hi</p>']);

        $this->assertArrayNotHasKey('section', $block);
    }

    public function test_a_valid_section_is_kept(): void
    {
        $block = $this->saved(['type' => 'text', 'content' => '<p>Hi</p>', 'section' => [
            'mode' => 'band', 'bg' => 'custom', 'bg_color' => '#1B2A4A', 'tone' => 'dark', 'padding' => 'xl', 'anchor' => 'about-us',
        ]]);

        $this->assertSame('band', $block['section']['mode']);
        $this->assertSame('custom', $block['section']['bg']);
        $this->assertSame('#1b2a4a', $block['section']['bg_color']);
        $this->assertSame('dark', $block['section']['tone']);
        $this->assertSame('xl', $block['section']['padding']);
        $this->assertSame('about-us', $block['section']['anchor']);
    }

    public function test_a_section_is_cleaned_when_saved(): void
    {
        $block = $this->saved(['type' => 'notice', 'text' => 'x', 'section' => [
            'mode' => 'diagonal', 'bg' => 'custom', 'bg_color' => 'red; background:url(x)', 'tone' => 'neon', 'padding' => 'huge',
            'anchor' => '  Our <Story>! ', 'overlay' => 'blinding',
        ]]);

        $this->assertSame('auto', $block['section']['mode']);
        $this->assertSame('none', $block['section']['bg'], 'a custom background with no valid colour falls back to none');
        $this->assertSame('', $block['section']['bg_color']);
        $this->assertSame('auto', $block['section']['tone']);
        $this->assertSame('auto', $block['section']['padding']);
        $this->assertSame('medium', $block['section']['overlay']);
        $this->assertSame('our-story', $block['section']['anchor']);
    }

    public function test_a_section_photo_must_be_a_safe_address(): void
    {
        $bad = $this->saved(['type' => 'text', 'content' => 'x', 'section' => ['mode' => 'band', 'bg' => 'image', 'bg_image' => 'javascript:alert(1)']]);
        $this->assertSame('none', $bad['section']['bg']);
        $this->assertSame('', $bad['section']['bg_image']);

        $good = $this->saved(['type' => 'text', 'content' => 'x', 'section' => ['mode' => 'band', 'bg' => 'image', 'bg_image' => 'https://example.org/p.jpg']]);
        $this->assertSame('image', $good['section']['bg']);
        $this->assertSame('https://example.org/p.jpg', $good['section']['bg_image']);
    }

    public function test_a_malformed_section_becomes_the_theme_default(): void
    {
        $block = $this->saved(['type' => 'text', 'content' => 'x', 'section' => 'navy']);

        $this->assertSame('auto', $block['section']['mode']);
    }

    public function test_feature_cards_are_cleaned_and_capped(): void
    {
        $items = [['id' => 'a', 'icon' => 'heart', 'title' => '<b>Charity</b>', 'text' => 'Giving', 'link' => 'javascript:alert(1)']];
        $items[] = ['icon' => '<script>', 'title' => 'Odd icon'];
        $items[] = ['icon' => '', 'title' => '', 'text' => ''];
        $items[] = 'not an item';
        for ($i = 0; $i < 20; $i++) {
            $items[] = ['title' => "Card $i"];
        }

        $block = $this->saved(['type' => 'feature_cards', 'heading' => '<i>Offers</i>', 'columns' => '9', 'card_style' => 'neon', 'icon_style' => 'square', 'items' => $items]);

        $this->assertSame('Offers', $block['heading']);
        $this->assertSame(3, $block['columns']);
        $this->assertSame('soft', $block['card_style']);
        $this->assertSame('plain', $block['icon_style']);
        $this->assertTrue($block['show_divider']);
        $this->assertCount(12, $block['items']);
        $this->assertSame('heart', $block['items'][0]['icon']);
        $this->assertSame('Charity', $block['items'][0]['title']);
        $this->assertSame('', $block['items'][0]['link']);
        $this->assertSame('', $block['items'][1]['icon'], 'an icon that is not on the list is dropped');
    }

    public function test_feature_card_columns_can_be_two_or_four(): void
    {
        $this->assertSame(4, $this->saved(['type' => 'feature_cards', 'columns' => 4, 'items' => []])['columns']);
        $this->assertSame(2, $this->saved(['type' => 'feature_cards', 'columns' => '2', 'items' => []])['columns']);
    }

    public function test_stats_keep_text_figures_and_drop_empty_ones(): void
    {
        $items = [
            ['icon' => 'users', 'number' => '150', 'suffix' => '+', 'label' => 'Years'],
            ['number' => '£1.2m', 'label' => '<b>Raised</b>'],
            ['number' => '', 'label' => ''],
        ];
        for ($i = 0; $i < 12; $i++) {
            $items[] = ['number' => (string) $i, 'label' => 'Extra'];
        }

        $block = $this->saved(['type' => 'stats', 'icon_style' => 'square', 'count_up' => 'no', 'items' => $items]);

        $this->assertSame('circle', $block['icon_style']);
        $this->assertFalse($block['count_up']);
        $this->assertCount(8, $block['items']);
        $this->assertSame('150', $block['items'][0]['number']);
        $this->assertSame('+', $block['items'][0]['suffix']);
        $this->assertSame('£1.2m', $block['items'][1]['number']);
        $this->assertSame('Raised', $block['items'][1]['label']);
    }

    public function test_motto_and_section_heading_are_plain_text(): void
    {
        $motto = $this->saved(['type' => 'quote_motto', 'heading' => '<script>x</script>Fraternus', 'text' => '<b>Line</b>', 'tagline' => 'Brotherly <i>love</i>']);
        $this->assertSame('xFraternus', $motto['heading']);
        $this->assertSame('Line', $motto['text']);
        $this->assertSame('Brotherly love', $motto['tagline']);

        $heading = $this->saved(['type' => 'section_heading', 'title' => '<b>About</b>', 'align' => 'right']);
        $this->assertSame('About', $heading['title']);
        $this->assertSame('center', $heading['align']);
        $this->assertTrue($heading['show_divider']);
    }

    public function test_an_older_hero_is_left_as_it_was_and_a_new_one_is_clamped(): void
    {
        $old = $this->saved(['type' => 'hero', 'title' => 'Welcome', 'cta_link' => 'example.org']);
        $this->assertSame('https://example.org', $old['cta_link']);
        $this->assertArrayNotHasKey('eyebrow', $old, 'an older hero keeps its fixed label');
        $this->assertArrayNotHasKey('align', $old);

        $new = $this->saved(['type' => 'hero', 'title' => 'Welcome', 'eyebrow' => '<b>Est. 1874</b>', 'hide_eyebrow' => 'true', 'align' => 'spiral', 'height' => 'giant', 'overlay' => 'x', 'image_url' => 'javascript:alert(1)', 'cta2_text' => 'More', 'cta2_link' => 'javascript:alert(1)']);
        $this->assertSame('Est. 1874', $new['eyebrow']);
        $this->assertTrue($new['hide_eyebrow']);
        $this->assertSame(['auto', 'normal', 'medium'], [$new['align'], $new['height'], $new['overlay']]);
        $this->assertSame('', $new['image_url']);
        $this->assertSame('', $new['cta2_link']);
    }

    public function test_a_cta_banner_can_be_a_panel_with_a_side_photo(): void
    {
        $block = $this->saved(['type' => 'cta_banner', 'style' => 'panel', 'side_image_url' => 'https://example.org/me.jpg', 'image_shape' => 'hexagon', 'image_side' => 'right', 'text_style' => 'italic']);

        $this->assertSame('panel', $block['style']);
        $this->assertSame('https://example.org/me.jpg', $block['side_image_url']);
        $this->assertSame('circle', $block['image_shape']);
        $this->assertSame('right', $block['image_side']);
        $this->assertSame('italic', $block['text_style']);

        $unsafe = $this->saved(['type' => 'cta_banner', 'style' => 'panel', 'side_image_url' => 'javascript:alert(1)']);
        $this->assertSame('', $unsafe['side_image_url']);
    }

    public function test_a_slideshow_is_cleaned_and_capped(): void
    {
        $slides = [
            ['id' => 'a', 'image_url' => 'https://example.org/one.jpg', 'alt' => '<b>Lodge</b> room', 'caption' => 'Founders'],
            ['image_url' => 'javascript:alert(1)', 'alt' => 'bad'],
            ['image_url' => '', 'alt' => 'empty'],
            'not a slide',
        ];
        for ($i = 0; $i < 20; $i++) {
            $slides[] = ['image_url' => "https://example.org/p$i.jpg"];
        }

        $block = $this->saved(['type' => 'slideshow', 'height' => 'huge', 'overlay' => 'x', 'effect' => 'spin', 'align' => 'up', 'interval' => '99', 'autoplay' => 'no', 'button_url' => 'javascript:alert(1)', 'button2_url' => 'example.org', 'slides' => $slides]);

        $this->assertCount(12, $block['slides']);
        $this->assertSame('https://example.org/one.jpg', $block['slides'][0]['image_url']);
        $this->assertSame('Lodge room', $block['slides'][0]['alt']);
        $this->assertSame(['normal', 'medium', 'fade', 'left'], [$block['height'], $block['overlay'], $block['effect'], $block['align']]);
        $this->assertSame(5, $block['interval'], 'an out-of-range time falls back to the default');
        $this->assertFalse($block['autoplay']);
        $this->assertTrue($block['show_dots']);
        $this->assertFalse($block['full_width']);
        $this->assertSame('', $block['button_url']);
        $this->assertSame('https://example.org', $block['button2_url']);

        $this->assertSame(8, $this->saved(['type' => 'slideshow', 'interval' => 8, 'slides' => []])['interval']);
    }

    public function test_an_element_can_be_hidden_and_is_left_out_of_the_public_page(): void
    {
        $club = $this->club();
        $page = Page::create(['club_id' => $club->id, 'title' => 'Hide', 'slug' => 'hide', 'is_published' => true, 'blocks' => [
            ['id' => 'a', 'type' => 'text', 'content' => '<p>Shown text</p>'],
            ['id' => 'b', 'type' => 'text', 'content' => '<p>Secret text</p>', 'hidden' => 'true'],
            ['id' => 'c', 'type' => 'text', 'content' => '<p>Also shown</p>', 'hidden' => false],
        ]])->fresh();

        $this->assertTrue($page->blocks[1]['hidden']);
        $this->assertFalse($page->blocks[2]['hidden']);
        $this->assertArrayNotHasKey('hidden', $page->blocks[0]);

        $this->get('/site/lodge-of-fraternity/hide')
            ->assertOk()
            ->assertInertia(fn ($inertia) => $inertia->has('page.blocks', 2)->where('page.blocks.0.id', 'a')->where('page.blocks.1.id', 'c'));

        $this->assertStringNotContainsString('Secret text', $this->get('/site/lodge-of-fraternity/hide')->getContent());
    }

    public function test_the_icon_list_matches_the_drawings_the_editor_ships(): void
    {
        preg_match_all("/^    '([a-z0-9-]+)': \\{ label:/m", file_get_contents(resource_path('js/Support/icons.js')), $matches);

        $this->assertEqualsCanonicalizing(BlockIcons::NAMES, $matches[1]);
        $this->assertSame('', BlockIcons::clean('not-an-icon'));
        $this->assertSame('heart', BlockIcons::clean('heart'));
    }

    public function test_the_theme_lists_match_the_javascript_theme_table(): void
    {
        $js = file_get_contents(resource_path('js/Support/siteThemes.js'));

        preg_match_all("/^        id: '([a-z_]+)',$/m", $js, $ids);
        $known = $ids[1];

        foreach ([...SiteThemes::LAYOUTS, ...SiteThemes::COLOR_SCHEMES] as $id) {
            $this->assertContains($id, $known, "siteThemes.js does not define '{$id}'");
        }

        $this->assertContains('traditional:lodge_navy_gold', SiteThemes::keys());
    }

    public function test_the_lodge_theme_and_font_pairing_can_be_saved(): void
    {
        $club = $this->club('themed-lodge');
        $user = User::factory()->create();
        $user->clubs()->attach($club->id, ['role' => 'admin']);

        $this->actingAs($user)
            ->post(route('admin.pages.themes.update', ['clubSlug' => $club->slug]), ['website_theme' => 'traditional:lodge_navy_gold', 'font_pairing' => 'lodge'])
            ->assertSessionHasNoErrors();

        $settings = $club->fresh()->settings;
        $this->assertSame('traditional:lodge_navy_gold', $settings['website_theme']);
        $this->assertSame('lodge', $settings['font_pairing']);

        $this->post(route('admin.pages.themes.update', ['clubSlug' => $club->slug]), ['website_theme' => 'traditional:nope'])
            ->assertSessionHasErrors('website_theme');
    }

    private function themedAdmin(string $slug): array
    {
        $club = $this->club($slug);
        $user = User::factory()->create();
        $user->clubs()->attach($club->id, ['role' => 'admin']);

        return [$club, $user];
    }

    public function test_a_lodge_can_create_a_named_colour_scheme_and_use_it(): void
    {
        [$club, $user] = $this->themedAdmin('coloured-lodge');
        $this->actingAs($user);

        $this->post(route('admin.pages.colour_schemes.save', ['clubSlug' => $club->slug]), ['id' => 'custom-abcd1234', 'name' => '  <b>Lodge</b> crimson ', 'primary' => '#4A0D18', 'accent' => '#C8A961'])
            ->assertSessionHasNoErrors();

        $this->assertSame([['id' => 'custom-abcd1234', 'name' => 'Lodge crimson', 'primary' => '#4a0d18', 'accent' => '#c8a961']], $club->fresh()->settings['custom_color_schemes']);

        $this->post(route('admin.pages.themes.update', ['clubSlug' => $club->slug]), ['website_theme' => 'traditional:custom-abcd1234'])->assertSessionHasNoErrors();
        $this->assertSame('traditional:custom-abcd1234', $club->fresh()->settings['website_theme']);

        $this->get('/site/coloured-lodge')->assertInertia(fn ($page) => $page
            ->where('club.website_theme', 'traditional:custom-abcd1234')
            ->where('site.custom_color_schemes.0.name', 'Lodge crimson')
            ->where('site.custom_color_schemes.0.primary', '#4a0d18'));

        // Saving the same id again renames and recolours it rather than adding a second one.
        $this->post(route('admin.pages.colour_schemes.save', ['clubSlug' => $club->slug]), ['id' => 'custom-abcd1234', 'name' => 'Renamed', 'primary' => '#111111', 'accent' => '#eeeeee']);
        $this->assertCount(1, $club->fresh()->settings['custom_color_schemes']);
        $this->assertSame('Renamed', $club->fresh()->settings['custom_color_schemes'][0]['name']);
    }

    public function test_a_colour_scheme_must_have_a_valid_id_name_and_colours(): void
    {
        [$club, $user] = $this->themedAdmin('strict-lodge');
        $this->actingAs($user);
        $url = route('admin.pages.colour_schemes.save', ['clubSlug' => $club->slug]);
        $good = ['id' => 'custom-abcd1234', 'name' => 'Ok', 'primary' => '#4a0d18', 'accent' => '#c8a961'];

        $this->post($url, ['id' => 'nope'] + $good)->assertSessionHasErrors('id');
        $this->post($url, ['id' => 'custom-ABCD1234'] + $good)->assertSessionHasErrors('id');
        $this->post($url, ['name' => ''] + $good)->assertSessionHasErrors('name');
        $this->post($url, ['name' => '<i></i>'] + $good)->assertSessionHasErrors('name');
        $this->post($url, ['primary' => 'red'] + $good)->assertSessionHasErrors('primary');
        $this->post($url, ['accent' => '#4a0d18;background:url(x)'] + $good)->assertSessionHasErrors('accent');

        $this->assertArrayNotHasKey('custom_color_schemes', $club->fresh()->settings ?? []);
    }

    public function test_a_lodge_can_keep_only_a_limited_number_of_colour_schemes(): void
    {
        [$club, $user] = $this->themedAdmin('busy-lodge');
        $this->actingAs($user);
        $url = route('admin.pages.colour_schemes.save', ['clubSlug' => $club->slug]);

        for ($i = 0; $i < SiteThemes::MAX_CUSTOM_SCHEMES; $i++) {
            $this->post($url, ['id' => sprintf('custom-%08d', $i), 'name' => "Scheme $i", 'primary' => '#4a0d18', 'accent' => '#c8a961'])->assertSessionHasNoErrors();
        }

        $this->post($url, ['id' => 'custom-zzzzzzzz', 'name' => 'One too many', 'primary' => '#4a0d18', 'accent' => '#c8a961'])->assertSessionHasErrors('name');
        $this->assertCount(SiteThemes::MAX_CUSTOM_SCHEMES, $club->fresh()->settings['custom_color_schemes']);
        $this->assertSame(2, SiteThemes::MAX_CUSTOM_SCHEMES);

        // An existing scheme can still be edited when the limit is reached.
        $this->post($url, ['id' => 'custom-00000000', 'name' => 'Edited', 'primary' => '#111111', 'accent' => '#eeeeee'])->assertSessionHasNoErrors();
    }

    public function test_only_a_lodges_own_colour_schemes_can_be_applied_and_the_live_one_cannot_be_deleted(): void
    {
        [$club, $user] = $this->themedAdmin('owner-lodge');
        [$other] = $this->themedAdmin('other-lodge');
        $other->update(['settings' => ['custom_color_schemes' => [['id' => 'custom-otherone', 'name' => 'Theirs', 'primary' => '#111111', 'accent' => '#eeeeee']]]]);

        $this->actingAs($user);
        $themes = route('admin.pages.themes.update', ['clubSlug' => $club->slug]);

        $this->post($themes, ['website_theme' => 'traditional:custom-otherone'])->assertSessionHasErrors('website_theme');
        $this->post($themes, ['website_theme' => 'nonsense:custom-abcd1234'])->assertSessionHasErrors('website_theme');

        $this->post(route('admin.pages.colour_schemes.save', ['clubSlug' => $club->slug]), ['id' => 'custom-abcd1234', 'name' => 'Mine', 'primary' => '#4a0d18', 'accent' => '#c8a961']);
        $this->post($themes, ['website_theme' => 'bold:custom-abcd1234'])->assertSessionHasNoErrors();

        $delete = route('admin.pages.colour_schemes.delete', ['clubSlug' => $club->slug, 'schemeId' => 'custom-abcd1234']);
        $this->delete($delete)->assertSessionHasErrors('scheme');
        $this->assertCount(1, $club->fresh()->settings['custom_color_schemes']);

        $this->post($themes, ['website_theme' => 'bold:navy_gold']);
        $this->delete($delete)->assertSessionHasNoErrors();
        $this->assertSame([], $club->fresh()->settings['custom_color_schemes']);
    }

    public function test_colours_saved_the_old_way_become_a_named_colour_scheme(): void
    {
        $club = $this->club('old-lodge');
        $club->update(['settings' => ['website_theme' => 'traditional:navy_gold', 'theme_colors' => ['primary' => '#e854cd', 'accent' => '#ac92d9']]]);
        $legacy = $this->club('masonic-lodge');
        $legacy->update(['settings' => ['website_theme' => 'masonic']]);

        (require database_path('migrations/2026_09_25_210000_turn_theme_colours_into_a_named_colour_scheme.php'))->up();

        $settings = $club->fresh()->settings;
        $this->assertArrayNotHasKey('theme_colors', $settings);
        $this->assertSame('Custom colours', $settings['custom_color_schemes'][0]['name']);
        $this->assertSame('#e854cd', $settings['custom_color_schemes'][0]['primary']);
        $this->assertSame('traditional:'.$settings['custom_color_schemes'][0]['id'], $settings['website_theme']);
        $this->assertTrue(SiteThemes::isValidFor($settings['website_theme'], $club->fresh()));
        $this->assertSame('masonic', $legacy->fresh()->settings['website_theme']);
    }

    public function test_the_public_site_components_carry_no_hard_coded_blue(): void
    {
        $files = [
            'js/Components/Site/PublicHeader.vue', 'js/Components/Site/PublicFooter.vue', 'js/Components/Site/AnnouncementBar.vue',
            'js/Components/Site/CookieNotice.vue', 'js/Components/Site/SiteAccountMenu.vue', 'js/Pages/Public/Site.vue',
            'js/Components/Blocks/BlockRenderer.vue', 'js/Components/Blocks/HeroBlock.vue', 'js/Components/Blocks/SlideshowBlock.vue',
            'js/Components/Blocks/CtaBannerBlock.vue', 'js/Components/Blocks/TileMap.vue', 'js/Components/Blocks/FeatureCardsBlock.vue',
            'js/Components/Blocks/StatsBlock.vue', 'js/Components/Blocks/QuoteMottoBlock.vue', 'js/Components/Blocks/PostHeaderBlock.vue',
        ];

        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('/\b(?:blue|indigo|sky|slate)-\d/', file_get_contents(resource_path($file)), "{$file} hard-codes a blue or slate colour; use the theme colours");
        }

        $themes = file_get_contents(resource_path('js/Support/siteThemes.js'));

        // A button or bar filled with the accent must take its text colour from the accent (--cm-on-accent), never a fixed colour.
        foreach (preg_split('/\R/', $themes) as $line) {
            if (preg_match('/(?<![:\w-])bg-\[var\(--cm-accent(?:-bright)?\)\](?!\/)/', $line) && preg_match('/(?<![:\w-])text-(?:white(?!\/)|\[var\(--cm-(?:primary|night)\)\])/', $line) && ! str_contains($line, 'selection:')) {
                $this->fail('An accent-filled style has a fixed text colour: '.trim($line));
            }
        }
        $layouts = substr($themes, strpos($themes, 'const LAYOUT_TABLE'), strpos($themes, '// Optional typography') - strpos($themes, 'const LAYOUT_TABLE'));

        $this->assertDoesNotMatchRegularExpression('/\b(?:blue|indigo|sky|slate)-\d/', $layouts, 'the layout class tables must use the theme colours');
    }
}
