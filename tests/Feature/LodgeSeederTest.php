<?php

namespace Tests\Feature;

use App\Models\ClubType;
use App\Models\Lodge;
use App\Models\LodgeSchedule;
use App\Models\MasonicHall;
use App\Models\Province;
use Carbon\CarbonImmutable;
use Database\Seeders\ClubTypeSeeder;
use Database\Seeders\LodgeSeeder;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LodgeSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedAll(): void
    {
        $this->seed([ClubTypeSeeder::class, ProvinceSeeder::class, LodgeSeeder::class]);
    }

    public function test_the_data_files_are_complete_and_unique(): void
    {
        $this->seed([ClubTypeSeeder::class, ProvinceSeeder::class]);

        $rows = (new LodgeSeeder)->rows();
        $orders = ClubType::pluck('code')->all();
        $provinces = Province::pluck('code')->all();
        $hallPostcodes = MasonicHall::whereNotNull('postcode')->pluck('postcode')->map(fn ($p) => LodgeSeeder::normalisePostcode($p))->all();

        $this->assertGreaterThan(300, count($rows));
        $this->assertSame(count($rows), count(array_unique(array_column($rows, 'slug'))), 'Duplicate lodge slugs in the data files.');

        foreach ($rows as $row) {
            $this->assertNotEmpty($row['name'], 'A lodge has no name.');
            $this->assertContains($row['order'], $orders, "{$row['name']} has an unknown order.");
            $this->assertContains($row['province_code'], $provinces, "{$row['name']} has an unknown province.");
            $this->assertNotEmpty($row['source_url'], "{$row['name']} has no source.");

            if ($row['hall_postcode']) {
                $this->assertContains(LodgeSeeder::normalisePostcode($row['hall_postcode']), $hallPostcodes, "{$row['name']} meets at a postcode that is not a known hall ({$row['hall_postcode']}).");
            }
        }
    }

    public function test_seeding_builds_lodges_halls_and_patterns_and_is_repeatable(): void
    {
        $this->seedAll();
        $count = Lodge::count();
        $schedules = LodgeSchedule::count();

        $this->seed(LodgeSeeder::class);

        $this->assertSame($count, Lodge::count());
        $this->assertSame($schedules, LodgeSchedule::count());
        $this->assertGreaterThan(250, $schedules);

        $industry = Lodge::with(['masonicHall', 'province', 'schedules'])->where('slug', 'lodge-of-industry-48')->firstOrFail();
        $this->assertSame('Gateshead Masonic Hall', $industry->masonicHall->name);
        $this->assertSame('durham', $industry->province->code);
        $this->assertSame('4th', $industry->schedules->first()->occurrence);
        $this->assertSame([1, 2, 3, 4, 5, 9, 10, 11], $industry->schedules->first()->months);

        CarbonImmutable::setTestNow('2026-09-24');
        $this->assertSame('2026-09-28', $industry->schedules->first()->nextDates(CarbonImmutable::today(), 1)[0]->toDateString());
        CarbonImmutable::setTestNow();
    }

    public function test_stockton_has_its_lodge_and_chapter_at_the_hall(): void
    {
        $this->seedAll();

        $hall = MasonicHall::where('name', 'Stockton-on-Tees Masonic Hall')->firstOrFail();

        $this->assertGreaterThanOrEqual(7, Lodge::where('masonic_hall_id', $hall->id)->count());
        $this->assertTrue(Lodge::where('masonic_hall_id', $hall->id)->where('name', 'like', 'Stockton%')->exists());
    }

    public function test_reseeding_keeps_a_schedule_and_the_claim_a_superadmin_set(): void
    {
        $this->seedAll();
        $lodge = Lodge::where('slug', 'lodge-of-industry-48')->firstOrFail();
        $lodge->schedules()->delete();
        $lodge->schedules()->create(['occurrence' => 'last', 'day_of_week' => 'Friday', 'months' => [5], 'source' => 'manual']);
        $lodge->update(['website_url' => 'https://example.org/industry']);

        $this->seed(LodgeSeeder::class);

        $lodge->refresh();
        $this->assertSame(1, $lodge->schedules()->count());
        $this->assertSame('manual', $lodge->schedules()->first()->source);
        $this->assertSame('https://example.org/industry', $lodge->website_url);
    }

    public function test_installation_months_come_from_the_tables_and_the_wording(): void
    {
        $this->seedAll();

        $cowpen = Lodge::where('slug', 'cowpen-lodge-4824')->with('schedules')->firstOrFail();
        $this->assertSame(11, $cowpen->installation_month);
        $this->assertContains(11, $cowpen->schedules->first()->months);

        $phoenix = Lodge::where('slug', 'phoenix-lodge-94')->firstOrFail();
        $this->assertSame(12, $phoenix->installation_month);

        // Every lodge with one pattern and an installation month meets in that month. (A lodge with several
        // patterns is left as its source words it, because there is no telling which weekday the installation is on.)
        Lodge::whereNotNull('installation_month')->with('schedules')->get()
            ->filter(fn (Lodge $lodge) => $lodge->schedules->count() === 1)
            ->each(fn (Lodge $lodge) => $this->assertContains($lodge->installation_month, $lodge->schedules->first()->months, $lodge->name));
    }

    public function test_side_orders_load_with_their_own_types_and_never_share_a_slug_with_a_craft_lodge(): void
    {
        $this->seedAll();

        foreach (['mark_lodge', 'royal_ark_mariner', 'knights_templar', 'rose_croix'] as $code) {
            $this->assertGreaterThan(50, Lodge::whereHas('clubType', fn ($type) => $type->where('code', $code))->count(), $code);
        }

        foreach (['cryptic_council', 'secret_monitor', 'allied_masonic', 'red_cross_constantine'] as $code) {
            $this->assertGreaterThan(10, Lodge::whereHas('clubType', fn ($type) => $type->where('code', $code))->count(), $code);
        }

        $this->assertSame('invicta-council-54-amd', Lodge::slugFor('Invicta', '54', 'allied_masonic'));
        $this->assertSame('invicta-council-54-rsm', Lodge::slugFor('Invicta', '54', 'cryptic_council'));
        $this->assertSame('porchester-lodge-27-ram', Lodge::slugFor('Porchester', '27', 'royal_ark_mariner'));
        $this->assertSame('porchester-lodge-27-mark', Lodge::slugFor('Porchester', '27', 'mark_lodge'));
        $this->assertSame('porchester-lodge-27', Lodge::slugFor('Porchester', '27', 'craft_lodge'));
    }
}
