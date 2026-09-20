<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubAdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Club $alpha;

    private Club $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'code' => 'craft_lodge',
            'name' => 'Craft Lodge',
            'available_modules' => [],
        ]);

        $this->alpha = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Alpha Lodge',
            'slug' => 'alpha-lodge',
        ]);

        $this->beta = Club::create([
            'club_type_id' => $clubType->id,
            'name' => 'Beta Lodge',
            'slug' => 'beta-lodge',
        ]);
    }

    private function adminUrl(Club $club): string
    {
        return "/{$club->slug}/admin/settings";
    }

    public function test_admin_of_one_club_cannot_administer_another_club(): void
    {
        $user = $this->makeClubAdmin(User::factory()->create(), $this->alpha);

        $this->actingAs($user)->get($this->adminUrl($this->beta))->assertForbidden();
    }

    public function test_admin_can_administer_their_own_club(): void
    {
        $user = $this->makeClubAdmin(User::factory()->create(), $this->alpha);

        $this->actingAs($user)->get($this->adminUrl($this->alpha))->assertSuccessful();
    }

    public function test_user_with_no_membership_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get($this->adminUrl($this->alpha))->assertForbidden();
    }

    public function test_plain_member_cannot_reach_the_admin_area(): void
    {
        $user = $this->makeClubAdmin(User::factory()->create(), $this->alpha, 'member');

        $this->actingAs($user)->get($this->adminUrl($this->alpha))->assertForbidden();
    }

    public function test_non_active_membership_is_forbidden(): void
    {
        $user = User::factory()->create();
        $this->alpha->users()->attach($user->id, ['role' => 'admin', 'status' => 'pending']);

        $this->actingAs($user)->get($this->adminUrl($this->alpha))->assertForbidden();
    }

    public function test_super_admin_may_administer_any_club(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($user)->get($this->adminUrl($this->beta))->assertSuccessful();
    }

    public function test_guest_is_redirected_to_login_rather_than_forbidden(): void
    {
        $this->get($this->adminUrl($this->alpha))->assertRedirect('/login');
    }

    public function test_treasurer_may_reach_billing_but_not_settings(): void
    {
        $user = $this->makeClubAdmin(User::factory()->create(), $this->alpha, 'treasurer');

        // manage_billing includes treasurer; manage_settings does not.
        $this->actingAs($user)->get("/{$this->alpha->slug}/admin/accounting")->assertSuccessful();
        $this->actingAs($user)->get($this->adminUrl($this->alpha))->assertForbidden();
    }

    public function test_coach_cannot_reach_accounting(): void
    {
        $user = $this->makeClubAdmin(User::factory()->create(), $this->alpha, 'coach');

        $this->actingAs($user)->get("/{$this->alpha->slug}/admin/accounting")->assertForbidden();
    }

    public function test_club_can_widen_a_capability_through_its_permission_matrix(): void
    {
        $user = $this->makeClubAdmin(User::factory()->create(), $this->alpha, 'coach');

        $this->alpha->update([
            'settings' => array_merge($this->alpha->settings ?? [], [
                'permission_matrix' => [
                    'manage_billing' => ['roles' => ['owner', 'admin', 'treasurer', 'coach']],
                ],
            ]),
        ]);

        $this->actingAs($user)->get("/{$this->alpha->slug}/admin/accounting")->assertSuccessful();
    }

    public function test_owner_is_never_locked_out_by_a_narrowed_matrix(): void
    {
        $user = $this->makeClubAdmin(User::factory()->create(), $this->alpha, 'owner');

        $this->alpha->update([
            'settings' => array_merge($this->alpha->settings ?? [], [
                'permission_matrix' => [
                    'manage_settings' => ['roles' => []],
                ],
            ]),
        ]);

        $this->actingAs($user)->get($this->adminUrl($this->alpha))->assertSuccessful();
    }
}
