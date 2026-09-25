<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\MasonicHall;
use App\Models\Page;
use App\Models\User;
use App\Support\BlockNormaliser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContentBlocksTest extends TestCase
{
    use RefreshDatabase;

    private function club(string $slug = 'lodge-of-fraternity'): Club
    {
        $clubType = ClubType::firstOrCreate(['code' => 'lodge'], ['name' => 'Lodge']);

        return Club::create(['name' => 'Lodge of Fraternity', 'slug' => $slug, 'club_type_id' => $clubType->id, 'email' => 'club@example.org']);
    }

    private function admin(Club $club, string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->clubs()->attach($club->id, ['role' => $role]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    private function saved(Club $club, array $block): array
    {
        return Page::create(['club_id' => $club->id, 'title' => 'Blocks', 'slug' => 'blocks-'.uniqid(), 'blocks' => [$block]])->fresh()->blocks[0];
    }

    public function test_a_cta_banner_is_cleaned_when_saved(): void
    {
        $block = $this->saved($this->club(), [
            'type' => 'cta_banner',
            'eyebrow' => '<i>Join</i> us',
            'heading' => 'Come along',
            'style' => 'neon',
            'overlay' => 'blinding',
            'align' => 'diagonal',
            'size' => 'huge',
            'button_label' => 'Go',
            'button_url' => 'javascript:alert(1)',
            'button2_label' => 'More',
            'button2_url' => 'https://example.org/more',
            'image_url' => 'javascript:alert(1)',
            'button_new_tab' => 'true',
        ]);

        $this->assertSame('Join us', $block['eyebrow']);
        $this->assertSame(['bold', 'medium', 'center', 'normal'], [$block['style'], $block['overlay'], $block['align'], $block['size']]);
        $this->assertSame('', $block['button_url']);
        $this->assertSame('', $block['image_url']);
        $this->assertSame('https://example.org/more', $block['button2_url']);
        $this->assertTrue($block['button_new_tab']);
        $this->assertFalse($block['button2_new_tab']);
    }

    public function test_a_faq_keeps_safe_rich_text_answers_and_caps_the_items(): void
    {
        $items = [['id' => 'a', 'question' => '<b>Who?</b>', 'answer' => '<p>Ask <a href="javascript:alert(1)" onclick="x()">us</a><script>alert(1)</script></p>']];
        $items[] = ['question' => '', 'answer' => ''];
        $items[] = 'not an item';
        for ($i = 0; $i < 70; $i++) {
            $items[] = ['question' => "Q$i", 'answer' => '<p>A</p>'];
        }

        $block = $this->saved($this->club(), ['type' => 'faq', 'behaviour' => 'sideways', 'columns' => '2', 'items' => $items]);

        $this->assertSame('single', $block['behaviour']);
        $this->assertSame(2, $block['columns']);
        $this->assertCount(60, $block['items']);
        $this->assertSame('Who?', $block['items'][0]['question']);
        $this->assertStringNotContainsString('<script', $block['items'][0]['answer']);
        $this->assertStringNotContainsString('onclick', $block['items'][0]['answer']);
        $this->assertStringNotContainsString('javascript:', $block['items'][0]['answer']);
        $this->assertStringContainsString('Ask', $block['items'][0]['answer']);
    }

    public function test_a_map_keeps_only_valid_coordinates(): void
    {
        $club = $this->club();

        $good = $this->saved($club, ['type' => 'map', 'lat' => '51.5155', 'lng' => -0.1201, 'zoom' => '17', 'notes' => '<b>Park</b> behind', 'layout' => 'floating']);
        $this->assertSame(51.5155, $good['lat']);
        $this->assertSame(-0.1201, $good['lng']);
        $this->assertSame(17, $good['zoom']);
        $this->assertSame('Park behind', $good['notes']);
        $this->assertSame('stacked', $good['layout']);

        $satellite = $this->saved($club, ['type' => 'map', 'lat' => 1, 'lng' => 1, 'zoom' => 19, 'map_style' => 'satellite']);
        $this->assertSame('satellite', $satellite['map_style']);
        $this->assertSame(18, $satellite['zoom'], 'satellite imagery stops at zoom 18');
        $this->assertTrue($satellite['allow_style_switch']);

        $unknown = $this->saved($club, ['type' => 'map', 'lat' => 1, 'lng' => 1, 'map_style' => 'terrain', 'allow_style_switch' => false]);
        $this->assertSame('street', $unknown['map_style']);
        $this->assertFalse($unknown['allow_style_switch']);

        $bad = $this->saved($club, ['type' => 'map', 'lat' => 123, 'lng' => 'east', 'zoom' => 99]);
        $this->assertNull($bad['lat']);
        $this->assertNull($bad['lng']);
        $this->assertSame(16, $bad['zoom'], 'an out-of-range zoom falls back to the default');
    }

    public function test_any_block_can_be_narrowed_and_positioned(): void
    {
        $club = $this->club();

        $half = $this->saved($club, ['type' => 'text', 'content' => '<p>Hi</p>', 'block_width' => 'half', 'block_align' => 'right']);
        $this->assertSame(['half', 'right'], [$half['block_width'], $half['block_align']]);

        $bad = $this->saved($club, ['type' => 'notice', 'text' => 'x', 'block_width' => 'huge', 'block_align' => 'sideways']);
        $this->assertSame(['full', 'center'], [$bad['block_width'], $bad['block_align']]);

        $legacy = $this->saved($club, ['type' => 'notice', 'text' => 'x']);
        $this->assertArrayNotHasKey('block_width', $legacy);
    }

    public function test_a_calendar_block_falls_back_to_its_defaults(): void
    {
        $block = $this->saved($this->club(), ['type' => 'calendar', 'default_view' => 'year', 'week_starts' => 'friday', 'list_length' => '7', 'show_price' => 'yes']);

        $this->assertSame('month', $block['default_view']);
        $this->assertSame('monday', $block['week_starts']);
        $this->assertSame(10, $block['list_length']);
        $this->assertTrue($block['show_price']);
        $this->assertTrue($block['show_subscribe'], 'the subscribe link defaults on');
    }

    public function test_a_downloads_block_never_stores_a_storage_url_for_an_upload(): void
    {
        $block = $this->saved($this->club(), [
            'type' => 'downloads',
            'open_in' => 'sideways',
            'items' => [
                ['id' => 'u', 'source' => 'upload', 'media_id' => '12', 'url' => 'https://example.org/storage/12/x.pdf', 'title' => '<b>Minutes</b>', 'size' => '2048', 'added_at' => '2026-09-01T10:00:00Z'],
                ['id' => 'l', 'source' => 'link', 'url' => 'https://example.org/form.pdf', 'title' => 'Form'],
                ['source' => 'link', 'url' => 'javascript:alert(1)', 'title' => 'Bad link'],
                ['source' => 'upload', 'media_id' => 0, 'title' => 'No file'],
            ],
        ]);

        $this->assertSame('new_tab', $block['open_in']);
        $this->assertSame('Download', $block['button_label']);
        $this->assertFalse($block['members_only']);
        $this->assertCount(2, $block['items'], 'a link with a bad address and an upload with no file are dropped');

        [$upload, $link] = $block['items'];
        $this->assertSame(12, $upload['media_id']);
        $this->assertSame('', $upload['url']);
        $this->assertSame('Minutes', $upload['title']);
        $this->assertSame(2048, $upload['size']);
        $this->assertSame('2026-09-01', $upload['added_at']);
        $this->assertNull($link['media_id']);
        $this->assertSame('https://example.org/form.pdf', $link['url']);
    }

    public function test_the_map_search_finds_an_address_once_and_then_remembers_it(): void
    {
        config(['services.nominatim.gap_ms' => 0]);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '51.5155', 'lon' => '-0.1201', 'display_name' => 'Freemasons\' Hall, London'],
            ['lat' => '51.5', 'lon' => '-0.12', 'display_name' => 'Great Queen Street, London'],
        ])]);

        $club = $this->club();
        $admin = $this->admin($club);
        $url = route('admin.pages.geocode', ['clubSlug' => $club->slug]);

        $this->actingAs($admin)->postJson($url, ['q' => 'Great Queen Street, London'])
            ->assertOk()
            ->assertJson(['found' => true, 'approximate' => false, 'results' => [['lat' => 51.5155, 'lng' => -0.1201], ['lat' => 51.5, 'lng' => -0.12]]]);

        $this->actingAs($admin)->postJson($url, ['q' => '  great queen   street, london '])->assertOk()->assertJson(['found' => true]);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->header('User-Agent')[0], 'ClubManager') && $request['limit'] === 5);
    }

    public function test_a_uk_postcode_is_searched_first_because_it_pins_the_building(): void
    {
        config(['services.nominatim.gap_ms' => 0]);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '54.56616', 'lon' => '-1.31653', 'display_name' => 'TS18 1RD, Stockton-on-Tees']])]);

        $club = $this->club();

        $this->actingAs($this->admin($club))
            ->postJson(route('admin.pages.geocode', ['clubSlug' => $club->slug]), ['q' => 'Stockton Masonic Hall, Wellington St, Stockton-on-Tees TS18 1RD'])
            ->assertOk()
            ->assertJson(['found' => true, 'approximate' => true, 'matched' => 'TS18 1RD', 'results' => [['lat' => 54.56616, 'lng' => -1.31653]]]);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['q'] === 'TS18 1RD');
    }

    public function test_a_search_without_a_postcode_falls_back_to_the_address_without_the_building_name(): void
    {
        config(['services.nominatim.gap_ms' => 0]);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::sequence()
            ->push([])
            ->push([['lat' => '54.5663', 'lon' => '-1.3174', 'display_name' => 'Wellington Street, Stockton-on-Tees']])]);

        $club = $this->club();

        $this->actingAs($this->admin($club))
            ->postJson(route('admin.pages.geocode', ['clubSlug' => $club->slug]), ['q' => 'Masonic Hall, Wellington Street, Stockton-on-Tees'])
            ->assertOk()
            ->assertJson(['found' => true, 'approximate' => true, 'matched' => 'Wellington Street, Stockton-on-Tees']);

        Http::assertSentCount(2);
    }

    public function test_the_map_search_handles_no_match_and_an_unreachable_service(): void
    {
        config(['services.nominatim.gap_ms' => 0]);
        $club = $this->club();
        $admin = $this->admin($club);
        $url = route('admin.pages.geocode', ['clubSlug' => $club->slug]);

        Http::fake(['nominatim.openstreetmap.org/*' => Http::sequence()->push([])->push('down', 500)]);
        $this->actingAs($admin)->postJson($url, ['q' => 'Nowhereville'])->assertOk()->assertJson(['found' => false, 'results' => []]);

        $this->actingAs($admin)->postJson($url, ['q' => 'Somewhere else'])->assertStatus(503)->assertJson(['found' => false]);

        $this->actingAs($admin)->postJson($url, [])->assertUnprocessable();
    }

    public function test_hall_suggestions_come_from_the_directory_without_any_outside_lookup(): void
    {
        Http::fake();
        $club = $this->club();
        $admin = $this->admin($club);
        MasonicHall::create(['name' => 'Stockton-on-Tees Masonic Hall', 'slug' => 'stockton', 'kind' => 'masonic_hall', 'address_line_1' => 'Wellington Street', 'town' => 'Stockton-on-Tees', 'postcode' => 'TS18 1RD']);
        MasonicHall::create(['name' => 'Durham Masonic Hall', 'slug' => 'durham', 'kind' => 'masonic_hall', 'town' => 'Durham', 'postcode' => 'DH1 3RR']);
        $url = route('admin.pages.places', ['clubSlug' => $club->slug]);

        $this->actingAs($admin)->getJson($url.'?q=stockton')->assertOk()
            ->assertJsonCount(1, 'places')
            ->assertJsonPath('places.0.name', 'Stockton-on-Tees Masonic Hall')
            ->assertJsonPath('places.0.address', 'Wellington Street, Stockton-on-Tees, TS18 1RD');

        $this->actingAs($admin)->getJson($url.'?q=TS18')->assertJsonCount(1, 'places');
        $this->actingAs($admin)->getJson($url.'?q=%25')->assertJsonCount(0, 'places');
        $this->actingAs($admin)->getJson($url.'?q=st')->assertJsonCount(0, 'places');

        $this->actingAs($this->admin($club, 'member'))->getJson($url.'?q=stockton')->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_only_website_editors_can_use_the_map_search(): void
    {
        Http::fake();
        $club = $this->club();
        $url = route('admin.pages.geocode', ['clubSlug' => $club->slug]);

        $this->actingAs($this->admin($club, 'member'))->postJson($url, ['q' => 'London'])->assertForbidden();
        $this->actingAs($this->admin($this->club('other-club'), 'admin'))->postJson($url, ['q' => 'London'])->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_a_banner_button_link_written_without_https_is_completed(): void
    {
        $type = ClubType::create(['name' => 'Lodge', 'code' => 'lodge-links']);
        $club = Club::create(['name' => 'Link Lodge', 'slug' => 'link-lodge', 'club_type_id' => $type->id, 'email' => 'links@example.org']);

        $page = Page::create(['club_id' => $club->id, 'title' => 'Links', 'slug' => 'links', 'is_published' => true, 'blocks' => [[
            'id' => 'b1', 'type' => 'cta_banner', 'heading' => 'Join', 'button_label' => 'Visit',
            'button_url' => 'www.ugle.org.uk/about', 'button2_url' => '/site/link-lodge/contact',
        ], [
            'id' => 'b2', 'type' => 'cta_banner', 'heading' => 'More', 'button_label' => 'Mail',
            'button_url' => 'mailto:sec@example.org', 'button2_url' => 'javascript:alert(1)',
        ], [
            'id' => 'b3', 'type' => 'cta_banner', 'heading' => 'Bare', 'button_url' => 'example.com', 'button2_url' => '',
        ]]]);

        $blocks = $page->fresh()->blocks;

        $this->assertSame('https://www.ugle.org.uk/about', $blocks[0]['button_url']);
        $this->assertSame('/site/link-lodge/contact', $blocks[0]['button2_url']);
        $this->assertSame('mailto:sec@example.org', $blocks[1]['button_url']);
        $this->assertSame('', $blocks[1]['button2_url']);
        $this->assertSame('https://example.com', $blocks[2]['button_url']);
        $this->assertSame('', $blocks[2]['button2_url']);
    }

    public function test_the_button_link_and_hero_button_links_are_completed_too(): void
    {
        $type = ClubType::create(['name' => 'Lodge', 'code' => 'lodge-links2']);
        $club = Club::create(['name' => 'Button Lodge', 'slug' => 'button-lodge', 'club_type_id' => $type->id, 'email' => 'b@example.org']);

        $page = Page::create(['club_id' => $club->id, 'title' => 'Buttons', 'slug' => 'buttons', 'is_published' => true, 'blocks' => [
            ['id' => 'a', 'type' => 'button', 'label' => 'Go', 'url' => 'www.ugle.org.uk'],
            ['id' => 'b', 'type' => 'button', 'label' => 'Page', 'url' => '/site/button-lodge/contact'],
            ['id' => 'c', 'type' => 'hero', 'title' => 'Hi', 'cta_link' => 'example.org/join'],
            ['id' => 'd', 'type' => 'image', 'url' => 'photo.jpg'],
        ]]);

        $blocks = $page->fresh()->blocks;

        $this->assertSame('https://www.ugle.org.uk', $blocks[0]['url']);
        $this->assertSame('/site/button-lodge/contact', $blocks[1]['url']);
        $this->assertSame('https://example.org/join', $blocks[2]['cta_link']);
        // Only link buttons are completed: an image's address is left as it was.
        $this->assertSame('photo.jpg', $blocks[3]['url']);
    }

    public function test_link_completion_leaves_other_schemes_alone_and_keeps_ports(): void
    {
        $this->assertSame('https://example.org:8080/x', BlockNormaliser::link('example.org:8080/x'));
        $this->assertSame('javascript:alert(1)', BlockNormaliser::link('javascript:alert(1)'));
        $this->assertSame('', BlockNormaliser::link('   '));
        $this->assertSame('#top', BlockNormaliser::link('#top'));
        $this->assertSame('tel:+441234', BlockNormaliser::link('tel:+441234'));
    }
}
