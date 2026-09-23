<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\MasonicHall;
use App\Models\Province;
use App\Models\User;
use Database\Seeders\MasonicHallSeeder;
use Database\Seeders\ProvinceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasonicHallTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    private Province $bristol;

    private Province $durham;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create([
            'name' => 'Lodge',
            'code' => 'lodge',
            'available_modules' => ['memberships'],
            'default_settings' => [],
        ]);

        $this->bristol = Province::create(['name' => 'Province of Bristol', 'code' => 'bristol']);
        $this->durham = Province::create(['name' => 'Province of Durham', 'code' => 'durham']);

        $this->club = Club::create([
            'club_type_id' => $type->id,
            'name' => 'Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'status' => 'active',
            'province_id' => $this->bristol->id,
        ]);

        $this->admin = User::factory()->create();
        $this->club->users()->attach($this->admin->id, ['role' => 'admin', 'status' => 'active']);
    }

    private function saveSettings(array $payload)
    {
        return $this->actingAs($this->admin)
            ->put(route('admin.settings.update', ['clubSlug' => $this->club->slug]), $payload);
    }

    public function test_settings_page_lists_the_halls(): void
    {
        MasonicHall::factory()->create(['province_id' => $this->bristol->id, 'name' => 'Park Street Hall']);

        $this->actingAs($this->admin)
            ->get(route('admin.settings.show', ['clubSlug' => $this->club->slug]))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Settings/Show')
                ->has('masonicHalls', 1)
                ->where('masonicHalls.0.name', 'Park Street Hall'));
    }

    public function test_admin_can_choose_a_hall_in_their_province(): void
    {
        $hall = MasonicHall::factory()->create(['province_id' => $this->bristol->id]);

        $this->saveSettings(['province_id' => (string) $this->bristol->id, 'masonic_hall_id' => (string) $hall->id])
            ->assertSessionHasNoErrors();

        $this->club->refresh();
        $this->assertSame($hall->id, $this->club->masonic_hall_id);
        $this->assertArrayNotHasKey('masonic_hall_id', $this->club->settings ?? []);
        $this->assertTrue($hall->clubs->contains($this->club));
    }

    public function test_hall_from_another_province_is_refused(): void
    {
        $hall = MasonicHall::factory()->create(['province_id' => $this->durham->id]);

        $this->saveSettings(['province_id' => $this->bristol->id, 'masonic_hall_id' => $hall->id])
            ->assertSessionHasErrors('masonic_hall_id');

        $this->assertNull($this->club->refresh()->masonic_hall_id);
    }

    public function test_hall_with_no_province_can_be_chosen_and_then_cleared(): void
    {
        $hall = MasonicHall::factory()->create(['province_id' => null]);

        $this->saveSettings(['masonic_hall_id' => $hall->id])->assertSessionHasNoErrors();
        $this->assertSame($hall->id, $this->club->refresh()->masonic_hall_id);

        $this->saveSettings(['masonic_hall_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($this->club->refresh()->masonic_hall_id);
    }

    public function test_unknown_hall_is_refused(): void
    {
        $this->saveSettings(['masonic_hall_id' => 9999])->assertSessionHasErrors('masonic_hall_id');
    }

    public function test_deleting_a_hall_leaves_its_clubs_without_one(): void
    {
        $hall = MasonicHall::factory()->create();
        $this->club->update(['masonic_hall_id' => $hall->id]);

        $hall->delete();

        $this->assertNull($this->club->refresh()->masonic_hall_id);
    }

    public function test_seeder_is_idempotent_and_links_provinces(): void
    {
        Province::create(['name' => 'Metropolitan Grand Lodge of London', 'code' => 'london_metropolitan']);

        $this->seed(MasonicHallSeeder::class);
        $count = MasonicHall::count();
        $this->seed(MasonicHallSeeder::class);

        $this->assertSame($count, MasonicHall::count());
        $this->assertSame(
            Province::where('code', 'london_metropolitan')->value('id'),
            MasonicHall::where('slug', 'freemasons-hall-london')->value('province_id')
        );
    }

    public function test_superadmin_can_add_edit_and_delete_a_hall(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($superAdmin)->post(route('superadmin.masonic_halls.store'), [
            'name' => 'Castle Street Masonic Hall',
            'province_id' => $this->durham->id,
            'address_line_1' => '1 Castle Street',
            'town' => 'Durham',
            'postcode' => 'DH1 1AA',
        ])->assertSessionHasNoErrors();

        $hall = MasonicHall::where('name', 'Castle Street Masonic Hall')->firstOrFail();
        $this->assertSame('castle-street-masonic-hall', $hall->slug);
        $this->assertSame('1 Castle Street, Durham, DH1 1AA', $hall->fullAddress());

        // A second hall with the same name still gets its own slug.
        $this->actingAs($superAdmin)->post(route('superadmin.masonic_halls.store'), ['name' => 'Castle Street Masonic Hall']);
        $this->assertSame(2, MasonicHall::where('name', 'Castle Street Masonic Hall')->count());

        $this->actingAs($superAdmin)->put(route('superadmin.masonic_halls.update', $hall->id), [
            'name' => 'Castle Street Hall',
            'town' => 'Durham City',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Castle Street Hall', $hall->refresh()->name);

        $this->actingAs($superAdmin)->get(route('superadmin.masonic_halls.index'))->assertOk();

        $this->actingAs($superAdmin)->delete(route('superadmin.masonic_halls.destroy', $hall->id));
        $this->assertModelMissing($hall);
    }

    public function test_superadmin_hall_website_must_be_http(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($superAdmin)->post(route('superadmin.masonic_halls.store'), [
            'name' => 'Bad Hall',
            'website_url' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('website_url');
    }

    public function test_club_admin_cannot_manage_halls(): void
    {
        $this->actingAs($this->admin)->get(route('superadmin.masonic_halls.index'))->assertRedirect('/');
        $this->actingAs($this->admin)->post(route('superadmin.masonic_halls.store'), ['name' => 'Nope']);

        $this->assertSame(0, MasonicHall::count());
    }

    public function test_every_hall_in_the_data_files_is_complete_and_unique(): void
    {
        $rows = (new MasonicHallSeeder)->rows();
        $this->seed(ProvinceSeeder::class);
        $provinceCodes = Province::pluck('code')->all();

        $this->assertGreaterThan(60, count($rows));
        $this->assertSame(count($rows), count(array_unique(array_column($rows, 'slug'))), 'Duplicate hall slugs in the data files.');

        foreach ($rows as $row) {
            $this->assertNotEmpty($row['name'], 'A hall has no name.');
            $this->assertNotEmpty($row['town'], "{$row['name']} has no town.");

            if ($row['province_code'] !== '') {
                $this->assertContains($row['province_code'], $provinceCodes, "{$row['name']} names an unknown province.");
            }

            if (($row['country'] ?? '') === 'England') {
                $this->assertMatchesRegularExpression('/^[A-Z]{1,2}\d[A-Z\d]? \d[A-Z]{2}$/', $row['postcode'] ?? '', "{$row['name']} has a bad postcode.");
            }
        }
    }

    public function test_seeding_the_full_list_puts_stockton_in_durham(): void
    {
        $this->seed(ProvinceSeeder::class);

        $stockton = MasonicHall::where('name', 'Stockton-on-Tees Masonic Hall')->firstOrFail();

        $this->assertSame('durham', $stockton->province->code);
        $this->assertSame('Wellington Street, Stockton-on-Tees, TS18 1RD', $stockton->fullAddress());
        $this->assertSame(0, MasonicHall::whereNull('province_id')->where('slug', '!=', 'freemasons-hall-edinburgh')->count());
    }

    public function test_seeding_again_keeps_a_province_that_was_set_by_hand(): void
    {
        $this->seed(ProvinceSeeder::class);
        $durham = Province::where('code', 'durham')->firstOrFail();
        $hall = MasonicHall::where('name', 'Stockton-on-Tees Masonic Hall')->firstOrFail();
        $hall->update(['province_id' => $this->bristol->id]);

        $this->seed(MasonicHallSeeder::class);

        $this->assertSame($this->bristol->id, $hall->refresh()->province_id);
        $this->assertNotSame($durham->id, $hall->province_id);
    }

    public function test_venues_that_are_not_masonic_halls_are_kept_and_typed(): void
    {
        $this->seed(ProvinceSeeder::class);

        $this->assertSame('hotel', MasonicHall::where('name', 'Lumley Castle Hotel')->value('kind'));
        $this->assertSame('hotel', MasonicHall::where('name', 'Gosforth Hotel, Newcastle upon Tyne')->value('kind'));
        $this->assertSame('club', MasonicHall::where('name', 'Northern Counties Club')->value('kind'));
        $this->assertSame('hall', MasonicHall::where('name', 'Stockton-on-Tees Masonic Hall')->value('kind'));
        $this->assertGreaterThan(100, MasonicHall::where('kind', '!=', 'hall')->count());

        foreach ((new MasonicHallSeeder)->rows() as $row) {
            $this->assertArrayHasKey($row['kind'], MasonicHall::KINDS, "{$row['name']} has an unknown type.");
        }
    }

    public function test_a_hall_is_a_masonic_hall_unless_told_otherwise(): void
    {
        $this->assertSame('hall', MasonicHall::factory()->create()->refresh()->kind);
    }

    public function test_settings_page_tells_the_page_what_kind_each_venue_is(): void
    {
        MasonicHall::factory()->create(['province_id' => $this->bristol->id, 'kind' => 'hotel']);

        $this->actingAs($this->admin)
            ->get(route('admin.settings.show', ['clubSlug' => $this->club->slug]))
            ->assertInertia(fn ($page) => $page->where('masonicHalls.0.kind', 'hotel'));
    }

    public function test_a_lodge_can_meet_in_a_hotel_venue(): void
    {
        $hotel = MasonicHall::factory()->create(['province_id' => $this->bristol->id, 'kind' => 'hotel']);

        $this->saveSettings(['masonic_hall_id' => $hotel->id])->assertSessionHasNoErrors();

        $this->assertSame($hotel->id, $this->club->refresh()->masonic_hall_id);
    }

    public function test_superadmin_can_set_the_venue_type_and_bad_types_are_refused(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($superAdmin)->post(route('superadmin.masonic_halls.store'), ['name' => 'Golf Club Room', 'kind' => 'club'])
            ->assertSessionHasNoErrors();
        $this->assertSame('club', MasonicHall::where('name', 'Golf Club Room')->value('kind'));

        $this->actingAs($superAdmin)->post(route('superadmin.masonic_halls.store'), ['name' => 'Odd', 'kind' => 'castle'])
            ->assertSessionHasErrors('kind');
    }
}
