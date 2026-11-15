<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Livewire\Members\MemberProfile;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Models\Club;
use App\Models\User;
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
}
