<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\GrantApprovalStatus;
use App\Domains\ClubAccounting\Livewire\Committee\MeetingWorkspace;
use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Models\ClubCommitteeMeeting;
use App\Domains\ClubAccounting\Models\Member;
use App\Enums\Visibility;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventPromo;
use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Holes found by the whole-app security reviews of the public pages and the Livewire screens.
 */
class SecurityReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'lodge', 'available_modules' => ['accounting', 'events']]);
        $this->club = Club::create(['name' => 'Lodge of Unity', 'slug' => 'lodge-of-unity', 'club_type_id' => $type->id, 'is_active' => true]);
        $this->admin = User::factory()->create();
        $this->makeClubAdmin($this->admin, $this->club);
    }

    public function test_the_public_overview_shows_no_newsletters_or_promo_codes(): void
    {
        Newsletter::create(['club_id' => $this->club->id, 'subject' => 'Draft for the board', 'content' => '<p>Secret</p>', 'status' => 'draft']);
        $event = Event::create(['club_id' => $this->club->id, 'title' => 'Ladies Night', 'slug' => 'ladies-night', 'starts_at' => now()->addWeek(), 'status' => 'upcoming', 'visibility' => Visibility::Public]);
        EventPromo::create(['event_id' => $event->id, 'code' => 'STAFF50', 'discount_type' => 'percent', 'discount_amount' => 50]);

        $this->get(route('clubs.show', $this->club->slug))
            ->assertOk()
            ->assertDontSee('Draft for the board')
            ->assertDontSee('STAFF50')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing('club.newsletters')
                ->where('club.events.0.has_promos', true)
                ->where('club.events.0.promos', []));
    }

    public function test_the_visitor_sign_up_page_sends_only_the_lodge_name(): void
    {
        $this->club->update(['settings' => ['bank_account_number' => '12345678']]);

        $this->get(route('clubs.visitor.register', $this->club->slug))
            ->assertOk()
            ->assertDontSee('12345678')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('club', ['name' => 'Lodge of Unity', 'slug' => 'lodge-of-unity']));
    }

    public function test_turning_down_a_request_cannot_remove_an_owner_or_an_active_member(): void
    {
        $owner = User::factory()->create();
        $this->club->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $pending = User::factory()->create();
        $this->club->users()->attach($pending->id, ['role' => 'member', 'status' => 'pending']);

        $this->actingAs($this->admin)->post(route('clubs.members.reject', ['slug' => $this->club->slug, 'userId' => $owner->id]))->assertNotFound();
        $this->actingAs($this->admin)->post(route('clubs.members.reject', ['slug' => $this->club->slug, 'userId' => $pending->id]))->assertRedirect();

        $this->assertTrue($this->club->users()->whereKey($owner->id)->exists());
        $this->assertFalse($this->club->users()->whereKey($pending->id)->exists());
    }

    public function test_the_password_reset_reply_does_not_say_whether_an_account_exists(): void
    {
        $this->post(route('password.email'), ['email' => 'nobody@example.test'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');
    }

    public function test_the_committee_screen_cannot_pay_out_a_grant_or_name_another_lodges_member(): void
    {
        $secretary = User::factory()->create();
        $this->club->users()->attach($secretary->id, ['role' => 'coach', 'status' => 'active']);
        $meeting = ClubCommitteeMeeting::create(['club_id' => $this->club->id, 'title' => 'Committee', 'meeting_date' => now()->addWeek()]);
        $grant = CharityGrant::create(['club_id' => $this->club->id, 'recipient_name' => 'Hospice', 'purpose' => 'Care', 'amount' => 100, 'approval_status' => GrantApprovalStatus::Proposed]);
        $otherClub = Club::create(['name' => 'Other', 'slug' => 'other-lodge', 'club_type_id' => $this->club->club_type_id, 'is_active' => true]);
        $foreign = Member::create(['club_id' => $otherClub->id, 'first_name' => 'Far', 'last_name' => 'Away', 'membership_status' => 'active', 'current_office' => 'member']);

        Livewire::actingAs($secretary)->test(MeetingWorkspace::class, ['clubSlug' => $this->club->slug, 'meetingId' => $meeting->id])
            ->call('updateGrantApprovalStatus', $grant->id, 'disbursed')
            ->call('updateGrantSeconder', $grant->id, $foreign->id);

        $this->assertSame(GrantApprovalStatus::Proposed, $grant->fresh()->approval_status);
        $this->assertNull($grant->fresh()->seconder_member_id);

        Livewire::actingAs($this->admin)->test(MeetingWorkspace::class, ['clubSlug' => $this->club->slug, 'meetingId' => $meeting->id])
            ->call('updateGrantApprovalStatus', $grant->id, 'disbursed');
        $this->assertSame(GrantApprovalStatus::Disbursed, $grant->fresh()->approval_status, 'an administrator can record the payment');
    }
}
