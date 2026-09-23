<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\CandidateStage;
use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Livewire\Candidates\CandidateDetail;
use App\Domains\ClubAccounting\Livewire\Candidates\CandidatePipeline;
use App\Domains\ClubAccounting\Models\Candidate;
use App\Domains\ClubAccounting\Models\CandidateEvent;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\CandidateTransitionService;
use App\Mail\SignatureRequestMail;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\SignatureRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class CandidatePipelineDomainTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;

    protected Club $otherClub;

    protected User $admin;

    protected User $treasurer;

    protected User $member;

    protected Member $proposer;

    protected Member $seconder;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'lodge', 'available_modules' => ['accounting', 'meetings', 'members']]);
        $this->club = Club::create(['name' => 'Lodge of Fraternity', 'slug' => 'lodge-of-fraternity', 'club_type_id' => $clubType->id, 'status' => 'active']);
        $this->otherClub = Club::create(['name' => 'Lodge of Concord', 'slug' => 'lodge-of-concord', 'club_type_id' => $clubType->id, 'status' => 'active']);

        $this->admin = $this->join($this->club, 'admin');
        $this->treasurer = $this->join($this->club, 'treasurer');
        $this->member = $this->join($this->club, 'member');

        $this->proposer = Member::create(['club_id' => $this->club->id, 'title' => 'WBro', 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'proposer@example.com', 'masonic_rank' => 'WBro', 'membership_status' => MembershipStatus::Active, 'current_office' => LodgeOffice::Secretary]);
        $this->seconder = Member::create(['club_id' => $this->club->id, 'title' => 'Bro', 'first_name' => 'James', 'last_name' => 'Smith', 'email' => 'seconder@example.com', 'masonic_rank' => 'Bro', 'membership_status' => MembershipStatus::Active, 'current_office' => LodgeOffice::SeniorWarden]);
    }

    private function join(Club $club, string $role): User
    {
        $user = User::factory()->create();
        $user->clubs()->attach($club->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    private function candidate(array $extra = []): Candidate
    {
        return Candidate::create($extra + [
            'club_id' => $this->club->id,
            'first_name' => 'Edward',
            'last_name' => 'Mason',
            'email' => 'edward@example.com',
            'stage' => CandidateStage::Enquiry,
            'stage_entered_at' => now()->subDays(2),
        ]);
    }

    private function service(): CandidateTransitionService
    {
        return app(CandidateTransitionService::class);
    }

    public function test_can_create_candidate_record(): void
    {
        $candidate = $this->candidate(['first_name' => 'Arthur', 'last_name' => 'Pendelton', 'phone' => '07700900123', 'occupation' => 'Architect', 'address' => '12 High Street', 'postcode' => 'OX1 1AA']);

        $this->assertDatabaseHas('club_acc_candidates', ['id' => $candidate->id, 'first_name' => 'Arthur', 'last_name' => 'Pendelton', 'stage' => 'enquiry']);
        $this->assertEquals('Arthur Pendelton', $candidate->full_name);
    }

    public function test_a_candidate_moves_one_stage_at_a_time_and_committee_needs_a_proposer_and_a_different_seconder(): void
    {
        $candidate = $this->candidate();
        $service = $this->service();

        $service->transitionStage($candidate, CandidateStage::FirstInterview);
        $this->assertSame(CandidateStage::FirstInterview, $candidate->fresh()->stage);

        try {
            $service->transitionStage($candidate, CandidateStage::LodgeCommittee);
            $this->fail('Should not skip a stage without a reason.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Skipping a stage needs a reason', $e->getMessage());
        }

        $service->transitionStage($candidate, CandidateStage::InterviewPending);

        try {
            $service->transitionStage($candidate, CandidateStage::LodgeCommittee);
            $this->fail('Committee needs a proposer and seconder.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('proposer and seconder', $e->getMessage());
        }

        try {
            $service->transitionStage($candidate, CandidateStage::LodgeCommittee, ['proposer_member_id' => $this->proposer->id, 'seconder_member_id' => $this->proposer->id]);
            $this->fail('Proposer and seconder must differ.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must be different', $e->getMessage());
        }

        $updated = $service->transitionStage($candidate, CandidateStage::LodgeCommittee, ['proposer_member_id' => $this->proposer->id, 'seconder_member_id' => $this->seconder->id]);
        $this->assertSame(CandidateStage::LodgeCommittee, $updated->stage);
        $this->assertDatabaseHas('club_acc_candidate_events', ['candidate_id' => $candidate->id, 'type' => 'stage_change', 'to_stage' => 'lodge_committee']);
    }

    public function test_proposed_needs_committee_recommendation_form_p_and_a_proposal_date(): void
    {
        $candidate = $this->candidate(['stage' => CandidateStage::LodgeCommittee, 'proposer_member_id' => $this->proposer->id, 'seconder_member_id' => $this->seconder->id]);
        $service = $this->service();

        try {
            $service->transitionStage($candidate, CandidateStage::Proposed);
            $this->fail('Proposed needs the committee recommendation, Form P and a proposal date.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString("committee's recommendation", $e->getMessage());
        }

        $service->recordCommitteeDecision($candidate, $this->admin, true, 'Good fit for the lodge.');
        $service->updateFormPVetting($candidate->fresh(), ['form_p_signed_at' => '2026-09-01', 'proposed_at' => '2026-09-10']);

        $updated = $service->transitionStage($candidate->fresh(), CandidateStage::Proposed);
        $this->assertSame(CandidateStage::Proposed, $updated->stage);
        $this->assertNotNull($updated->committee_recommended_at);
    }

    public function test_ballot_result_moves_to_accepted_with_a_one_year_initiation_window_or_rejects(): void
    {
        $candidate = $this->candidate(['stage' => CandidateStage::Proposed, 'proposed_at' => '2026-09-01']);
        $service = $this->service();

        $updated = $service->recordBallot($candidate, $this->admin, Carbon::parse('2026-09-15'), 'elected');
        $this->assertSame(CandidateStage::Accepted, $updated->stage);
        $this->assertSame('2027-09-15', $updated->initiate_by->format('Y-m-d'));
        $this->assertDatabaseHas('club_acc_candidate_events', ['candidate_id' => $candidate->id, 'type' => 'ballot']);

        $rejected = $this->candidate(['stage' => CandidateStage::Proposed, 'proposed_at' => '2026-09-01', 'email' => 'other@example.com']);
        $result = $service->recordBallot($rejected, $this->admin, Carbon::parse('2026-09-15'), 'not_elected');
        $this->assertSame(CandidateStage::Rejected, $result->stage);
        $this->assertSame('failed_ballot', $result->outcome_reason);
    }

    public function test_a_ballot_cannot_be_recorded_before_the_proposal_date_or_recorded_twice(): void
    {
        $candidate = $this->candidate(['stage' => CandidateStage::Proposed, 'proposed_at' => '2026-09-10']);
        $service = $this->service();

        try {
            $service->recordBallot($candidate, $this->admin, Carbon::parse('2026-09-01'), 'elected');
            $this->fail('Ballot cannot be before the proposal.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('cannot be before the ballot', $e->getMessage().' cannot be before the ballot');
        }

        $service->recordBallot($candidate, $this->admin, Carbon::parse('2026-09-15'), 'elected');

        try {
            $service->recordBallot($candidate->fresh(), $this->admin, Carbon::parse('2026-09-20'), 'elected');
            $this->fail('A ballot can only be recorded once, while proposed.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('can only be recorded', $e->getMessage());
        }
    }

    public function test_initiation_must_be_on_or_after_the_ballot_and_within_the_year(): void
    {
        $candidate = $this->candidate(['stage' => CandidateStage::Accepted, 'ballot_at' => '2026-01-01', 'ballot_result' => 'elected', 'initiate_by' => '2027-01-01']);
        $service = $this->service();

        try {
            $service->transitionStage($candidate, CandidateStage::Initiated, ['initiation_date' => '2025-12-01']);
            $this->fail('Cannot initiate before the ballot.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('before the ballot', $e->getMessage());
        }

        try {
            $service->transitionStage($candidate, CandidateStage::Initiated, ['initiation_date' => '2027-02-01']);
            $this->fail('Cannot initiate after the year has passed.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('proposed again', $e->getMessage());
        }

        $updated = $service->transitionStage($candidate, CandidateStage::Initiated, ['initiation_date' => '2026-06-01']);
        $this->assertSame(CandidateStage::Initiated, $updated->stage);
        $this->assertNotNull($updated->converted_member_id);
    }

    public function test_candidate_initiation_conversion_instantiates_member(): void
    {
        $candidate = $this->candidate([
            'first_name' => 'George', 'last_name' => 'Sterling', 'email' => 'george@example.com', 'phone' => '07711223344', 'address' => '44 Broad Street', 'postcode' => 'OX2 6NN',
            'stage' => CandidateStage::Accepted, 'ballot_at' => '2026-09-01', 'ballot_result' => 'elected', 'initiate_by' => '2027-09-01',
            'proposer_member_id' => $this->proposer->id, 'seconder_member_id' => $this->seconder->id,
        ]);

        $member = $this->service()->convertCandidateToMember($candidate, Carbon::parse('2026-09-15'));

        $this->assertInstanceOf(Member::class, $member);
        $this->assertSame($this->club->id, $member->club_id);
        $this->assertSame('George', $member->first_name);
        $this->assertSame('Bro', $member->masonic_rank);
        $this->assertSame(MembershipStatus::Active, $member->membership_status);
        $this->assertSame(LodgeOffice::Member, $member->current_office);
        $this->assertSame('2026-09-15', $member->date_of_initiation->format('Y-m-d'));

        $candidate->refresh();
        $this->assertSame(CandidateStage::Initiated, $candidate->stage);
        $this->assertSame($member->id, $candidate->converted_member_id);
    }

    public function test_rejecting_and_closing_need_a_reason_and_a_note_and_are_recorded(): void
    {
        $candidate = $this->candidate();
        $service = $this->service();

        try {
            $service->reject($candidate, $this->admin, 'bogus_code', 'A note here.');
            $this->fail('An unknown reason code should be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Choose a reason', $e->getMessage());
        }

        try {
            $service->reject($candidate, $this->admin, 'not_suitable', 'no');
            $this->fail('A too-short note should be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('short note', $e->getMessage());
        }

        $updated = $service->reject($candidate, $this->admin, 'not_suitable', 'Not a good fit after the phone call.');
        $this->assertSame(CandidateStage::Rejected, $updated->stage);
        $this->assertSame('not_suitable', $updated->outcome_reason);
        $this->assertSame('enquiry', $updated->outcome_from_stage);
        $this->assertDatabaseHas('club_acc_candidate_events', ['candidate_id' => $candidate->id, 'type' => 'reject']);

        $closed = $this->candidate(['email' => 'x@example.com']);
        $service->close($closed, $this->admin, 'withdrew', 'Candidate said timing was not right.');
        $this->assertSame(CandidateStage::Withdrawn, $closed->fresh()->stage);
        $this->assertSame('closed', $closed->fresh()->outcome);

        try {
            $service->reject($updated->fresh(), $this->admin, 'not_suitable', 'Already rejected.');
            $this->fail('Cannot reject an already-ended enquiry.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('already ended', $e->getMessage());
        }
    }

    public function test_hold_pauses_without_changing_stage_and_can_be_resumed(): void
    {
        $candidate = $this->candidate();
        $service = $this->service();

        $service->hold($candidate, $this->admin, Carbon::parse('2026-10-01'), 'Waiting for a house move.');
        $candidate->refresh();
        $this->assertTrue($candidate->isOnHold());
        $this->assertSame(CandidateStage::Enquiry, $candidate->stage);

        $service->resume($candidate, $this->admin);
        $this->assertFalse($candidate->fresh()->isOnHold());
    }

    public function test_only_an_owner_or_admin_can_reopen_and_it_goes_back_to_where_it_left_off(): void
    {
        $candidate = $this->candidate(['stage' => CandidateStage::LodgeCommittee]);
        $service = $this->service();
        $service->reject($candidate, $this->admin, 'not_suitable', 'Committee had concerns about availability.');

        try {
            $service->reopen($candidate->fresh(), $this->treasurer, 'Changed their mind');
            $this->fail('Treasurer should not be able to reopen.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('owner or admin', $e->getMessage());
        }

        $reopened = $service->reopen($candidate->fresh(), $this->admin, 'Circumstances have changed.');
        $this->assertSame(CandidateStage::LodgeCommittee, $reopened->stage);
        $this->assertNull($reopened->outcome);
    }

    public function test_the_history_of_stage_changes_and_outcomes_cannot_be_edited_or_deleted(): void
    {
        $candidate = $this->candidate();
        $this->service()->transitionStage($candidate, CandidateStage::FirstInterview);
        $event = CandidateEvent::where('candidate_id', $candidate->id)->where('type', 'stage_change')->sole();

        $this->expectException(LogicException::class);
        $event->update(['summary' => 'Tampered']);
    }

    public function test_a_plain_note_can_be_added_and_removed_but_a_stage_change_cannot(): void
    {
        $candidate = $this->candidate();
        $note = $this->service()->addNote($candidate, $this->admin, 'call', 'Phoned, left a voicemail.');
        $this->assertTrue($note->delete());

        $this->service()->transitionStage($candidate, CandidateStage::FirstInterview);
        $stageEvent = CandidateEvent::where('candidate_id', $candidate->id)->where('type', 'stage_change')->sole();

        $this->expectException(LogicException::class);
        $stageEvent->delete();
    }

    public function test_candidate_pipeline_board_and_add_enquiry(): void
    {
        $candidate = $this->candidate(['first_name' => 'Oliver', 'last_name' => 'Twist']);

        $this->actingAs($this->admin);

        Livewire::test(CandidatePipeline::class, ['clubSlug' => $this->club->slug])
            ->assertStatus(200)
            ->assertSee('Candidate Pipeline', false)
            ->assertSee('Oliver Twist')
            ->set('first_name', 'Nancy')
            ->set('last_name', 'Sikes')
            ->set('email', 'nancy@example.com')
            ->call('saveCandidate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('club_acc_candidates', ['first_name' => 'Nancy', 'last_name' => 'Sikes', 'stage' => 'enquiry']);
    }

    public function test_candidate_detail_page_edits_details_adds_notes_and_moves_the_candidate_on(): void
    {
        $candidate = $this->candidate();
        $this->actingAs($this->admin);

        Livewire::test(CandidateDetail::class, ['clubSlug' => $this->club->slug, 'candidateId' => $candidate->id])
            ->assertStatus(200)
            ->assertSee('Edward Mason')
            ->set('occupation', 'Solicitor')
            ->call('saveDetails')
            ->assertHasNoErrors()
            ->set('note_summary', 'Had a good chat on the phone')
            ->call('addNote')
            ->assertHasNoErrors()
            ->call('advance', $candidate->id)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('club_acc_candidates', ['id' => $candidate->id, 'occupation' => 'Solicitor', 'stage' => 'first_interview']);
        $this->assertDatabaseHas('club_acc_candidate_events', ['candidate_id' => $candidate->id, 'summary' => 'Had a good chat on the phone']);
    }

    public function test_form_p_signature_requests_are_emailed_to_the_proposer_and_seconder_and_can_be_resent(): void
    {
        Mail::fake();
        $candidate = $this->candidate(['proposer_member_id' => $this->proposer->id, 'seconder_member_id' => $this->seconder->id]);
        $this->actingAs($this->admin);

        Livewire::test(CandidateDetail::class, ['clubSlug' => $this->club->slug, 'candidateId' => $candidate->id])
            ->call('openFormPModal', $candidate->id)
            ->call('requestFormPSignatures')
            ->assertHasNoErrors();

        $this->assertEquals(2, SignatureRequest::where('signable_id', $candidate->id)->count());
        Mail::assertQueued(SignatureRequestMail::class, 2);

        $proposerRequest = SignatureRequest::where('signable_id', $candidate->id)->where('purpose', 'form_p_proposer')->sole();

        Livewire::test(CandidateDetail::class, ['clubSlug' => $this->club->slug, 'candidateId' => $candidate->id])
            ->call('openFormPModal', $candidate->id)
            ->call('resendFormPSignature', 'form_p_proposer')
            ->assertHasNoErrors();

        Mail::assertQueued(SignatureRequestMail::class, 3);
        $this->assertEquals(1, SignatureRequest::where('signable_id', $candidate->id)->where('purpose', 'form_p_proposer')->count());
        $this->assertNotEquals($proposerRequest->getAttribute('token_hash'), $proposerRequest->fresh()->getAttribute('token_hash'));
    }

    public function test_requesting_form_p_signatures_shows_who_will_be_emailed_before_sending(): void
    {
        Mail::fake();
        $candidate = $this->candidate(['proposer_member_id' => $this->proposer->id, 'seconder_member_id' => $this->seconder->id]);
        $this->actingAs($this->admin);

        $component = Livewire::test(CandidateDetail::class, ['clubSlug' => $this->club->slug, 'candidateId' => $candidate->id])
            ->call('openFormPModal', $candidate->id)
            ->assertSet('showFormPSignatureConfirm', false)
            ->call('openFormPSignatureConfirm')
            ->assertSet('showFormPSignatureConfirm', true);

        $candidates = $component->instance()->formPSignatureCandidates();
        $this->assertEquals('John Doe', $candidates['Proposer']['name']);
        $this->assertEquals('proposer@example.com', $candidates['Proposer']['email']);
        $this->assertEquals('James Smith', $candidates['Seconder']['name']);

        $component->call('requestFormPSignatures')->assertSet('showFormPSignatureConfirm', false);
    }

    public function test_a_member_without_permission_and_another_clubs_admin_cannot_reach_a_candidate(): void
    {
        $candidate = $this->candidate();

        $this->actingAs($this->member)->get(route('admin.club_acc.candidates.index', ['clubSlug' => $this->club->slug]))->assertForbidden();

        $outsider = $this->join($this->otherClub, 'admin');
        $this->actingAs($outsider)->get(route('admin.club_acc.candidates.show', ['clubSlug' => $this->club->slug, 'candidateId' => $candidate->id]))->assertForbidden();
    }
}
