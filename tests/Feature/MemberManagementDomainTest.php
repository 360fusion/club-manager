<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Livewire\Members\MemberIndex;
use App\Domains\ClubAccounting\Livewire\Members\MemberProfile;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberManagementDomainTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'lodge',
            'available_modules' => ['accounting', 'meetings', 'members'],
        ]);

        $this->club = Club::create([
            'name' => 'The Lodge of Fraternity No. 1418',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create(['name' => 'Arthur Pendelton', 'email' => 'arthur@lodge.org']);
        $this->admin->clubs()->attach($this->club, ['role' => 'admin']);
    }

    public function test_member_model_accessors_and_scopes(): void
    {
        $m1 = Member::create([
            'club_id' => $this->club->id,
            'title' => 'WBro',
            'first_name' => 'Henry',
            'last_name' => 'Vane',
            'email' => 'henry@lodge.org',
            'masonic_rank' => 'WBro',
            'grand_rank' => 'PAGDC',
            'provincial_rank' => 'PPrGSuptWks',
            'grand_lodge_number' => '1049281',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::WorshipfulMaster,
        ]);

        $m2 = Member::create([
            'club_id' => $this->club->id,
            'title' => 'Bro',
            'first_name' => 'Charles',
            'last_name' => 'Darwin',
            'email' => 'charles@lodge.org',
            'masonic_rank' => 'Bro',
            'membership_status' => MembershipStatus::Resigned,
            'current_office' => LodgeOffice::Member,
        ]);

        // Accessors
        $this->assertEquals('Henry Vane', $m1->full_name);
        $this->assertEquals('WBro Henry Vane PAGDC, PPrGSuptWks', $m1->formatted_rank_name);
        $this->assertTrue($m1->is_past_master);
        $this->assertFalse($m2->is_past_master);

        // Scopes
        $this->assertCount(1, Member::where('club_id', $this->club->id)->active()->get());
        $this->assertCount(1, Member::where('club_id', $this->club->id)->officers()->get());
        $this->assertCount(1, Member::where('club_id', $this->club->id)->pastMasters()->get());
        $this->assertCount(1, Member::where('club_id', $this->club->id)->search('1049281')->get());
    }

    public function test_member_index_livewire_component_and_actions(): void
    {
        $this->actingAs($this->admin);

        $member = Member::create([
            'club_id' => $this->club->id,
            'title' => 'Bro',
            'first_name' => 'Alexander',
            'last_name' => 'Hamilton',
            'email' => 'alex@lodge.org',
            'masonic_rank' => 'Bro',
            'grand_lodge_number' => '998271',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::SeniorWarden,
        ]);

        Livewire::test(MemberIndex::class, ['clubSlug' => $this->club->slug])
            ->assertSee('Lodge Member Directory')
            ->assertSee('Alexander Hamilton')
            ->assertSee('Senior Warden')
            ->set('search', '998271')
            ->assertSee('Alexander Hamilton')
            ->set('search', 'NonExistent')
            ->assertDontSee('Alexander Hamilton')
            ->set('search', '')
            ->call('openAddModal')
            ->assertSet('showMemberModal', true)
            ->set('first_name', 'Benjamin')
            ->set('last_name', 'Franklin')
            ->set('email', 'ben@lodge.org')
            ->set('masonic_rank', 'WBro')
            ->set('current_office', 'treasurer')
            ->set('membership_status', 'active')
            ->call('saveMember')
            ->assertSet('showMemberModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('club_acc_members', [
            'club_id' => $this->club->id,
            'first_name' => 'Benjamin',
            'last_name' => 'Franklin',
            'current_office' => 'treasurer',
        ]);
    }

    public function test_member_index_csv_export(): void
    {
        $this->actingAs($this->admin);

        Member::create([
            'club_id' => $this->club->id,
            'title' => 'WBro',
            'first_name' => 'Isaac',
            'last_name' => 'Newton',
            'email' => 'isaac@lodge.org',
            'masonic_rank' => 'WBro',
            'grand_lodge_number' => '888123',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::Chaplain,
        ]);

        Livewire::test(MemberIndex::class, ['clubSlug' => $this->club->slug])
            ->call('exportCsv')
            ->assertFileDownloaded('Lodge-Roster-lodge-of-fraternity-'.now()->format('Y-m-d').'.csv');
    }

    public function test_member_profile_livewire_component_and_tab_switching(): void
    {
        $this->actingAs($this->admin);

        $member = Member::create([
            'club_id' => $this->club->id,
            'title' => 'Bro',
            'first_name' => 'Gottfried',
            'last_name' => 'Leibniz',
            'email' => 'gottfried@lodge.org',
            'masonic_rank' => 'Bro',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::JuniorWarden,
        ]);

        Livewire::test(MemberProfile::class, [
            'clubSlug' => $this->club->slug,
            'memberId' => $member->id,
        ])
            ->assertSet('activeTab', 'details')
            ->assertSee('Bro Gottfried Leibniz')
            ->assertSee('Junior Warden')
            ->set('activeTab', 'finances')
            ->assertSee('Subscription Balance Snapshot')
            ->call('toggleEdit')
            ->assertSet('isEditing', true)
            ->set('provincial_rank', 'PPrGStdB')
            ->call('updateProfile')
            ->assertSet('isEditing', false)
            ->assertHasNoErrors();

        $this->assertEquals('PPrGStdB', $member->fresh()->provincial_rank);

        // Test Archive Member
        Livewire::test(MemberProfile::class, [
            'clubSlug' => $this->club->slug,
            'memberId' => $member->id,
        ])
            ->call('archiveMember')
            ->assertHasNoErrors();

        $this->assertEquals(MembershipStatus::Resigned, $member->fresh()->membership_status);

        // Test Delete Member
        Livewire::test(MemberProfile::class, [
            'clubSlug' => $this->club->slug,
            'memberId' => $member->id,
        ])
            ->call('deleteMember')
            ->assertRedirect(route('admin.club_acc.members.index', ['clubSlug' => $this->club->slug]));

        $this->assertDatabaseMissing('club_acc_members', ['id' => $member->id]);
    }
}
