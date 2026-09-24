<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Lodge;
use App\Models\LodgeSchedule;
use App\Models\MasonicHall;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LodgeAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private ClubType $craft;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['is_super_admin' => true]);
        $this->craft = ClubType::create(['name' => 'Craft Lodge', 'code' => 'craft_lodge', 'available_modules' => [], 'default_settings' => []]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'club_type_id' => $this->craft->id,
            'name' => 'Lodge of Industry',
            'number' => '48',
            ...$overrides,
        ];
    }

    public function test_superadmin_can_list_and_filter_lodges(): void
    {
        $province = Province::create(['name' => 'Province of Durham', 'code' => 'durham']);
        Lodge::factory()->create(['name' => 'Alpha Lodge', 'slug' => 'a', 'province_id' => $province->id]);
        Lodge::factory()->create(['name' => 'Beta Lodge', 'slug' => 'b']);

        $this->actingAs($this->superAdmin)->get(route('superadmin.lodges.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('SuperAdmin/Lodges/Index')->has('lodges.data', 2)->where('totals.no_hall', 2));

        $this->actingAs($this->superAdmin)->get(route('superadmin.lodges.index', ['province' => 'durham']))
            ->assertInertia(fn ($page) => $page->has('lodges.data', 1)->where('lodges.data.0.name', 'Alpha Lodge'));

        $this->actingAs($this->superAdmin)->get(route('superadmin.lodges.index', ['gaps' => 'no_province']))
            ->assertInertia(fn ($page) => $page->has('lodges.data', 1)->where('lodges.data.0.name', 'Beta Lodge'));
    }

    public function test_superadmin_can_add_a_lodge_with_a_meeting_pattern(): void
    {
        $hall = MasonicHall::factory()->create();

        $this->actingAs($this->superAdmin)->post(route('superadmin.lodges.store'), $this->payload([
            'masonic_hall_id' => $hall->id,
            'schedule' => ['occurrence' => '4th', 'day_of_week' => 'Monday', 'months' => [3, 1, 2, 2], 'start_time' => '18:45'],
        ]))->assertSessionHasNoErrors();

        $lodge = Lodge::firstOrFail();
        $this->assertSame('lodge-of-industry-48', $lodge->slug);

        $schedule = $lodge->schedules()->firstOrFail();
        $this->assertSame([1, 2, 3], $schedule->months);
        $this->assertSame('manual', $schedule->source);
        $this->assertSame($hall->id, $schedule->masonic_hall_id);
    }

    public function test_two_lodges_with_the_same_name_and_number_get_their_own_slug(): void
    {
        $this->actingAs($this->superAdmin)->post(route('superadmin.lodges.store'), $this->payload());
        $this->actingAs($this->superAdmin)->post(route('superadmin.lodges.store'), $this->payload());

        $this->assertEqualsCanonicalizing(['lodge-of-industry-48', 'lodge-of-industry-48-2'], Lodge::pluck('slug')->all());
    }

    public function test_editing_keeps_the_slug_and_can_replace_or_clear_the_pattern(): void
    {
        $lodge = Lodge::factory()->create(['name' => 'Old Name', 'slug' => 'old-name-lodge', 'club_type_id' => $this->craft->id]);
        LodgeSchedule::factory()->create(['lodge_id' => $lodge->id]);

        $this->actingAs($this->superAdmin)->put(route('superadmin.lodges.update', $lodge->id), $this->payload([
            'name' => 'New Name',
            'schedule' => ['occurrence' => 'last', 'day_of_week' => 'Friday', 'months' => [5], 'start_time' => ''],
        ]))->assertSessionHasNoErrors();

        $lodge->refresh();
        $this->assertSame('New Name', $lodge->name);
        $this->assertSame('old-name-lodge', $lodge->slug);
        $this->assertSame('Friday', $lodge->schedules()->firstOrFail()->day_of_week);
        $this->assertSame(1, $lodge->schedules()->count());

        $this->actingAs($this->superAdmin)->put(route('superadmin.lodges.update', $lodge->id), $this->payload([
            'schedule' => ['occurrence' => '', 'day_of_week' => '', 'months' => []],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(0, $lodge->schedules()->count());
    }

    public function test_the_pattern_can_be_read_again_from_the_wording(): void
    {
        $lodge = Lodge::factory()->create(['club_type_id' => $this->craft->id, 'meets_text' => null]);

        $this->actingAs($this->superAdmin)->put(route('superadmin.lodges.update', $lodge->id), $this->payload([
            'meets_text' => 'Last Thursday Jan to May, Sep to Dec.',
            'reparse' => true,
        ]))->assertSessionHasNoErrors();

        $schedule = $lodge->schedules()->firstOrFail();
        $this->assertSame('last', $schedule->occurrence);
        $this->assertSame('Thursday', $schedule->day_of_week);
        $this->assertSame([1, 2, 3, 4, 5, 9, 10, 11, 12], $schedule->months);
        $this->assertSame('import', $schedule->source);
    }

    public function test_bad_input_is_refused(): void
    {
        $bad = [
            ['schedule' => ['occurrence' => '9th', 'day_of_week' => 'Monday', 'months' => [1]]],
            ['schedule' => ['occurrence' => '1st', 'day_of_week' => 'Someday', 'months' => [1]]],
            ['schedule' => ['occurrence' => '1st', 'day_of_week' => 'Monday', 'months' => [13]]],
            ['schedule' => ['occurrence' => '1st', 'day_of_week' => 'Monday', 'months' => [1], 'start_time' => '25:00']],
            ['website_url' => 'javascript:alert(1)'],
            ['status' => 'imaginary'],
            ['province_id' => 999999],
            ['name' => ''],
        ];

        foreach ($bad as $overrides) {
            $this->actingAs($this->superAdmin)->post(route('superadmin.lodges.store'), $this->payload($overrides))->assertSessionHasErrors();
        }

        $this->assertSame(0, Lodge::count());
    }

    public function test_a_managed_lodge_cannot_be_deleted_but_an_unmanaged_one_can(): void
    {
        $club = Club::create(['club_type_id' => $this->craft->id, 'name' => 'Managed Lodge', 'slug' => 'managed-lodge', 'status' => 'active']);
        $managed = Lodge::factory()->create(['slug' => 'managed', 'club_id' => $club->id]);
        $plain = Lodge::factory()->create(['slug' => 'plain']);

        $this->actingAs($this->superAdmin)->delete(route('superadmin.lodges.destroy', $managed->id))->assertSessionHas('error');
        $this->assertModelExists($managed);

        $this->actingAs($this->superAdmin)->delete(route('superadmin.lodges.destroy', $plain->id));
        $this->assertModelMissing($plain);
    }

    public function test_only_superadmins_can_manage_lodges(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('superadmin.lodges.index'))->assertRedirect('/');
        $this->actingAs($member)->post(route('superadmin.lodges.store'), $this->payload());
        $this->assertSame(0, Lodge::count());

        $this->post(route('superadmin.lodges.store'), $this->payload())->assertRedirect();
        $this->assertSame(0, Lodge::count());
    }

    public function test_the_installation_is_a_month_and_becomes_one_of_the_meeting_months(): void
    {
        $this->actingAs($this->superAdmin)->post(route('superadmin.lodges.store'), $this->payload([
            'installation_month' => 11,
            'schedule' => ['occurrence' => '4th', 'day_of_week' => 'Tuesday', 'months' => [1, 2, 3], 'start_time' => ''],
        ]))->assertSessionHasNoErrors();

        $lodge = Lodge::firstOrFail();
        $this->assertSame(11, $lodge->installation_month);
        $this->assertSame([1, 2, 3, 11], $lodge->schedules()->firstOrFail()->months);
        $this->assertTrue($lodge->isInstallationOn(now()->setDate(2026, 11, 24)));
        $this->assertFalse($lodge->isInstallationOn(now()->setDate(2026, 10, 27)));
        $this->assertSame('November', $lodge->installationMonthName());

        $this->actingAs($this->superAdmin)->get(route('superadmin.lodges.index'))
            ->assertInertia(fn ($page) => $page->where('lodges.data.0.installation_month', 11)->where('months.11', 'November'));
    }

    public function test_only_a_real_month_is_accepted_for_the_installation(): void
    {
        foreach ([0, 13, 'November', 'x'] as $month) {
            $this->actingAs($this->superAdmin)->post(route('superadmin.lodges.store'), $this->payload(['installation_month' => $month]))
                ->assertSessionHasErrors('installation_month');
        }

        $this->assertSame(0, Lodge::count());
    }
}
