<?php

namespace Tests\Feature;

use App\Models\Lodge;
use App\Models\LodgeSource;
use App\Models\MasonicHall;
use App\Models\Province;
use App\Support\LodgeReferenceLinks;
use Database\Seeders\ClubTypeSeeder;
use Database\Seeders\LodgeSeeder;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LodgeSourcesTest extends TestCase
{
    use RefreshDatabase;

    private function lodgeWithSources(string $slug, string $url, string $kind = LodgeSource::PROVINCE_PAGE, ?Province $province = null): Lodge
    {
        $lodge = Lodge::factory()->create(['slug' => $slug, 'province_id' => $province?->id, 'source_url' => $url]);
        $lodge->sources()->create(['kind' => $kind, 'url' => $url]);

        return $lodge;
    }

    public function test_every_hall_that_ugle_lists_has_its_ugle_page(): void
    {
        $this->seed([ClubTypeSeeder::class, ProvinceSeeder::class]);

        $this->assertGreaterThan(900, MasonicHall::whereNotNull('ugle_url')->count());
        $this->assertSame(0, MasonicHall::whereNotNull('ugle_url')->where('ugle_url', 'not like', 'https://www.ugle.org.uk/%')->count());
    }

    public function test_seeding_records_a_province_link_and_a_ugle_link_for_each_lodge(): void
    {
        $this->seed([ClubTypeSeeder::class, ProvinceSeeder::class, LodgeSeeder::class]);

        $lodge = Lodge::whereHas('sources', fn ($sources) => $sources->where('kind', LodgeSource::UGLE_HALL))
            ->whereHas('sources', fn ($sources) => $sources->whereIn('kind', LodgeSource::PROVINCE_KINDS))->with('sources')->firstOrFail();
        $this->assertSame($lodge->source_url, $lodge->sources->whereIn('kind', [LodgeSource::PROVINCE_PAGE, LodgeSource::PROVINCE_LIST])->sole()->url);
        $this->assertSame($lodge->masonicHall->ugle_url, $lodge->sources->firstWhere('kind', LodgeSource::UGLE_HALL)->url);

        $count = LodgeSource::count();
        $this->seed(LodgeSeeder::class);
        $this->assertSame($count, LodgeSource::count());
    }

    public function test_a_page_shared_by_several_lodges_is_a_list_page(): void
    {
        $this->seed([ClubTypeSeeder::class, ProvinceSeeder::class, LodgeSeeder::class]);

        $shared = LodgeSource::where('kind', LodgeSource::PROVINCE_LIST)->firstOrFail();
        $this->assertGreaterThan(1, LodgeSource::where('url', $shared->url)->count());
        $this->assertSame(0, LodgeSource::where('kind', LodgeSource::PROVINCE_PAGE)->groupBy('url')->havingRaw('count(*) > 1')->get()->count());
    }

    public function test_a_lodge_listed_only_by_the_directory_gets_only_a_directory_source(): void
    {
        $this->seed([ClubTypeSeeder::class, ProvinceSeeder::class, LodgeSeeder::class]);

        $lodge = Lodge::where('source_url', 'like', 'https://onthesquare.co/%')->with('sources')->firstOrFail();
        $this->assertSame([LodgeSource::DIRECTORY_PAGE], $lodge->sources->whereIn('kind', [...LodgeSource::PROVINCE_KINDS, LodgeSource::DIRECTORY_PAGE])->pluck('kind')->all());
    }

    public function test_a_province_lodge_can_also_carry_its_directory_page(): void
    {
        $this->seed([ClubTypeSeeder::class, ProvinceSeeder::class, LodgeSeeder::class]);

        $lodge = Lodge::whereHas('sources', fn ($q) => $q->where('kind', LodgeSource::DIRECTORY_PAGE))
            ->whereHas('sources', fn ($q) => $q->whereIn('kind', LodgeSource::PROVINCE_KINDS))->with('sources')->firstOrFail();

        $this->assertStringContainsString('onthesquare.co', $lodge->sources->firstWhere('kind', LodgeSource::DIRECTORY_PAGE)->url);
        $this->assertNotSame($lodge->sources->firstWhere('kind', LodgeSource::DIRECTORY_PAGE)->url, $lodge->source_url);
    }

    public function test_reference_links_are_grouped_ugle_then_province_then_directories(): void
    {
        $hall = MasonicHall::factory()->create(['postcode' => 'DE55 7AQ', 'ugle_url' => 'https://www.ugle.org.uk/x/hall']);
        $lodge = $this->lodgeWithSources('a', 'https://province.example/a');
        $lodge->sources()->create(['kind' => LodgeSource::DIRECTORY_PAGE, 'url' => 'https://onthesquare.co/lodge/a/']);
        $lodge->update(['masonic_hall_id' => $hall->id]);

        $groups = LodgeReferenceLinks::forLodge($lodge->fresh(['sources', 'masonicHall']));

        $this->assertSame([1, 2, 3], array_column($groups, 'tier'));
        $this->assertSame(['https://www.ugle.org.uk/x/hall'], [$groups[0]['links'][0]['url']]);
        $this->assertTrue($groups[0]['links'][1]['is_search']);
        $this->assertStringContainsString('origin_address%5D=DE55%207AQ', $groups[0]['links'][1]['url']);
        $this->assertSame('https://province.example/a', $groups[1]['links'][0]['url']);
        $this->assertSame('https://onthesquare.co/lodge/a/', $groups[2]['links'][0]['url']);
    }

    public function test_the_lodge_and_hall_pages_show_the_reference_links(): void
    {
        $hall = MasonicHall::factory()->create(['postcode' => 'NE8 1RB', 'ugle_url' => 'https://www.ugle.org.uk/x/hall']);
        $lodge = $this->lodgeWithSources('industry', 'https://province.example/industry');
        $lodge->update(['masonic_hall_id' => $hall->id]);

        $this->get(route('lodges.show', 'industry'))->assertInertia(fn ($page) => $page->has('lodge.references', 2)->where('lodge.references.0.tier', 1)->has('lodge.references.0.links', 2));
        $this->get(route('lodges.hall', $hall->slug))->assertInertia(fn ($page) => $page->has('hall.references', 1)->has('hall.references.0.links', 2));
    }

    public function test_the_check_flags_changed_pages_once_per_distinct_url(): void
    {
        $one = $this->lodgeWithSources('one', 'https://province.example/list', LodgeSource::PROVINCE_LIST);
        $two = $this->lodgeWithSources('two', 'https://province.example/list', LodgeSource::PROVINCE_LIST);
        Http::fake(['province.example/*' => Http::sequence()
            ->push('<html><script>var a=1</script><p>Meets 1st Monday</p></html>')
            ->push('<html><script>var a=2</script><p>Meets  1st   Monday</p></html>')
            ->push('<html><p>Meets 2nd Tuesday</p></html>')
            ->push('gone', 500)]);

        $this->artisan('lodges:check-sources', ['--delay' => 0])->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertSame(['ok', 'ok'], LodgeSource::orderBy('id')->pluck('last_status')->all());

        $this->artisan('lodges:check-sources', ['--delay' => 0])->assertSuccessful();
        $this->assertSame(['unchanged', 'unchanged'], LodgeSource::orderBy('id')->pluck('last_status')->all());
        $this->assertNull($one->sources()->first()->changed_at);

        $this->artisan('lodges:check-sources', ['--delay' => 0])->assertSuccessful();
        $this->assertSame('changed', $two->sources()->first()->last_status);
        $this->assertNotNull($two->sources()->first()->changed_at);

        $this->artisan('lodges:check-sources', ['--delay' => 0])->assertSuccessful();
        $failed = $one->sources()->first();
        $this->assertSame('failed', $failed->last_status);
        $this->assertSame(500, $failed->last_http_status);
        $this->assertNotNull($failed->content_hash);
    }

    public function test_the_check_can_be_limited_and_can_be_a_dry_run(): void
    {
        $durham = Province::create(['name' => 'Province of Durham', 'code' => 'durham']);
        $this->lodgeWithSources('in-durham', 'https://province.example/durham', province: $durham);
        $this->lodgeWithSources('elsewhere', 'https://other.example/x');
        Http::fake(['*' => Http::response('<p>ok</p>')]);

        $this->artisan('lodges:check-sources', ['--dry-run' => true])->assertSuccessful();
        Http::assertNothingSent();
        $this->assertNull(LodgeSource::first()->last_checked_at);

        $this->artisan('lodges:check-sources', ['--province' => 'durham', '--delay' => 0])->assertSuccessful();
        Http::assertSentCount(1);
        $this->assertNotNull(LodgeSource::where('url', 'https://province.example/durham')->first()->last_checked_at);
        $this->assertNull(LodgeSource::where('url', 'https://other.example/x')->first()->last_checked_at);
    }
}
