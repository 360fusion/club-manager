<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\SignatureRequest;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\Signatures\SignatureRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberSignatureControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;

    protected User $auditorOne;

    protected User $auditorTwo;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'lodge', 'available_modules' => ['accounting']]);
        $this->club = Club::create(['name' => 'Lodge of Fraternity', 'slug' => 'lodge-of-fraternity', 'club_type_id' => $clubType->id, 'status' => 'active']);

        $this->auditorOne = User::factory()->create();
        $this->auditorTwo = User::factory()->create();
        $this->auditorOne->clubs()->attach($this->club->id, ['role' => 'member', 'status' => 'active']);
        $this->auditorTwo->clubs()->attach($this->club->id, ['role' => 'member', 'status' => 'active']);
    }

    private function pendingRequest(): SignatureRequest
    {
        Mail::fake();
        $audit = app(AccountingService::class)->requestYearAudit($this->club, 2025, $this->auditorOne, $this->auditorTwo, null);

        return app(SignatureRequestService::class)->request($audit, 'year_audit_auditor_one', $this->auditorOne, $this->auditorOne->name, $this->auditorOne->email, $this->auditorOne);
    }

    public function test_the_signer_can_see_and_sign_their_own_request(): void
    {
        $request = $this->pendingRequest();

        $this->actingAs($this->auditorOne)->get(route('member.signatures.show', ['slug' => $this->club->slug, 'id' => $request->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Member/SignDocument')->where('status', 'pending'));

        $this->actingAs($this->auditorOne)->post(route('member.signatures.store', ['slug' => $this->club->slug, 'id' => $request->id]), [
            'method' => 'typed',
            'typed_name' => $this->auditorOne->name,
            'consent' => true,
        ])->assertRedirect(route('member.signatures.show', ['slug' => $this->club->slug, 'id' => $request->id]));

        $this->assertEquals('signed', $request->fresh()->status->value);
    }

    public function test_someone_else_cannot_see_or_sign_another_persons_request(): void
    {
        $request = $this->pendingRequest();
        $outsider = User::factory()->create();
        $outsider->clubs()->attach($this->club->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($outsider)->get(route('member.signatures.show', ['slug' => $this->club->slug, 'id' => $request->id]))->assertForbidden();

        $this->actingAs($outsider)->post(route('member.signatures.store', ['slug' => $this->club->slug, 'id' => $request->id]), [
            'method' => 'typed',
            'typed_name' => 'Someone Else',
            'consent' => true,
        ])->assertForbidden();

        $this->assertEquals('pending', $request->fresh()->status->value);
    }

    public function test_the_other_auditor_cannot_sign_the_first_auditors_request(): void
    {
        $request = $this->pendingRequest();

        $this->actingAs($this->auditorTwo)->get(route('member.signatures.show', ['slug' => $this->club->slug, 'id' => $request->id]))->assertForbidden();
    }

    public function test_signing_by_drawing_uploads_the_image(): void
    {
        $request = $this->pendingRequest();

        $this->actingAs($this->auditorOne)->post(route('member.signatures.store', ['slug' => $this->club->slug, 'id' => $request->id]), [
            'method' => 'drawn',
            'image' => UploadedFile::fake()->image('signature.png', 400, 150),
            'consent' => true,
        ])->assertRedirect();

        $this->assertCount(1, $request->fresh()->getMedia('signature'));
    }

    public function test_declining_records_the_reason(): void
    {
        $request = $this->pendingRequest();

        $this->actingAs($this->auditorOne)->post(route('member.signatures.decline', ['slug' => $this->club->slug, 'id' => $request->id]), ['reason' => 'Not ready.'])
            ->assertRedirect(route('member.signatures.show', ['slug' => $this->club->slug, 'id' => $request->id]));

        $this->assertEquals('declined', $request->fresh()->status->value);
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        $request = $this->pendingRequest();

        $this->get(route('member.signatures.show', ['slug' => $this->club->slug, 'id' => $request->id]))->assertRedirect('/login');
    }

    public function test_the_signer_can_download_a_pdf_once_signed(): void
    {
        $request = $this->pendingRequest();
        app(SignatureRequestService::class)->sign($request, 'typed', ['typed_name' => $this->auditorOne->name], '1.1.1.1', 'Agent');

        $this->actingAs($this->auditorOne)->get(route('member.signatures.pdf', ['slug' => $this->club->slug, 'id' => $request->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_pdf_is_not_available_before_signing(): void
    {
        $request = $this->pendingRequest();

        $this->actingAs($this->auditorOne)->get(route('member.signatures.pdf', ['slug' => $this->club->slug, 'id' => $request->id]))->assertNotFound();
    }

    public function test_signed_documents_are_listed_in_the_members_area(): void
    {
        $request = $this->pendingRequest();
        app(SignatureRequestService::class)->sign($request, 'typed', ['typed_name' => $this->auditorOne->name], '1.1.1.1', 'Agent');

        $this->actingAs($this->auditorOne)->get(route('members.signed_documents'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Members/SignedDocuments')
                ->has('documents.data', 1)
                ->where('documents.data.0.club.slug', $this->club->slug));

        $this->actingAs($this->auditorTwo)->get(route('members.signed_documents'))
            ->assertInertia(fn (Assert $page) => $page->has('documents.data', 0));
    }
}
