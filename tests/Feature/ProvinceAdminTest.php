<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Lodge;
use App\Models\LodgeCorrection;
use App\Models\Province;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProvinceAdminTest extends TestCase
{
    use RefreshDatabase;

    private Province $durham;

    private Lodge $durhamLodge;

    private Lodge $otherLodge;

    private User $provincialAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->durham = Province::create(['name' => 'Province of Durham', 'code' => 'durham']);
        $northumberland = Province::create(['name' => 'Province of Northumberland', 'code' => 'northumberland']);
        $this->durhamLodge = Lodge::factory()->create(['province_id' => $this->durham->id, 'number' => '48']);
        $this->otherLodge = Lodge::factory()->create(['province_id' => $northumberland->id, 'number' => '24']);

        $this->provincialAdmin = User::factory()->create();
        $this->durham->admins()->attach($this->provincialAdmin);
    }

    private function correction(Lodge $lodge): LodgeCorrection
    {
        return LodgeCorrection::factory()->create(['lodge_id' => $lodge->id, 'changes' => ['number' => '4800']]);
    }

    public function test_a_superadmin_appoints_and_removes_a_provincial_admin_by_email(): void
    {
        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $secretary = User::factory()->create(['email' => 'secretary@example.test']);

        $this->actingAs($superAdmin)
            ->post(route('superadmin.provinces.admins.store', $this->durham->id), ['email' => 'secretary@example.test'])
            ->assertSessionHasNoErrors();
        $this->assertTrue($this->durham->admins()->whereKey($secretary->id)->exists());

        $this->actingAs($superAdmin)
            ->post(route('superadmin.provinces.admins.store', $this->durham->id), ['email' => 'nobody@example.test'])
            ->assertSessionHasErrors(['email' => 'No one has an account with that email address yet.']);

        $this->actingAs($superAdmin)->delete(route('superadmin.provinces.admins.destroy', [$this->durham->id, $secretary->id]));
        $this->assertFalse($this->durham->admins()->whereKey($secretary->id)->exists());
    }

    public function test_only_a_superadmin_can_appoint_a_provincial_admin(): void
    {
        $this->actingAs($this->provincialAdmin)
            ->post(route('superadmin.provinces.admins.store', $this->durham->id), ['email' => User::factory()->create()->email])
            ->assertRedirect(route('home'));

        $this->assertSame(1, $this->durham->admins()->count());
    }

    public function test_a_provincial_admin_sees_only_their_provinces_suggestions(): void
    {
        $this->correction($this->durhamLodge);
        $this->correction($this->otherLodge);

        $this->actingAs($this->provincialAdmin)->get(route('members.lodges.fixes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/LodgeCorrections/Index')
                ->where('layout', 'members')
                ->where('counts.open', 1)
                ->has('corrections.data', 1)
                ->where('corrections.data.0.lodge.number', '48'));
    }

    public function test_a_provincial_admin_applies_a_fix_in_their_province(): void
    {
        $correction = $this->correction($this->durhamLodge);

        $this->actingAs($this->provincialAdmin)
            ->post(route('members.lodges.fixes.apply', $correction->id), ['fields' => ['number'], 'values' => ['number' => '4800']])
            ->assertSessionHasNoErrors();

        $this->assertSame('4800', $this->durhamLodge->fresh()->number);
        $this->assertSame($this->provincialAdmin->id, $correction->fresh()->decided_by);
    }

    public function test_a_provincial_admin_cannot_decide_another_provinces_suggestion(): void
    {
        $correction = $this->correction($this->otherLodge);

        $this->actingAs($this->provincialAdmin)
            ->post(route('members.lodges.fixes.apply', $correction->id), ['fields' => ['number'], 'values' => ['number' => '4800']])
            ->assertNotFound();
        $this->actingAs($this->provincialAdmin)
            ->post(route('members.lodges.fixes.reject', $correction->id), ['reason' => 'Not our lodge'])
            ->assertNotFound();

        $this->assertSame('24', $this->otherLodge->fresh()->number);
        $this->assertTrue($correction->fresh()->isOpen());
    }

    public function test_someone_who_looks_after_no_province_is_refused_before_the_form_is_checked(): void
    {
        $member = User::factory()->create();
        $correction = $this->correction($this->durhamLodge);

        $this->actingAs($member)->get(route('members.lodges.fixes.index'))->assertForbidden();
        $this->actingAs($member)->post(route('members.lodges.fixes.apply', $correction->id), [])->assertForbidden();
    }

    public function test_a_provincial_admin_edits_and_adds_listings_only_in_their_provinces(): void
    {
        $managed = Lodge::factory()->create(['province_id' => $this->durham->id, 'club_id' => Club::factory()->create()->id]);
        $values = fn (array $changes = []) => [
            'club_type_id' => $this->durhamLodge->club_type_id, 'province_id' => $this->durham->id, 'name' => 'Lodge of Industry',
            'number' => '48', 'status' => 'active', ...$changes,
        ];

        $this->actingAs($this->provincialAdmin)->get(route('members.lodges.directory.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('SuperAdmin/Lodges/Index')
                ->where('layout', 'members')
                ->where('full', false)
                ->where('totals.all', 2)
                ->has('provinces', 1)
                ->where('linkableClubs', []));

        $this->put(route('members.lodges.directory.update', $this->durhamLodge->id), $values())->assertSessionHasNoErrors()->assertRedirect(route('members.lodges.directory.index'));
        $this->assertSame('Lodge of Industry', $this->durhamLodge->fresh()->name);

        // Another province's lodge is not found; a lodge with its own club account keeps its listing; a lodge cannot be
        // moved to (or added in) a province they don't look after.
        $this->put(route('members.lodges.directory.update', $this->otherLodge->id), $values())->assertNotFound();
        $this->put(route('members.lodges.directory.update', $managed->id), $values())->assertForbidden();
        $this->put(route('members.lodges.directory.update', $this->durhamLodge->id), $values(['province_id' => $this->otherLodge->province_id]))->assertSessionHasErrors('province_id');
        $this->post(route('members.lodges.directory.store'), $values(['province_id' => $this->otherLodge->province_id, 'number' => '9999']))->assertSessionHasErrors('province_id');
        $this->assertSame($this->durham->id, $this->durhamLodge->fresh()->province_id);

        $this->post(route('members.lodges.directory.store'), $values(['name' => 'New Lodge', 'number' => '9999']))->assertSessionHasNoErrors();
        $this->assertTrue(Lodge::where('number', '9999')->where('province_id', $this->durham->id)->exists());

        // Someone who looks after no province is refused before anything is validated.
        $this->actingAs(User::factory()->create())->get(route('members.lodges.directory.index'))->assertForbidden();
        $this->post(route('members.lodges.directory.store'), [])->assertForbidden();
    }
}
