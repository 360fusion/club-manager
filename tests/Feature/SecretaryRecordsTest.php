<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Livewire\Members\MemberProfile;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Models\Club;
use App\Models\User;
use App\Support\ClubAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SecretaryRecordsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $secretary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->secretary = User::factory()->create();
        $this->club->users()->attach($this->secretary->id, ['role' => 'coach', 'status' => 'active']);
    }

    private function member(): Member
    {
        return Member::create(['club_id' => $this->club->id, 'first_name' => 'Arthur', 'last_name' => 'Pendleton', 'membership_status' => 'active', 'current_office' => 'member']);
    }

    public function test_the_secretary_can_open_the_membership_roll_and_candidates(): void
    {
        $this->actingAs($this->secretary)->get(route('admin.club_acc.members.index', ['clubSlug' => $this->club->slug]))->assertOk();
        $this->actingAs($this->secretary)->get("/{$this->club->slug}/admin/candidates")->assertOk();
    }

    public function test_a_member_with_dues_on_record_is_not_deleted(): void
    {
        $member = $this->member();
        MemberSubscription::create(['club_id' => $this->club->id, 'member_id' => $member->id, 'billing_year' => 2026, 'due_date' => '2026-04-01', 'amount_due' => 160, 'amount_paid' => 160, 'status' => SubscriptionStatus::Paid, 'invoice_reference' => 'INV-1']);

        Livewire::actingAs($this->secretary)->test(MemberProfile::class, ['clubSlug' => $this->club->slug, 'memberId' => $member->id])
            ->call('deleteMember')
            ->assertSet('noticeType', 'error');

        $this->assertModelExists($member);
        $this->assertSame(1, MemberSubscription::where('member_id', $member->id)->count());
    }

    public function test_an_entry_made_by_mistake_can_still_be_deleted(): void
    {
        $member = $this->member();

        Livewire::actingAs($this->secretary)->test(MemberProfile::class, ['clubSlug' => $this->club->slug, 'memberId' => $member->id])
            ->call('deleteMember')
            ->assertRedirect();

        $this->assertModelMissing($member);
    }

    public function test_the_secretary_cannot_promote_themselves_or_touch_an_admin(): void
    {
        $admin = User::factory()->create();
        $this->club->users()->attach($admin->id, ['role' => 'admin', 'status' => 'active']);
        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $role = fn (User $user, string $to) => $this->actingAs($this->secretary)->post(route('admin.users.role.update', [$this->club->slug, $user->id]), ['role' => $to]);

        $role($this->secretary, 'admin')->assertForbidden();
        $role($this->secretary, 'treasurer')->assertForbidden();
        $role($member, 'admin')->assertForbidden();
        $role($admin, 'member')->assertForbidden();
        $this->actingAs($this->secretary)->post(route('admin.users.status.update', [$this->club->slug, $admin->id]), ['status' => 'past'])->assertForbidden();

        $this->assertSame('coach', $this->club->users()->find($this->secretary->id)->pivot->role);
        $this->assertSame('admin', $this->club->users()->find($admin->id)->pivot->role);
        $this->assertSame('active', $this->club->users()->find($admin->id)->pivot->status);

        $role($member, 'treasurer')->assertRedirect();
        $this->assertSame('treasurer', $this->club->users()->find($member->id)->pivot->role, 'giving a member an ordinary staff role still works');
    }

    public function test_superadmin_access_to_a_lodge_needs_two_factor_sign_in_on_a_real_server(): void
    {
        $superadmin = User::factory()->create(['two_factor_confirmed_at' => null]);
        $superadmin->forceFill(['is_super_admin' => true])->save();
        $this->app['env'] = 'production';

        $this->assertFalse($superadmin->actsAsSuperAdmin());
        $this->assertFalse(ClubAccess::can($superadmin, $this->club, 'manage_billing'));

        $superadmin->forceFill(['two_factor_confirmed_at' => now()])->save();
        $this->assertTrue(ClubAccess::can($superadmin->fresh(), $this->club, 'manage_billing'));
    }
}
