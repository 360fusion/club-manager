<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\CandidateStage;
use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Livewire\Candidates\CandidatePipeline;
use App\Domains\ClubAccounting\Models\Candidate;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\CandidateTransitionService;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CandidatePipelineDomainTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;

    protected User $adminUser;

    protected Member $proposer;

    protected Member $seconder;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'lodge',
            'available_modules' => ['accounting', 'meetings', 'members'],
        ]);

        $this->club = Club::create([
            'name' => 'Lodge of Fraternity',
            'slug' => 'lodge-of-fraternity',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create();
        $this->adminUser->clubs()->attach($this->club->id, ['role' => 'admin']);

        $this->proposer = Member::create([
            'club_id' => $this->club->id,
            'title' => 'WBro',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'proposer@example.com',
            'masonic_rank' => 'WBro',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::Secretary,
        ]);

        $this->seconder = Member::create([
            'club_id' => $this->club->id,
            'title' => 'Bro',
            'first_name' => 'James',
            'last_name' => 'Smith',
            'email' => 'seconder@example.com',
            'masonic_rank' => 'Bro',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::SeniorWarden,
        ]);
    }

    public function test_can_create_candidate_record(): void
    {
        $candidate = Candidate::create([
            'club_id' => $this->club->id,
            'first_name' => 'Arthur',
            'last_name' => 'Pendelton',
            'email' => 'arthur@example.com',
            'phone' => '07700900123',
            'occupation' => 'Architect',
            'address' => '12 High Street',
            'postcode' => 'OX1 1AA',
            'stage' => CandidateStage::Enquiry,
        ]);

        $this->assertDatabaseHas('club_acc_candidates', [
            'id' => $candidate->id,
            'first_name' => 'Arthur',
            'last_name' => 'Pendelton',
            'stage' => 'enquiry',
        ]);

        $this->assertEquals('Arthur Pendelton', $candidate->full_name);
    }

    public function test_form_p_vetting_and_committee_stage_transition(): void
    {
        $candidate = Candidate::create([
            'club_id' => $this->club->id,
            'first_name' => 'Edward',
            'last_name' => 'Mason',
            'email' => 'edward@example.com',
            'stage' => CandidateStage::Enquiry,
        ]);

        $service = new CandidateTransitionService;

        // Must fail transition to committee without proposer/seconder
        $this->expectException(\InvalidArgumentException::class);
        $service->transitionStage($candidate, CandidateStage::LodgeCommittee);
    }

    public function test_successful_form_p_vetting_and_stage_progression(): void
    {
        $candidate = Candidate::create([
            'club_id' => $this->club->id,
            'first_name' => 'Edward',
            'last_name' => 'Mason',
            'email' => 'edward@example.com',
            'stage' => CandidateStage::Enquiry,
        ]);

        $service = new CandidateTransitionService;

        $service->updateFormPVetting($candidate, [
            'proposer_member_id' => $this->proposer->id,
            'seconder_member_id' => $this->seconder->id,
            'form_p_signed_at' => '2026-09-01',
            'belief_in_supreme_being' => true,
            'no_criminal_record' => true,
            'no_bankruptcies' => true,
            'rule_159_cleared' => true,
        ]);

        $candidate->refresh();
        $this->assertTrue($candidate->isFormPComplete());

        $updated = $service->transitionStage($candidate, CandidateStage::LodgeCommittee);
        $this->assertEquals(CandidateStage::LodgeCommittee, $updated->stage);
    }

    public function test_candidate_initiation_conversion_instantiates_member(): void
    {
        $candidate = Candidate::create([
            'club_id' => $this->club->id,
            'first_name' => 'George',
            'last_name' => 'Sterling',
            'email' => 'george@example.com',
            'phone' => '07711223344',
            'address' => '44 Broad Street',
            'postcode' => 'OX2 6NN',
            'stage' => CandidateStage::BallotApproved,
            'proposer_member_id' => $this->proposer->id,
            'seconder_member_id' => $this->seconder->id,
            'belief_in_supreme_being' => true,
            'no_criminal_record' => true,
            'no_bankruptcies' => true,
        ]);

        $service = new CandidateTransitionService;
        $initiationDate = Carbon::parse('2026-09-15');

        $member = $service->convertCandidateToMember($candidate, $initiationDate);

        $this->assertInstanceOf(Member::class, $member);
        $this->assertEquals($this->club->id, $member->club_id);
        $this->assertEquals('George', $member->first_name);
        $this->assertEquals('Sterling', $member->last_name);
        $this->assertEquals('george@example.com', $member->email);
        $this->assertEquals('Bro', $member->masonic_rank);
        $this->assertEquals(MembershipStatus::Active, $member->membership_status);
        $this->assertEquals(LodgeOffice::Member, $member->current_office);
        $this->assertEquals('2026-09-15', $member->date_of_initiation->format('Y-m-d'));

        $candidate->refresh();
        $this->assertEquals(CandidateStage::Initiated, $candidate->stage);
        $this->assertEquals($member->id, $candidate->converted_member_id);
    }

    public function test_candidate_pipeline_livewire_component(): void
    {
        $candidate = Candidate::create([
            'club_id' => $this->club->id,
            'first_name' => 'Oliver',
            'last_name' => 'Twist',
            'email' => 'oliver@example.com',
            'stage' => CandidateStage::Enquiry,
        ]);

        $this->actingAs($this->adminUser);

        Livewire::test(CandidatePipeline::class, ['clubSlug' => $this->club->slug])
            ->assertStatus(200)
            ->assertSee('Candidate Pipeline', false)
            ->assertSee('Oliver Twist')
            ->call('editCandidate', $candidate->id)
            ->assertSet('first_name', 'Oliver')
            ->set('occupation', 'Senior Engineer')
            ->call('saveCandidate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('club_acc_candidates', [
            'id' => $candidate->id,
            'occupation' => 'Senior Engineer',
        ]);
    }
}
