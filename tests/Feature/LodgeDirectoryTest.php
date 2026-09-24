<?php

namespace Tests\Feature;

use App\Models\ClubType;
use App\Models\Lodge;
use App\Models\LodgeSchedule;
use App\Models\MasonicHall;
use App\Models\Province;
use App\Support\ReservedClubSlugs;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LodgeDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private Province $durham;

    private MasonicHall $hall;

    private Lodge $industry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->durham = Province::create(['name' => 'Province of Durham', 'code' => 'durham']);
        $this->hall = MasonicHall::factory()->create(['province_id' => $this->durham->id, 'name' => 'Gateshead Masonic Hall', 'town' => 'Gateshead', 'postcode' => 'NE8 1RB']);

        $this->industry = Lodge::factory()->create([
            'name' => 'Lodge of Industry',
            'number' => '48',
            'slug' => 'lodge-of-industry-48',
            'province_id' => $this->durham->id,
            'masonic_hall_id' => $this->hall->id,
            'meets_text' => 'Fourth Monday except June, July, August and December.',
        ]);
        LodgeSchedule::factory()->create(['lodge_id' => $this->industry->id, 'occurrence' => '4th', 'day_of_week' => 'Monday', 'months' => [1, 2, 3, 4, 5, 9, 10, 11]]);
    }

    public function test_the_directory_is_public_and_lists_lodges(): void
    {
        $this->get(route('lodges.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lodges/Index')
                ->where('total', 1)
                ->has('lodges.data', 1)
                ->where('lodges.data.0.name', 'Lodge of Industry')
                ->where('lodges.data.0.hall.name', 'Gateshead Masonic Hall')
                ->has('lodges.data.0.next', 2));
    }

    public function test_it_searches_by_name_number_hall_town_and_postcode(): void
    {
        Lodge::factory()->create(['name' => 'Other Lodge', 'number' => '999', 'slug' => 'other-lodge-999']);

        foreach (['Industry', '48', 'Gateshead', 'NE8'] as $term) {
            $this->get(route('lodges.index', ['q' => $term]))
                ->assertInertia(fn ($page) => $page->has('lodges.data', 1)->where('lodges.data.0.slug', 'lodge-of-industry-48'));
        }

        $this->get(route('lodges.index', ['q' => 'nothing like this']))
            ->assertInertia(fn ($page) => $page->has('lodges.data', 0));
    }

    public function test_it_filters_by_province_order_and_day(): void
    {
        $other = Lodge::factory()->create(['slug' => 'elsewhere']);
        LodgeSchedule::factory()->create(['lodge_id' => $other->id, 'day_of_week' => 'Friday']);

        $this->get(route('lodges.index', ['province' => 'durham']))->assertInertia(fn ($page) => $page->has('lodges.data', 1));
        $this->get(route('lodges.index', ['day' => 'Friday']))->assertInertia(fn ($page) => $page->has('lodges.data', 1)->where('lodges.data.0.slug', 'elsewhere'));
        $this->get(route('lodges.index', ['day' => 'Monday']))->assertInertia(fn ($page) => $page->has('lodges.data', 1)->where('lodges.data.0.slug', 'lodge-of-industry-48'));
        $this->get(route('lodges.index', ['order' => 'royal_arch']))->assertInertia(fn ($page) => $page->has('lodges.data', 0));
        $this->get(route('lodges.index', ['day' => 'Someday']))->assertSessionHasErrors('day');
    }

    public function test_it_finds_lodges_meeting_in_the_next_week(): void
    {
        CarbonImmutable::setTestNow('2026-09-24');

        $soon = Lodge::factory()->create(['slug' => 'meets-soon']);
        LodgeSchedule::factory()->create(['lodge_id' => $soon->id, 'occurrence' => '4th', 'day_of_week' => 'Monday', 'months' => [9]]);
        $later = Lodge::factory()->create(['slug' => 'meets-later']);
        LodgeSchedule::factory()->create(['lodge_id' => $later->id, 'occurrence' => '1st', 'day_of_week' => 'Tuesday', 'months' => [11]]);

        // The 4th Monday of September 2026 is the 28th, four days away; the November lodge is not.
        $this->get(route('lodges.index', ['when' => 'week']))
            ->assertInertia(fn ($page) => $page
                ->has('lodges.data', 2)
                ->where('lodges.data', fn ($lodges) => collect($lodges)->pluck('slug')->sort()->values()->all() === ['lodge-of-industry-48', 'meets-soon']));

        CarbonImmutable::setTestNow();
    }

    public function test_lodges_that_are_not_active_are_not_listed(): void
    {
        Lodge::factory()->create(['slug' => 'gone', 'status' => 'erased']);

        $this->get(route('lodges.index'))->assertInertia(fn ($page) => $page->where('total', 1));
        $this->get(route('lodges.show', 'gone'))->assertNotFound();
    }

    public function test_a_lodge_page_shows_where_and_when_it_meets(): void
    {
        $sibling = Lodge::factory()->create(['name' => 'Chapter of Industry', 'slug' => 'chapter', 'masonic_hall_id' => $this->hall->id]);

        $this->get(route('lodges.show', 'lodge-of-industry-48'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lodges/Show')
                ->where('lodge.name', 'Lodge of Industry')
                ->where('lodge.meets_text', 'Fourth Monday except June, July, August and December.')
                ->where('lodge.schedules.0.day_of_week', 'Monday')
                ->has('lodge.upcoming', 8)
                ->where('hall.name', 'Gateshead Masonic Hall')
                ->where('hall.address', fn ($address) => str_contains($address, 'NE8 1RB'))
                ->has('sharing', 1)
                ->where('sharing.0.slug', $sibling->slug));
    }

    public function test_upcoming_dates_follow_the_pattern(): void
    {
        CarbonImmutable::setTestNow('2026-10-01');

        $dates = collect($this->industry->schedules->first()->nextDates(CarbonImmutable::today(), 3))->map->toDateString()->all();

        $this->assertSame(['2026-10-26', '2026-11-23', '2027-01-25'], $dates);

        CarbonImmutable::setTestNow();
    }

    public function test_a_hall_page_lists_the_bodies_that_meet_there(): void
    {
        $this->get(route('lodges.hall', $this->hall->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Lodges/Hall')->where('hall.name', 'Gateshead Masonic Hall')->has('lodges', 1));

        $this->get(route('lodges.hall', 'no-such-hall'))->assertNotFound();
    }

    public function test_an_unknown_lodge_is_a_404(): void
    {
        $this->get(route('lodges.show', 'no-such-lodge'))->assertNotFound();
    }

    public function test_the_directory_paths_cannot_be_taken_as_club_slugs(): void
    {
        $this->assertTrue(ReservedClubSlugs::isReserved('lodges'));
        $this->assertTrue(ReservedClubSlugs::isReserved('halls'));
    }

    public function test_display_name_adds_the_order_when_the_source_left_it_out(): void
    {
        $arch = ClubType::create(['name' => 'Royal Arch Chapter', 'code' => 'royal_arch', 'available_modules' => [], 'default_settings' => []]);

        $this->assertSame('Cowpen Lodge', Lodge::factory()->create(['name' => 'Cowpen', 'slug' => 'c1'])->load('clubType')->displayName());
        $this->assertSame('Cowpen Chapter', Lodge::factory()->create(['name' => 'Cowpen', 'slug' => 'c2', 'club_type_id' => $arch->id])->load('clubType')->displayName());
        $this->assertSame('Lodge of Industry', $this->industry->load('clubType')->displayName());
        $this->assertSame('cowpen-lodge-4824', Lodge::slugFor('Cowpen', '4824', 'craft_lodge'));
        $this->assertSame('cowpen-chapter-4824', Lodge::slugFor('Cowpen', '4824', 'royal_arch'));
    }

    public function test_the_installation_meeting_is_marked_in_the_upcoming_dates(): void
    {
        CarbonImmutable::setTestNow('2026-09-24');
        $this->industry->update(['installation_month' => 10]);

        $this->get(route('lodges.show', 'lodge-of-industry-48'))
            ->assertInertia(fn ($page) => $page
                ->where('lodge.installation_month', 'October')
                ->where('lodge.upcoming', function ($upcoming) {
                    $marked = collect($upcoming)->where('installation', true);

                    return $marked->count() >= 1 && $marked->every(fn ($meeting) => str_starts_with($meeting['date'], '2026-10') || str_starts_with($meeting['date'], '2027-10'));
                }));

        $this->get(route('lodges.index'))
            ->assertInertia(fn ($page) => $page->where('lodges.data.0.next.0.installation', false));

        CarbonImmutable::setTestNow();
    }
}
