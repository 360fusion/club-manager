<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\CollectionType;
use App\Domains\ClubAccounting\Enums\GrantApprovalStatus;
use App\Domains\ClubAccounting\Livewire\Charity\CharityDashboard;
use App\Domains\ClubAccounting\Models\CharityCollection;
use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\FestivalTarget;
use App\Domains\ClubAccounting\Models\MemberFestivalGiving;
use App\Domains\ClubAccounting\Services\ReliefChestExportService;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CharityDomainTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;
    protected User $user;
    protected Member $counterMember;
    protected Member $witnessMember;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = \App\Models\ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'lodge',
            'available_modules' => ['accounting', 'meetings', 'members', 'charity'],
        ]);

        $this->club = Club::create([
            'name' => 'Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->user->clubs()->attach($this->club->id, ['role' => 'admin']);

        $this->counterMember = Member::create([
            'club_id' => $this->club->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'masonic_rank' => 'WBro',
            'current_office' => \App\Domains\ClubAccounting\Enums\LodgeOffice::CharitySteward,
            'membership_status' => 'active',
            'joined_at' => now(),
        ]);

        $this->witnessMember = Member::create([
            'club_id' => $this->club->id,
            'first_name' => 'Arthur',
            'last_name' => 'Pendelton',
            'email' => 'arthur@example.com',
            'masonic_rank' => 'WBro',
            'current_office' => \App\Domains\ClubAccounting\Enums\LodgeOffice::AssistantDC,
            'membership_status' => 'active',
            'joined_at' => now(),
        ]);
    }

    public function test_can_record_dual_custody_charity_collection(): void
    {
        $collection = CharityCollection::create([
            'club_id' => $this->club->id,
            'collection_type' => CollectionType::AlmsPlate,
            'cash_amount' => 150.00,
            'cheque_amount' => 50.00,
            'counted_by_member_id' => $this->counterMember->id,
            'witnessed_by_member_id' => $this->witnessMember->id,
        ]);

        $this->assertEquals(200.00, $collection->total_amount);
        $this->assertEquals('Alms Plate Collection', $collection->collection_type->label());
        $this->assertEquals($this->counterMember->id, $collection->countedBy->id);
        $this->assertEquals($this->witnessMember->id, $collection->witnessedBy->id);
    }

    public function test_grant_approval_lifecycle(): void
    {
        $grant = CharityGrant::create([
            'club_id' => $this->club->id,
            'recipient_name' => 'Masonic Charitable Foundation',
            'purpose' => 'Disaster Relief Fund',
            'amount' => 500.00,
            'relief_chest_number' => 'E1418',
            'approval_status' => GrantApprovalStatus::Proposed,
        ]);

        $this->assertEquals(GrantApprovalStatus::Proposed, $grant->approval_status);

        $grant->update(['approval_status' => GrantApprovalStatus::CommitteeApproved]);
        $this->assertEquals(GrantApprovalStatus::CommitteeApproved, $grant->approval_status);

        $grant->update([
            'approval_status' => GrantApprovalStatus::Disbursed,
            'bacs_reference' => 'BACS-MCF-500',
        ]);
        $this->assertEquals(GrantApprovalStatus::Disbursed, $grant->approval_status);
        $this->assertEquals('BACS-MCF-500', $grant->bacs_reference);
    }

    public function test_festival_target_progress_and_tiers(): void
    {
        $target = FestivalTarget::create([
            'club_id' => $this->club->id,
            'festival_name' => 'Durham 2029 Festival',
            'relief_chest_ref' => 'E1418',
            'target_amount' => 10000.00,
            'bronze_tier' => 2500.00,
            'silver_tier' => 5000.00,
            'gold_tier' => 7500.00,
            'platinum_tier' => 10000.00,
        ]);

        CharityCollection::create([
            'club_id' => $this->club->id,
            'collection_type' => CollectionType::AlmsPlate,
            'cash_amount' => 3000.00,
            'cheque_amount' => 2500.00,
            'counted_by_member_id' => $this->counterMember->id,
            'witnessed_by_member_id' => $this->witnessMember->id,
        ]);

        $collectionsTotal = CharityCollection::where('club_id', $this->club->id)
            ->get()
            ->sum(fn ($c) => $c->total_amount);

        $this->assertEquals(5500.00, $collectionsTotal);
        $this->assertEquals(55.0, $target->getPercentage($collectionsTotal));
        $this->assertEquals('Silver Honor', $target->getCurrentTier($collectionsTotal));
    }

    public function test_relief_chest_export_service_generates_valid_csv(): void
    {
        CharityCollection::create([
            'club_id' => $this->club->id,
            'collection_type' => CollectionType::AlmsPlate,
            'cash_amount' => 250.00,
            'cheque_amount' => 100.00,
            'counted_by_member_id' => $this->counterMember->id,
            'witnessed_by_member_id' => $this->witnessMember->id,
        ]);

        CharityGrant::create([
            'club_id' => $this->club->id,
            'recipient_name' => 'MCF Relief Chest',
            'purpose' => 'Festival Giving',
            'amount' => 350.00,
            'relief_chest_number' => 'E1418',
            'approval_status' => GrantApprovalStatus::LodgeVoted,
        ]);

        $service = new ReliefChestExportService();
        $csvContent = $service->generateReliefChestCsv($this->club->id);

        $this->assertStringContainsString('Relief Chest Ref,Provincial Ref,Date,Collection Type', $csvContent);
        $this->assertStringContainsString('250.00', $csvContent);
        $this->assertStringContainsString('350.00', $csvContent);
    }

    public function test_livewire_charity_dashboard_records_collection(): void
    {
        Livewire::actingAs($this->user)
            ->test(CharityDashboard::class, ['clubSlug' => $this->club->slug])
            ->set('collection_type', CollectionType::Raffle->value)
            ->set('cash_amount', '120.00')
            ->set('cheque_amount', '30.00')
            ->set('counted_by_member_id', $this->counterMember->id)
            ->set('witnessed_by_member_id', $this->witnessMember->id)
            ->call('recordCollection')
            ->assertHasNoErrors()
            ->assertSee('Dual-custody meeting collection');

        $this->assertDatabaseHas('club_acc_charity_collections', [
            'club_id' => $this->club->id,
            'collection_type' => CollectionType::Raffle->value,
            'cash_amount' => 120.00,
            'cheque_amount' => 30.00,
        ]);
    }

    public function test_can_propose_grant_with_proposer_seconder_and_committee_meeting(): void
    {
        $meeting = \App\Domains\ClubAccounting\Models\ClubCommitteeMeeting::create([
            'club_id' => $this->club->id,
            'title' => 'Lodge Committee Q3 Meeting',
            'meeting_date' => now()->addDays(5),
            'status' => 'scheduled',
        ]);

        Livewire::actingAs($this->user)
            ->test(CharityDashboard::class, ['clubSlug' => $this->club->slug])
            ->set('recipient_name', 'Local Hospices Care Fund')
            ->set('purpose', 'Annual Equipment Grant')
            ->set('grant_amount', '450.00')
            ->set('proposer_member_id', $this->counterMember->id)
            ->set('seconder_member_id', $this->witnessMember->id)
            ->set('committee_meeting_id', $meeting->id)
            ->call('saveGrant')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('club_acc_charity_grants', [
            'club_id' => $this->club->id,
            'recipient_name' => 'Local Hospices Care Fund',
            'amount' => 450.00,
            'proposer_member_id' => $this->counterMember->id,
            'seconder_member_id' => $this->witnessMember->id,
            'committee_meeting_id' => $meeting->id,
        ]);
    }

    public function test_proposing_grant_linked_to_meeting_creates_agenda_item(): void
    {
        $meeting = \App\Models\Meeting::create([
            'club_id' => $this->club->id,
            'meeting_number' => 412,
            'title' => 'Regular Autumn Meeting',
            'meeting_date' => now()->addDays(14),
            'starts_at' => '18:30',
            'venue' => 'Masonic Hall, Durham',
            'dress_code' => 'Dark Suit & Regalia',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('admin.charity.grants.store', $this->club->slug), [
                'recipient_name' => 'Children Hospice UK',
                'purpose' => 'Winter Heating Support',
                'amount' => '600.00',
                'proposer_member_id' => $this->counterMember->id,
                'seconder_member_id' => $this->witnessMember->id,
                'meeting_id' => $meeting->id,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('club_acc_charity_grants', [
            'club_id' => $this->club->id,
            'recipient_name' => 'Children Hospice UK',
            'amount' => 600.00,
            'meeting_id' => $meeting->id,
        ]);

        $this->assertDatabaseHas('agenda_items', [
            'meeting_id' => $meeting->id,
            'is_ballot' => true,
        ]);

        $agendaItem = \App\Models\AgendaItem::where('meeting_id', $meeting->id)->first();
        $this->assertStringContainsString('Charity Grant Proposition: £600.00 to Children Hospice UK', $agendaItem->title);
        $this->assertStringContainsString('Proposed by John Doe', $agendaItem->description);
        $this->assertStringContainsString('Seconded by Arthur Pendelton', $agendaItem->description);
    }
}
