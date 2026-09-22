<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\CandidateStage;
use App\Domains\ClubAccounting\Models\Candidate;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use App\Services\Signatures\SignatureRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SignatureControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;

    protected User $admin;

    protected Candidate $candidate;

    protected Member $proposer;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'lodge', 'available_modules' => ['accounting', 'meetings', 'members']]);
        $this->club = Club::create(['name' => 'Lodge of Fraternity', 'slug' => 'lodge-of-fraternity', 'club_type_id' => $clubType->id, 'status' => 'active']);
        $this->admin = User::factory()->create();
        $this->admin->clubs()->attach($this->club->id, ['role' => 'admin', 'status' => 'active']);

        $this->proposer = Member::create(['club_id' => $this->club->id, 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'proposer@example.com']);
        $seconder = Member::create(['club_id' => $this->club->id, 'first_name' => 'James', 'last_name' => 'Smith', 'email' => 'seconder@example.com']);

        $this->candidate = Candidate::create([
            'club_id' => $this->club->id,
            'first_name' => 'Edward',
            'last_name' => 'Mason',
            'email' => 'edward@example.com',
            'stage' => CandidateStage::Proposed,
            'stage_entered_at' => now(),
            'proposer_member_id' => $this->proposer->id,
            'seconder_member_id' => $seconder->id,
        ]);
    }

    public function test_a_valid_token_shows_what_is_being_signed(): void
    {
        Mail::fake();
        $request = app(SignatureRequestService::class)->request($this->candidate, 'form_p_proposer', $this->proposer, $this->proposer->full_name, $this->proposer->email, $this->admin);

        $this->get(route('sign.show', ['token' => $request->plainToken]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Public/SignDocument')
                ->where('found', true)
                ->where('status', 'pending')
                ->where('signerName', 'John Doe')
                ->where('documentLabel', 'Form P — proposing Edward Mason for membership'));
    }

    public function test_an_invalid_token_reports_not_found(): void
    {
        $this->get(route('sign.show', ['token' => 'not-a-real-token-1234567890']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('found', false));
    }

    public function test_signing_with_a_typed_name_completes_the_request(): void
    {
        Mail::fake();
        $request = app(SignatureRequestService::class)->request($this->candidate, 'form_p_proposer', $this->proposer, $this->proposer->full_name, $this->proposer->email, $this->admin);

        $this->post(route('sign.store', ['token' => $request->plainToken]), [
            'method' => 'typed',
            'typed_name' => 'John Doe',
            'consent' => true,
        ])->assertRedirect(route('sign.show', ['token' => $request->plainToken]));

        $this->assertEquals('signed', $request->fresh()->status->value);
        $this->assertEquals('John Doe', $request->fresh()->typed_name);
    }

    public function test_signing_without_consent_is_rejected(): void
    {
        Mail::fake();
        $request = app(SignatureRequestService::class)->request($this->candidate, 'form_p_proposer', $this->proposer, $this->proposer->full_name, $this->proposer->email, $this->admin);

        $this->post(route('sign.store', ['token' => $request->plainToken]), [
            'method' => 'typed',
            'typed_name' => 'John Doe',
        ])->assertSessionHasErrors('consent');

        $this->assertEquals('pending', $request->fresh()->status->value);
    }

    public function test_signing_by_drawing_uploads_the_image(): void
    {
        Mail::fake();
        $request = app(SignatureRequestService::class)->request($this->candidate, 'form_p_proposer', $this->proposer, $this->proposer->full_name, $this->proposer->email, $this->admin);

        $this->post(route('sign.store', ['token' => $request->plainToken]), [
            'method' => 'drawn',
            'image' => UploadedFile::fake()->image('signature.png', 400, 150),
            'consent' => true,
        ])->assertRedirect(route('sign.show', ['token' => $request->plainToken]));

        $this->assertEquals('signed', $request->fresh()->status->value);
        $this->assertCount(1, $request->fresh()->getMedia('signature'));
    }

    public function test_declining_records_the_reason(): void
    {
        Mail::fake();
        $request = app(SignatureRequestService::class)->request($this->candidate, 'form_p_proposer', $this->proposer, $this->proposer->full_name, $this->proposer->email, $this->admin);

        $this->post(route('sign.decline', ['token' => $request->plainToken]), ['reason' => 'Not able to verify this candidate.'])
            ->assertRedirect(route('sign.show', ['token' => $request->plainToken]));

        $this->assertEquals('declined', $request->fresh()->status->value);
        $this->assertEquals('Not able to verify this candidate.', $request->fresh()->notes);
    }
}
