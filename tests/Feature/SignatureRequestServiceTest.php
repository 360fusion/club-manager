<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\CandidateStage;
use App\Domains\ClubAccounting\Models\Candidate;
use App\Domains\ClubAccounting\Models\Member;
use App\Enums\SignatureRequestStatus;
use App\Mail\SignatureRequestMail;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\Signatures\SignatureRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\TestCase;

class SignatureRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'lodge', 'available_modules' => ['accounting', 'meetings', 'members']]);
        $this->club = Club::create(['name' => 'Lodge of Fraternity', 'slug' => 'lodge-of-fraternity', 'club_type_id' => $clubType->id, 'status' => 'active']);
        $this->admin = User::factory()->create();
        $this->admin->clubs()->attach($this->club->id, ['role' => 'admin', 'status' => 'active']);
    }

    private function service(): SignatureRequestService
    {
        return app(SignatureRequestService::class);
    }

    private function candidate(): Candidate
    {
        $proposer = Member::create(['club_id' => $this->club->id, 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'proposer@example.com']);
        $seconder = Member::create(['club_id' => $this->club->id, 'first_name' => 'James', 'last_name' => 'Smith', 'email' => 'seconder@example.com']);

        return Candidate::create([
            'club_id' => $this->club->id,
            'first_name' => 'Edward',
            'last_name' => 'Mason',
            'email' => 'edward@example.com',
            'stage' => CandidateStage::Proposed,
            'stage_entered_at' => now(),
            'proposer_member_id' => $proposer->id,
            'seconder_member_id' => $seconder->id,
        ]);
    }

    public function test_requesting_a_signature_emails_the_signer_a_private_link(): void
    {
        Mail::fake();

        $candidate = $this->candidate();
        $proposer = Member::find($candidate->proposer_member_id);

        $request = $this->service()->request($candidate, 'form_p_proposer', $proposer, $proposer->full_name, $proposer->email, $this->admin);

        $this->assertNotNull($request->plainToken);
        $this->assertEquals(SignatureRequestStatus::Pending, $request->status);

        Mail::assertQueued(SignatureRequestMail::class, fn ($mail) => $mail->hasTo($proposer->email) && str_contains($mail->signUrl, $request->plainToken));
    }

    public function test_requesting_again_while_pending_reissues_the_same_row(): void
    {
        Mail::fake();
        $candidate = $this->candidate();
        $proposer = Member::find($candidate->proposer_member_id);

        $first = $this->service()->request($candidate, 'form_p_proposer', $proposer, $proposer->full_name, $proposer->email, $this->admin);
        $second = $this->service()->request($candidate, 'form_p_proposer', $proposer, $proposer->full_name, $proposer->email, $this->admin);

        $this->assertEquals($first->id, $second->id);
        $this->assertNotEquals($first->plainToken, $second->plainToken);
        $this->assertEquals(1, SignatureRequest::count());
    }

    public function test_signing_with_a_typed_name_records_consent_details(): void
    {
        $candidate = $this->candidate();
        $proposer = Member::find($candidate->proposer_member_id);
        $request = $this->service()->request($candidate, 'form_p_proposer', $proposer, $proposer->full_name, $proposer->email, $this->admin);

        $signed = $this->service()->sign($request, 'typed', ['typed_name' => 'John Doe'], '203.0.113.5', 'TestAgent/1.0');

        $this->assertEquals(SignatureRequestStatus::Signed, $signed->status);
        $this->assertEquals('John Doe', $signed->typed_name);
        $this->assertEquals('203.0.113.5', $signed->consent_ip);
        $this->assertNotNull($signed->signed_at);
        $this->assertNotNull($signed->document_hash);
    }

    public function test_signing_by_drawing_stores_the_image(): void
    {
        $candidate = $this->candidate();
        $proposer = Member::find($candidate->proposer_member_id);
        $request = $this->service()->request($candidate, 'form_p_proposer', $proposer, $proposer->full_name, $proposer->email, $this->admin);

        $image = UploadedFile::fake()->image('signature.png', 400, 150);

        $signed = $this->service()->sign($request, 'drawn', ['image' => $image], '203.0.113.5', 'TestAgent/1.0');

        $this->assertEquals(SignatureRequestStatus::Signed, $signed->status);
        $this->assertCount(1, $signed->getMedia('signature'));
    }

    public function test_signing_an_already_signed_request_is_rejected(): void
    {
        $candidate = $this->candidate();
        $proposer = Member::find($candidate->proposer_member_id);
        $request = $this->service()->request($candidate, 'form_p_proposer', $proposer, $proposer->full_name, $proposer->email, $this->admin);
        $this->service()->sign($request, 'typed', ['typed_name' => 'John Doe'], '1.1.1.1', 'Agent');

        $this->expectException(InvalidArgumentException::class);
        $this->service()->sign($request->fresh(), 'typed', ['typed_name' => 'John Doe'], '1.1.1.1', 'Agent');
    }

    public function test_an_expired_request_cannot_be_signed(): void
    {
        $candidate = $this->candidate();
        $proposer = Member::find($candidate->proposer_member_id);
        $request = $this->service()->request($candidate, 'form_p_proposer', $proposer, $proposer->full_name, $proposer->email, $this->admin, now()->subDay());

        $this->expectException(InvalidArgumentException::class);
        $this->service()->sign($request, 'typed', ['typed_name' => 'John Doe'], '1.1.1.1', 'Agent');
    }

    public function test_finding_by_token_flips_an_overdue_pending_request_to_expired(): void
    {
        $candidate = $this->candidate();
        $proposer = Member::find($candidate->proposer_member_id);
        $request = $this->service()->request($candidate, 'form_p_proposer', $proposer, $proposer->full_name, $proposer->email, $this->admin, now()->subDay());

        $found = $this->service()->findByToken($request->plainToken);

        $this->assertEquals(SignatureRequestStatus::Expired, $found->status);
    }

    public function test_form_p_is_marked_signed_only_once_both_proposer_and_seconder_have_signed(): void
    {
        Mail::fake();
        $candidate = $this->candidate();
        $proposer = Member::find($candidate->proposer_member_id);
        $seconder = Member::find($candidate->seconder_member_id);

        $proposerRequest = $this->service()->request($candidate, 'form_p_proposer', $proposer, $proposer->full_name, $proposer->email, $this->admin);
        $seconderRequest = $this->service()->request($candidate, 'form_p_seconder', $seconder, $seconder->full_name, $seconder->email, $this->admin);

        $this->service()->sign($proposerRequest, 'typed', ['typed_name' => $proposer->full_name], '1.1.1.1', 'Agent');
        $this->assertNull($candidate->fresh()->form_p_signed_at);

        $this->service()->sign($seconderRequest, 'typed', ['typed_name' => $seconder->full_name], '1.1.1.1', 'Agent');
        $this->assertNotNull($candidate->fresh()->form_p_signed_at);
    }

    public function test_year_audit_is_signed_off_only_once_both_auditors_have_signed(): void
    {
        Mail::fake();
        $auditorOne = User::factory()->create(['name' => 'Auditor One']);
        $auditorTwo = User::factory()->create(['name' => 'Auditor Two']);
        $auditorOne->clubs()->attach($this->club->id, ['role' => 'member', 'status' => 'active']);
        $auditorTwo->clubs()->attach($this->club->id, ['role' => 'member', 'status' => 'active']);

        $audit = app(AccountingService::class)->requestYearAudit($this->club, 2025, $auditorOne, $auditorTwo, 'Reviewed');
        $this->assertNull($audit->signed_off_at);

        $r1 = $this->service()->request($audit, 'year_audit_auditor_one', $auditorOne, $auditorOne->name, $auditorOne->email, $this->admin);
        $r2 = $this->service()->request($audit, 'year_audit_auditor_two', $auditorTwo, $auditorTwo->name, $auditorTwo->email, $this->admin);

        $this->service()->sign($r1, 'typed', ['typed_name' => $auditorOne->name], '1.1.1.1', 'Agent');
        $this->assertNull($audit->fresh()->signed_off_at);

        $this->service()->sign($r2, 'typed', ['typed_name' => $auditorTwo->name], '1.1.1.1', 'Agent');
        $this->assertNotNull($audit->fresh()->signed_off_at);
    }
}
