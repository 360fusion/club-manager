<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Livewire\Members\MemberImportPage;
use App\Domains\ClubAccounting\Livewire\Members\MemberProfile;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\GrandLodge;
use App\Models\Province;
use App\Models\User;
use App\Support\MasonicRanks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class RankListsManagementTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    private GrandLodge $grandLodge;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Craft Lodge', 'code' => 'craft_lodge', 'available_modules' => ['members']]);
        $this->grandLodge = GrandLodge::create([
            'name' => 'United Grand Lodge of England', 'code' => 'ugle', 'country' => 'England',
            'grand_ranks' => [['abbreviation' => 'PAGDC', 'title' => 'Past Assistant Grand Director of Ceremonies']],
            'provincial_ranks' => [['abbreviation' => 'PPrSGD', 'title' => 'Past Provincial Senior Grand Deacon']],
        ]);
        $province = Province::create(['grand_lodge_id' => $this->grandLodge->id, 'name' => 'Durham', 'code' => 'durham', 'country' => 'England']);
        $this->club = Club::create(['club_type_id' => $type->id, 'province_id' => $province->id, 'name' => 'Lodge of Fraternity', 'slug' => 'fraternity', 'status' => 'active']);
        $this->admin = User::factory()->create();
        $this->admin->clubs()->attach($this->club, ['role' => 'admin', 'status' => 'active']);
    }

    private function member(array $overrides = []): Member
    {
        return Member::create($overrides + [
            'club_id' => $this->club->id,
            'first_name' => 'Arthur',
            'last_name' => 'Pendelton',
            'email' => 'arthur@example.com',
            'masonic_rank' => 'Bro',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::Member,
        ]);
    }

    // ---- lodge settings ----

    public function test_the_settings_page_offers_the_lodges_lists_and_how_many_members_hold_each(): void
    {
        $this->member(['grand_rank' => 'PAGDC']);
        $this->member(['grand_rank' => 'PAGDC', 'email' => 'b@example.com']);

        $this->actingAs($this->admin)->get(route('admin.settings.show', ['clubSlug' => 'fraternity']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('settings.grand_ranks.0.abbreviation', 'PAGDC')
                ->where('ranks.grandLodgeName', 'United Grand Lodge of England')
                ->where('ranks.usage.grand.PAGDC', 2));
    }

    public function test_the_ranks_tab_is_available_to_any_club_with_a_member_roster(): void
    {
        $rowing = Club::create(['club_type_id' => ClubType::create(['name' => 'Rowing', 'code' => 'rowing', 'available_modules' => []])->id, 'name' => 'Demo', 'slug' => 'demo', 'status' => 'active']);
        $rowing->users()->attach($this->admin, ['role' => 'admin', 'status' => 'active']);

        $this->actingAs($this->admin)->get(route('admin.settings.show', ['clubSlug' => 'demo']))
            ->assertInertia(fn ($page) => $page->where('settings.grand_ranks.0.abbreviation', 'PGD')->has('ranks.usage'));
    }

    public function test_a_lodge_can_edit_its_lists_and_they_are_tidied_on_save(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update', ['clubSlug' => 'fraternity']), [
            'grand_ranks' => [['abbreviation' => ' PJGD ', 'title' => 'Past Junior Grand Deacon'], ['abbreviation' => 'PSGD', 'title' => null]],
            'provincial_ranks' => [],
        ])->assertSessionHasNoErrors();

        $settings = $this->club->fresh()->settings;
        $this->assertSame([['abbreviation' => 'PJGD', 'title' => 'Past Junior Grand Deacon'], ['abbreviation' => 'PSGD', 'title' => '']], $settings['grand_ranks']);
        $this->assertSame([], $settings['provincial_ranks']);
    }

    public function test_the_lists_are_validated(): void
    {
        $url = route('admin.settings.update', ['clubSlug' => 'fraternity']);

        $this->actingAs($this->admin)->put($url, ['grand_ranks' => [['abbreviation' => 'PJGD'], ['abbreviation' => 'pjgd']]])->assertSessionHasErrors('grand_ranks.1.abbreviation');
        $this->actingAs($this->admin)->put($url, ['grand_ranks' => [['abbreviation' => '']]])->assertSessionHasErrors('grand_ranks.0.abbreviation');
        $this->actingAs($this->admin)->put($url, ['provincial_ranks' => [['abbreviation' => str_repeat('x', 31)]]])->assertSessionHasErrors('provincial_ranks.0.abbreviation');
        $this->actingAs($this->admin)->put($url, ['provincial_ranks' => [['abbreviation' => 'OK', 'title' => str_repeat('x', 121)]]])->assertSessionHasErrors('provincial_ranks.0.title');
    }

    public function test_reset_takes_a_fresh_copy_from_the_grand_lodge(): void
    {
        $this->club->update(['settings' => array_merge($this->club->settings, ['grand_ranks' => [['abbreviation' => 'MINE', 'title' => '']]])]);
        $this->grandLodge->update(['grand_ranks' => [['abbreviation' => 'NEW', 'title' => 'Newly listed']]]);

        $this->actingAs($this->admin)->post(route('admin.settings.ranks.reset', ['clubSlug' => 'fraternity']))->assertRedirect()->assertSessionHas('success');

        $this->assertSame([['abbreviation' => 'NEW', 'title' => 'Newly listed']], $this->club->fresh()->settings['grand_ranks']);
    }

    public function test_only_lodge_settings_managers_can_change_or_reset_the_lists(): void
    {
        $regular = User::factory()->create();
        $this->club->users()->attach($regular, ['role' => 'member', 'status' => 'active']);
        $other = Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        $stranger = User::factory()->create();
        $other->users()->attach($stranger, ['role' => 'admin', 'status' => 'active']);
        $before = $this->club->fresh()->settings;

        foreach ([$regular, $stranger] as $user) {
            $this->actingAs($user)->post(route('admin.settings.ranks.reset', ['clubSlug' => 'fraternity']))->assertForbidden();
            $this->actingAs($user)->put(route('admin.settings.update', ['clubSlug' => 'fraternity']), ['grand_ranks' => [['abbreviation' => 'HACK']]])->assertForbidden();
        }

        $this->assertSame($before, $this->club->fresh()->settings);
    }

    // ---- where the lists are used ----

    public function test_the_profile_edit_only_accepts_listed_ranks_or_the_ones_the_member_holds(): void
    {
        $member = $this->member(['provincial_rank' => 'PPrOLD']);
        $component = fn () => Livewire::actingAs($this->admin)->test(MemberProfile::class, ['clubSlug' => 'fraternity', 'memberId' => $member->id]);

        $component()->set('grand_rank', 'PAGDC')->call('updateProfile')->assertHasNoErrors();
        $this->assertSame('PAGDC', $member->fresh()->grand_rank);
        $this->assertSame('PPrOLD', $member->fresh()->provincial_rank);

        $component()->set('grand_rank', 'MADEUP')->call('updateProfile')->assertHasErrors('grand_rank');
        $this->assertSame('PAGDC', $member->fresh()->grand_rank);
    }

    public function test_the_officers_quick_add_only_accepts_listed_ranks(): void
    {
        $url = route('admin.officers.quick_member', ['clubSlug' => 'fraternity']);
        $base = ['first_name' => 'Quick', 'last_name' => 'Add', 'masonic_rank' => 'WBro'];

        $this->actingAs($this->admin)->postJson($url, $base + ['grand_rank' => 'MADEUP'])->assertUnprocessable()->assertJsonValidationErrors('grand_rank');
        $this->actingAs($this->admin)->postJson($url, $base + ['grand_rank' => 'PAGDC', 'provincial_rank' => 'PPrSGD'])->assertSuccessful();

        $this->assertSame('PAGDC', Member::where('first_name', 'Quick')->firstOrFail()->grand_rank);
    }

    public function test_the_officers_page_is_given_the_options(): void
    {
        $this->actingAs($this->admin)->get(route('admin.officers.index', ['clubSlug' => 'fraternity']))
            ->assertInertia(fn ($page) => $page->where('grandRanks.0.value', 'PAGDC')->where('provincialRanks.0.value', 'PPrSGD'));
    }

    public function test_import_matches_ranks_to_the_list_and_keeps_unknown_ones_with_a_warning(): void
    {
        Storage::fake('local');
        $csv = "First Name,Last Name,Grand Rank,Provincial Rank\nAnn,Baker,pagdc,Past Provincial Senior Grand Deacon\nBob,Cook,PMYSTERY,\n";

        Livewire::actingAs($this->admin)->test(MemberImportPage::class, ['clubSlug' => 'fraternity'])
            ->set('file', UploadedFile::fake()->createWithContent('m.csv', $csv))
            ->call('reviewRows')
            ->call('runImport');

        $ann = Member::where('first_name', 'Ann')->firstOrFail();
        $bob = Member::where('first_name', 'Bob')->firstOrFail();
        $this->assertSame(['PAGDC', 'PPrSGD'], [$ann->grand_rank, $ann->provincial_rank]);
        $this->assertSame('PMYSTERY', $bob->grand_rank);
    }

    // ---- superadmin ----

    public function test_a_superadmin_edits_a_grand_lodges_master_lists(): void
    {
        $super = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($super)->get(route('superadmin.grand_lodges.ranks', $this->grandLodge->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('SuperAdmin/GrandLodges/Ranks')->where('grandRanks.0.abbreviation', 'PAGDC')->where('grandLodge.clubs_count', 1));

        $this->actingAs($super)->put(route('superadmin.grand_lodges.ranks.update', $this->grandLodge->id), [
            'grand_ranks' => [['abbreviation' => 'PGD', 'title' => 'Past Grand Deacon']],
            'provincial_ranks' => [['abbreviation' => 'PPrJGD', 'title' => '']],
        ])->assertSessionHas('success');

        $this->assertSame([['abbreviation' => 'PGD', 'title' => 'Past Grand Deacon']], $this->grandLodge->fresh()->grand_ranks);
        $this->assertSame([['abbreviation' => 'PAGDC', 'title' => 'Past Assistant Grand Director of Ceremonies']], $this->club->fresh()->settings['grand_ranks'], 'A lodge that already has its own copy must not change.');
    }

    public function test_a_lodge_admin_cannot_edit_a_grand_lodges_lists(): void
    {
        $this->actingAs($this->admin)->get(route('superadmin.grand_lodges.ranks', $this->grandLodge->id))->assertRedirect();
        $this->actingAs($this->admin)->put(route('superadmin.grand_lodges.ranks.update', $this->grandLodge->id), ['grand_ranks' => [['abbreviation' => 'HACK']]])->assertRedirect();

        $this->assertSame('PAGDC', MasonicRanks::masterFor($this->club->fresh(), 'grand')[0]['abbreviation']);
    }
}
