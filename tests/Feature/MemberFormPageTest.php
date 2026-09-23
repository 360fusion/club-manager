<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Livewire\Members\MemberFormPage;
use App\Domains\ClubAccounting\Models\Member;
use App\Mail\MemberInvitationMail;
use App\Models\Accounting\AccountingContact;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class MemberFormPageTest extends TestCase
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
        return Member::create($overrides + [
            'club_id' => ($club ?? $this->club)->id,
            'first_name' => 'Arthur',
            'last_name' => 'Pendelton',
            'email' => 'arthur@example.com',
            'masonic_rank' => 'Bro',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::Member,
        ]);
    }

    private function page(?int $memberId = null, ?User $user = null)
    {
        return Livewire::actingAs($user ?? $this->admin)->test(MemberFormPage::class, ['clubSlug' => $this->club->slug, 'memberId' => $memberId]);
    }

    public function test_the_add_and_edit_pages_render_for_an_admin(): void
    {
        $member = $this->member();

        $this->actingAs($this->admin)->get(route('admin.club_acc.members.create', ['clubSlug' => $this->club->slug]))->assertOk()->assertSee('Add New Lodge Member');
        $this->actingAs($this->admin)->get(route('admin.club_acc.members.edit', ['clubSlug' => $this->club->slug, 'memberId' => $member->id]))->assertOk()->assertSee('Edit Member Record')->assertSee('Arthur');
    }

    public function test_the_pages_are_closed_to_members_and_other_clubs(): void
    {
        $member = $this->member();
        $regular = User::factory()->create();
        $this->club->users()->attach($regular, ['role' => 'member', 'status' => 'active']);
        $other = Club::create(['name' => 'Other', 'slug' => 'other', 'club_type_id' => $this->club->club_type_id, 'is_active' => true]);
        $stranger = User::factory()->create();
        $other->users()->attach($stranger, ['role' => 'admin', 'status' => 'active']);

        foreach ([$regular, $stranger] as $user) {
            $this->actingAs($user)->get(route('admin.club_acc.members.create', ['clubSlug' => $this->club->slug]))->assertForbidden();
            $this->actingAs($user)->get(route('admin.club_acc.members.edit', ['clubSlug' => $this->club->slug, 'memberId' => $member->id]))->assertForbidden();
        }
    }

    public function test_a_member_is_added_and_the_admin_returns_to_the_directory(): void
    {
        $this->page()
            ->set('first_name', 'Benjamin')
            ->set('last_name', 'Franklin')
            ->set('email', 'ben@example.com')
            ->set('current_office', 'treasurer')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.club_acc.members.index', ['clubSlug' => $this->club->slug]));

        $member = Member::where('club_id', $this->club->id)->where('email', 'ben@example.com')->firstOrFail();
        $this->assertSame(LodgeOffice::Treasurer, $member->current_office);
    }

    public function test_names_are_required_and_values_are_checked(): void
    {
        $this->page()->call('save')->assertHasErrors(['first_name', 'last_name']);
        $this->page()->set('first_name', 'A')->set('last_name', 'B')->set('email', 'nope')->set('membership_status', 'mystery')->call('save')->assertHasErrors(['email', 'membership_status']);

        $this->assertSame(0, Member::count());
    }

    public function test_the_invitation_checkbox_is_switched_by_the_browser_not_disabled_when_the_page_loads(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.club_acc.members.create', ['clubSlug' => $this->club->slug]))->getContent();

        $this->assertStringContainsString('wire:model="sendInvitation"', $html);
        $this->assertStringContainsString(':disabled="($wire.email ?? \'\').trim() === \'\'"', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]*wire:model="sendInvitation"[^>]*\sdisabled(\s|=|>)/', $html);
    }

    public function test_a_new_member_can_be_invited_in_the_same_step(): void
    {
        Mail::fake();

        $this->page()
            ->set('first_name', 'Bertie')
            ->set('last_name', 'Wooster')
            ->set('email', 'bertie@example.com')
            ->set('sendInvitation', true)
            ->call('save');

        Mail::assertQueued(MemberInvitationMail::class, fn ($mail) => $mail->hasTo('bertie@example.com'));
    }

    public function test_editing_fills_the_form_saves_changes_and_returns_to_the_profile(): void
    {
        $member = $this->member(['phone' => '111', 'date_of_joining' => '2015-06-01']);

        $this->page($member->id)
            ->assertSet('first_name', 'Arthur')
            ->assertSet('phone', '111')
            ->assertSet('date_of_joining', '2015-06-01')
            ->set('phone', '222')
            ->call('save')
            ->assertRedirect(route('admin.club_acc.members.show', ['clubSlug' => $this->club->slug, 'memberId' => $member->id]));

        $this->assertSame('222', $member->fresh()->phone);
    }

    public function test_another_clubs_member_cannot_be_edited(): void
    {
        $other = Club::create(['name' => 'Other', 'slug' => 'other', 'club_type_id' => $this->club->club_type_id, 'is_active' => true]);
        $foreign = $this->member([], $other);

        $this->expectException(ModelNotFoundException::class);
        $this->page($foreign->id);
    }

    public function test_only_this_clubs_ledger_contacts_can_be_linked(): void
    {
        $other = Club::create(['name' => 'Other', 'slug' => 'other', 'club_type_id' => $this->club->club_type_id, 'is_active' => true]);
        $theirs = AccountingContact::create(['club_id' => $other->id, 'name' => 'Their Contact', 'first_name' => 'Their', 'last_name' => 'Contact', 'email' => 'x@example.com']);

        $this->page()->set('first_name', 'A')->set('last_name', 'B')->set('customer_account_id', $theirs->id)->call('save');

        $this->assertNull(Member::firstOrFail()->customer_account_id);
    }

    public function test_the_title_comes_from_the_masonic_rank_and_is_not_entered(): void
    {
        $this->page()->assertDontSee('Title Prefix');

        $this->page()->set('first_name', 'Henry')->set('last_name', 'Vane')->set('masonic_rank', 'WBro')->call('save')->assertHasNoErrors();

        $member = Member::firstOrFail();
        $this->assertSame('WBro', $member->title);
        $this->assertSame('WBro Henry Vane', $member->formatted_rank_name);

        $member->update(['masonic_rank' => 'VWBro']);
        $this->assertSame('VWBro Henry Vane', $member->fresh()->formatted_rank_name);
    }

    public function test_a_stored_title_can_no_longer_disagree_with_the_rank(): void
    {
        $member = $this->member(['masonic_rank' => 'RWBro']);
        Member::whereKey($member->id)->update(['title' => 'Bro.']);

        $this->assertSame('RWBro', $member->fresh()->title);
        $this->assertStringStartsWith('RWBro ', $member->fresh()->formatted_rank_name);
    }

    public function test_only_real_masonic_ranks_are_accepted(): void
    {
        $this->page()->set('first_name', 'A')->set('last_name', 'B')->set('masonic_rank', 'Emperor')->call('save')->assertHasErrors('masonic_rank');
    }

    public function test_grand_and_provincial_ranks_are_chosen_from_the_lodges_lists(): void
    {
        $this->club->update(['settings' => ['grand_ranks' => [['abbreviation' => 'PAGDC', 'title' => 'Past Assistant Grand Director of Ceremonies']], 'provincial_ranks' => [['abbreviation' => 'PPrSGD', 'title' => 'Past Provincial Senior Grand Deacon']]]]);

        $this->page()
            ->assertSee('PAGDC — Past Assistant Grand Director of Ceremonies')
            ->assertSee('PPrSGD — Past Provincial Senior Grand Deacon')
            ->set('first_name', 'A')->set('last_name', 'B')
            ->set('grand_rank', 'PAGDC')->set('provincial_rank', 'PPrSGD')
            ->call('save')
            ->assertHasNoErrors();

        $member = Member::firstOrFail();
        $this->assertSame('PAGDC', $member->grand_rank);
        $this->assertSame('PPrSGD', $member->provincial_rank);
    }

    public function test_a_rank_outside_the_list_is_refused(): void
    {
        $this->club->update(['settings' => ['grand_ranks' => [['abbreviation' => 'PAGDC', 'title' => '']], 'provincial_ranks' => []]]);

        $this->page()->set('first_name', 'A')->set('last_name', 'B')->set('grand_rank', 'MADEUP')->set('provincial_rank', 'ALSOFAKE')->call('save')->assertHasErrors(['grand_rank', 'provincial_rank']);

        $this->assertSame(0, Member::count());
    }

    public function test_a_member_keeps_a_rank_that_is_no_longer_in_the_list(): void
    {
        $this->club->update(['settings' => ['grand_ranks' => [['abbreviation' => 'PAGDC', 'title' => '']], 'provincial_ranks' => []]]);
        $member = $this->member(['grand_rank' => 'PGOLD']);

        $this->page($member->id)
            ->assertSee('PGOLD (not in your list)')
            ->assertSet('grand_rank', 'PGOLD')
            ->set('phone', '999')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('PGOLD', $member->fresh()->grand_rank);
        $this->assertSame('999', $member->fresh()->phone);

        $this->page($member->id)->set('grand_rank', 'SOMETHINGELSE')->call('save')->assertHasErrors('grand_rank');
    }

    public function test_the_directory_links_to_the_pages_instead_of_a_pop_up(): void
    {
        $member = $this->member();

        $this->actingAs($this->admin)->get(route('admin.club_acc.members.index', ['clubSlug' => $this->club->slug]))
            ->assertOk()
            ->assertSee(route('admin.club_acc.members.create', ['clubSlug' => $this->club->slug]), false)
            ->assertSee(route('admin.club_acc.members.edit', ['clubSlug' => $this->club->slug, 'memberId' => $member->id]), false);
    }
}
