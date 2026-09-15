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
}
