<?php

namespace Tests\Feature\Launchpad;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** The club's activity log, read from Launchpad's team activity, at /{club}/admin/activity */
class ClubActivityPageTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Club $other;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->other = Club::create(['club_type_id' => $type->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);

        $this->club->users()->attach(User::factory()->create(['name' => 'Mia Member'])->id, ['role' => 'member', 'status' => 'active']);
        $this->other->users()->attach(User::factory()->create(['name' => 'Otto Other'])->id, ['role' => 'member', 'status' => 'active']);
    }

    public function test_an_owner_reads_the_clubs_lines_and_not_another_clubs(): void
    {
        $owner = $this->makeClubAdmin(User::factory()->create(), $this->club, 'owner');

        $this->actingAs($owner)->get('/club-a/admin/activity')->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Admin/Activity/Index')
                ->where('events.data', fn ($lines) => collect($lines)->contains(fn ($l) => str_contains($l['summary'], 'Mia Member'))
                    && ! collect($lines)->contains(fn ($l) => str_contains($l['summary'], 'Otto Other'))));
    }

    public function test_a_plain_member_is_refused(): void
    {
        $member = $this->makeClubAdmin(User::factory()->create(), $this->club, 'member');

        $this->actingAs($member)->get('/club-a/admin/activity')->assertForbidden();
    }

    public function test_a_treasurer_is_refused(): void
    {
        $treasurer = $this->makeClubAdmin(User::factory()->create(), $this->club, 'treasurer');

        $this->actingAs($treasurer)->get('/club-a/admin/activity')->assertForbidden();
    }

    public function test_a_super_admin_gets_in(): void
    {
        $staff = User::factory()->create();
        $staff->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($staff)->get('/club-a/admin/activity')->assertOk();
    }
}
