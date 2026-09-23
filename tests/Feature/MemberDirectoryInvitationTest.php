<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Livewire\Members\MemberIndex;
use App\Domains\ClubAccounting\Livewire\Members\MemberProfile;
use App\Domains\ClubAccounting\Models\Member;
use App\Mail\MemberInvitationMail;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class MemberDirectoryInvitationTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'lodge', 'available_modules' => ['members']]);
        $this->club = Club::create(['name' => 'Lodge of Fraternity', 'slug' => 'fraternity', 'club_type_id' => $type->id, 'is_active' => true]);
        $this->admin = User::factory()->create();
        $this->admin->clubs()->attach($this->club, ['role' => 'admin', 'status' => 'active']);
    }

    private function member(array $overrides = [], ?Club $club = null): Member
    {
        static $n = 0;
        $n++;

        return Member::create($overrides + [
            'club_id' => ($club ?? $this->club)->id,
            'first_name' => 'Arthur'.$n,
            'last_name' => 'Pendelton',
            'email' => "arthur{$n}@example.com",
            'masonic_rank' => 'Bro',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::Member,
        ]);
    }

    private function index(?User $user = null)
    {
        return Livewire::actingAs($user ?? $this->admin)->test(MemberIndex::class, ['clubSlug' => $this->club->slug]);
    }

    public function test_directory_shows_each_account_status(): void
    {
        Mail::fake();
        $none = $this->member(['first_name' => 'Nora']);
        $invited = $this->member(['first_name' => 'Ivan']);
        $this->index()->call('inviteMember', $invited->id);

        $user = User::factory()->create();
        $this->club->users()->attach($user, ['role' => 'member', 'status' => 'active', 'invitation_accepted_at' => now()]);
        $this->member(['first_name' => 'Hana', 'user_id' => $user->id]);

        $this->index()
            ->assertSee('Not invited')
            ->assertSee('Invited')
            ->assertSee('Has account');
    }

    public function test_a_member_without_an_email_has_no_invite_button(): void
    {
        $this->member(['first_name' => 'Silent', 'email' => null]);

        $this->index()->assertSee('No email on record')->assertDontSee('wire:click="inviteMember(', false);
    }

    public function test_admin_invites_from_the_directory(): void
    {
        Mail::fake();
        $member = $this->member(['email' => 'new.brother@example.com']);

        $this->index()->call('inviteMember', $member->id)->assertSee('Invitation emailed to new.brother@example.com');

        Mail::assertQueued(MemberInvitationMail::class, fn ($mail) => $mail->hasTo('new.brother@example.com'));
        $this->assertNotNull($member->fresh()->user_id);
    }

    public function test_a_plain_member_cannot_invite(): void
    {
        Mail::fake();
        $regular = User::factory()->create();
        $this->club->users()->attach($regular, ['role' => 'member', 'status' => 'active']);
        $member = $this->member();

        $this->index($regular)->call('inviteMember', $member->id)->assertForbidden();

        Mail::assertNothingQueued();
        $this->assertNull($member->fresh()->user_id);
    }

    public function test_another_clubs_admin_cannot_invite_this_clubs_members(): void
    {
        Mail::fake();
        $otherClub = Club::create(['name' => 'Other', 'slug' => 'other', 'club_type_id' => $this->club->club_type_id, 'is_active' => true]);
        $stranger = User::factory()->create();
        $otherClub->users()->attach($stranger, ['role' => 'admin', 'status' => 'active']);
        $member = $this->member();

        $this->index($stranger)->call('inviteMember', $member->id)->assertForbidden();

        Mail::assertNothingQueued();
    }

    public function test_a_member_id_from_another_club_is_not_found(): void
    {
        Mail::fake();
        $otherClub = Club::create(['name' => 'Other', 'slug' => 'other', 'club_type_id' => $this->club->club_type_id, 'is_active' => true]);
        $foreign = $this->member([], $otherClub);

        $this->expectException(ModelNotFoundException::class);
        $this->index()->call('inviteMember', $foreign->id);
    }

    public function test_bulk_invite_selected_reports_what_happened(): void
    {
        Mail::fake();
        $a = $this->member();
        $b = $this->member();
        $noEmail = $this->member(['email' => null]);

        $this->index()
            ->set('selected', [$a->id, $b->id, $noEmail->id])
            ->call('inviteSelected')
            ->assertSet('selected', [])
            ->assertSee('Invited 2.')
            ->assertSee('Skipped 1: No email address on file.');

        Mail::assertQueued(MemberInvitationMail::class, 2);
    }

    public function test_account_filter_narrows_the_list(): void
    {
        Mail::fake();
        $this->member(['first_name' => 'Uninvited']);
        $invited = $this->member(['first_name' => 'Waiting']);
        $this->index()->call('inviteMember', $invited->id);

        $this->index()->set('accountFilter', 'invited')->assertSee('Waiting')->assertDontSee('Uninvited');
        $this->index()->set('accountFilter', 'not_invited')->assertSee('Uninvited')->assertDontSee('Waiting');
    }

    public function test_resend_and_revoke_from_the_directory(): void
    {
        Mail::fake();
        $member = $this->member();
        $this->index()->call('inviteMember', $member->id);

        $this->index()->call('resendInvite', $member->id)->assertSee('wait a few minutes');

        $this->index()->call('revokeInvite', $member->id)->assertSee('Invitation withdrawn');
        $this->assertNull($member->fresh()->user_id);
    }

    public function test_profile_shows_and_drives_the_account(): void
    {
        Mail::fake();
        $member = $this->member(['email' => 'profile@example.com']);
        $component = fn () => Livewire::actingAs($this->admin)->test(MemberProfile::class, ['clubSlug' => $this->club->slug, 'memberId' => $member->id]);

        $component()->assertSee('Portal Account')->assertSee('Not invited')->call('inviteToPortal')->assertSee('Invitation emailed to profile@example.com');
        $component()->assertSee('Invited')->assertSee('Resend invitation');
    }

    public function test_directory_and_profile_pages_render_for_an_admin(): void
    {
        $member = $this->member();

        $this->actingAs($this->admin)->get(route('admin.club_acc.members.index', ['clubSlug' => $this->club->slug]))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.club_acc.members.show', ['clubSlug' => $this->club->slug, 'memberId' => $member->id]))->assertOk();
    }
}
