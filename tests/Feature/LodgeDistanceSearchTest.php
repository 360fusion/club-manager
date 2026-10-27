<?php

namespace Tests\Feature;

use App\Models\Lodge;
use App\Models\MasonicHall;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LodgeDistanceSearchTest extends TestCase
{
    use RefreshDatabase;

    /** Durham city centre: where the faked place search says the visitor typed. */
    private const DURHAM = ['lat' => '54.7761', 'lon' => '-1.5733', 'display_name' => 'Durham, County Durham, England'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.nominatim.gap_ms' => 0]);

        $this->lodgeAt('Durham Lodge', ['latitude' => 54.7753, 'longitude' => -1.5849]);      // under a mile away
        $this->lodgeAt('Sunderland Lodge', ['latitude' => 54.9069, 'longitude' => -1.3838]);  // about 12 miles
        $this->lodgeAt('London Lodge', ['latitude' => 51.5154, 'longitude' => -0.1205]);      // about 230 miles
    }

    private function lodgeAt(string $name, array $position): Lodge
    {
        $hall = MasonicHall::factory()->create(['name' => $name.' Hall', 'postcode' => 'DH1 1AA', ...$position]);

        return Lodge::factory()->create(['name' => $name, 'masonic_hall_id' => $hall->id]);
    }

    private function fakePlaceSearch(array $results): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response($results)]);
    }

    public function test_lodges_near_a_place_are_listed_nearest_first_with_their_distance(): void
    {
        $this->fakePlaceSearch([self::DURHAM]);

        $this->get(route('lodges.index', ['near' => 'Durham', 'within' => 25]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('near.place', 'Durham, County Durham, England')
                ->has('lodges.data', 2)
                ->where('lodges.data.0.name', 'Durham Lodge')
                ->where('lodges.data.0.miles', 0.5)
                ->where('lodges.data.1.name', 'Sunderland Lodge'));

        Http::assertSent(fn (Request $request) => $request['countrycodes'] === MasonicHall::SEARCH_COUNTRIES);
    }

    public function test_a_smaller_distance_leaves_out_lodges_further_away(): void
    {
        $this->fakePlaceSearch([self::DURHAM]);

        $this->get(route('lodges.index', ['near' => 'Durham', 'within' => 5]))
            ->assertInertia(fn (Assert $page) => $page->has('lodges.data', 1)->where('lodges.data.0.name', 'Durham Lodge'));
    }

    public function test_a_place_that_cannot_be_found_says_so(): void
    {
        $this->fakePlaceSearch([]);

        $this->get(route('lodges.index', ['near' => 'Nowhere at all']))
            ->assertInertia(fn (Assert $page) => $page->where('near.error', "We couldn't find that place. Try a postcode or a town.")->where('near.place', null));
    }

    public function test_a_visitor_can_only_look_places_up_twenty_times_a_minute(): void
    {
        $this->fakePlaceSearch([self::DURHAM]);

        for ($search = 0; $search < 20; $search++) {
            $this->get(route('lodges.index', ['near' => 'Durham']));
        }

        $this->get(route('lodges.index', ['near' => 'Durham']))
            ->assertInertia(fn (Assert $page) => $page->where('near.error', 'Too many place searches. Try again in a minute.'));
    }

    public function test_the_geocode_command_places_halls_and_a_new_postcode_clears_the_position(): void
    {
        $this->fakePlaceSearch([self::DURHAM]);
        $hall = MasonicHall::factory()->create(['postcode' => 'DH1 3AB', 'town' => 'Durham']);

        $this->artisan('halls:geocode')->assertSuccessful();

        $this->assertSame(54.7761, $hall->fresh()->latitude);
        $this->assertSame(-1.5733, $hall->fresh()->longitude);

        $hall->fresh()->update(['postcode' => 'DH1 4XY']);

        $this->assertNull($hall->fresh()->latitude);
    }
}
