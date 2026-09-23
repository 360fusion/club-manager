<?php

namespace Tests\Feature;

use App\Enums\SignatureRequestStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingYearAudit;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\Signatures\SignatureRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Tests\TestCase;

class PeriodLockingTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;

    protected User $adminUser;

    protected AccountingService $accountingService;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'lodge',
            'available_modules' => ['accounting'],
        ]);

        $this->club = Club::create([
            'name' => 'Lodge of Unity',
            'slug' => 'lodge-of-unity',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create();
        $this->makeClubAdmin($this->adminUser, $this->club);

        $this->accountingService = app(AccountingService::class);
        $this->accountingService->seedDefaultAccounts($this->club);
    }

    public function test_posting_into_a_closed_year_is_blocked_for_a_normal_admin(): void
    {
        $this->accountingService->closeFinancialYear($this->club, 2025, $this->adminUser);
        $this->assertTrue($this->club->financialYearIsClosed(2025));

        $bankAcc = Account::where('club_id', $this->club->id)->where('code', '1000')->firstOrFail();
        $duesAcc = Account::where('club_id', $this->club->id)->where('code', '4000')->firstOrFail();

        $this->expectException(InvalidArgumentException::class);

        $this->accountingService->postJournalEntry($this->club, [
            'entry_date' => '2025-06-15',
            'description' => 'Late entry into closed year',
            'items' => [
                ['account_id' => $bankAcc->id, 'debit' => 50, 'credit' => 0],
                ['account_id' => $duesAcc->id, 'debit' => 0, 'credit' => 50],
            ],
        ]);
    }

    public function test_posting_into_an_open_year_still_works_after_a_different_year_is_closed(): void
    {
        $this->accountingService->closeFinancialYear($this->club, 2025, $this->adminUser);

        $bankAcc = Account::where('club_id', $this->club->id)->where('code', '1000')->firstOrFail();
        $duesAcc = Account::where('club_id', $this->club->id)->where('code', '4000')->firstOrFail();

        $entry = $this->accountingService->postJournalEntry($this->club, [
            'entry_date' => '2026-06-15',
            'description' => 'Current year entry',
            'items' => [
                ['account_id' => $bankAcc->id, 'debit' => 50, 'credit' => 0],
                ['account_id' => $duesAcc->id, 'debit' => 0, 'credit' => 50],
            ],
        ]);

        $this->assertTrue($entry->isBalanced());
    }

    public function test_reopening_a_year_allows_posting_again_and_is_super_admin_only(): void
    {
        $this->accountingService->closeFinancialYear($this->club, 2025, $this->adminUser);

        $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.financial_year.reopen', $this->club->slug), ['financial_year' => 2025])
            ->assertForbidden();

        $superAdmin = User::factory()->create(['is_super_admin' => true]);
        $this->makeClubAdmin($superAdmin, $this->club);

        $this->actingAs($superAdmin)
            ->post(route('admin.accounting.financial_year.reopen', $this->club->slug), ['financial_year' => 2025])
            ->assertRedirect();

        $this->assertFalse($this->club->fresh()->financialYearIsClosed(2025));
    }

    public function test_close_financial_year_route_is_forbidden_for_non_billing_roles(): void
    {
        $member = User::factory()->create();
        $member->clubs()->attach($this->club->id, ['role' => 'member']);

        $this->actingAs($member)
            ->post(route('admin.accounting.financial_year.close', $this->club->slug), ['financial_year' => 2025])
            ->assertForbidden();
    }

    public function test_two_different_elected_auditors_can_sign_off_a_financial_year(): void
    {
        $auditorOne = User::factory()->create();
        $auditorTwo = User::factory()->create();
        $auditorOne->clubs()->attach($this->club->id, ['role' => 'member']);
        $auditorTwo->clubs()->attach($this->club->id, ['role' => 'member']);

        $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.financial_year.audit', $this->club->slug), [
                'financial_year' => 2025,
                'auditor_one_user_id' => $auditorOne->id,
                'auditor_two_user_id' => $auditorTwo->id,
                'notes' => 'Books reviewed and found correct.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('accounting_year_audits', [
            'club_id' => $this->club->id,
            'financial_year' => 2025,
            'auditor_one_user_id' => $auditorOne->id,
            'auditor_two_user_id' => $auditorTwo->id,
        ]);
    }

    public function test_the_same_person_cannot_be_both_auditors(): void
    {
        $auditor = User::factory()->create();
        $auditor->clubs()->attach($this->club->id, ['role' => 'member']);

        $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.financial_year.audit', $this->club->slug), [
                'financial_year' => 2025,
                'auditor_one_user_id' => $auditor->id,
                'auditor_two_user_id' => $auditor->id,
            ])
            ->assertSessionHasErrors();
    }

    public function test_a_pending_auditor_signature_request_can_be_cancelled(): void
    {
        Mail::fake();
        $auditorOne = User::factory()->create();
        $auditorTwo = User::factory()->create();
        $auditorOne->clubs()->attach($this->club->id, ['role' => 'member']);
        $auditorTwo->clubs()->attach($this->club->id, ['role' => 'member']);

        $this->actingAs($this->adminUser)->post(route('admin.accounting.financial_year.audit', $this->club->slug), [
            'financial_year' => 2025,
            'auditor_one_user_id' => $auditorOne->id,
            'auditor_two_user_id' => $auditorTwo->id,
        ]);

        $this->actingAs($this->adminUser)
            ->post(route('admin.accounting.financial_year.audit.cancel', ['clubSlug' => $this->club->slug, 'purpose' => 'year_audit_auditor_one']), ['financial_year' => 2025])
            ->assertRedirect();

        $audit = AccountingYearAudit::where('club_id', $this->club->id)->where('financial_year', 2025)->sole();
        $this->assertEquals('cancelled', app(SignatureRequestService::class)->forSignable($audit)->where('purpose', 'year_audit_auditor_one')->sole()->status->value);
    }

    public function test_picking_a_different_auditor_does_nothing_until_the_current_one_is_cancelled(): void
    {
        Mail::fake();
        $auditorOne = User::factory()->create();
        $auditorTwo = User::factory()->create();
        $replacementAuditorOne = User::factory()->create();
        $auditorOne->clubs()->attach($this->club->id, ['role' => 'member']);
        $auditorTwo->clubs()->attach($this->club->id, ['role' => 'member']);
        $replacementAuditorOne->clubs()->attach($this->club->id, ['role' => 'member']);

        $this->actingAs($this->adminUser)->post(route('admin.accounting.financial_year.audit', $this->club->slug), [
            'financial_year' => 2025,
            'auditor_one_user_id' => $auditorOne->id,
            'auditor_two_user_id' => $auditorTwo->id,
        ]);

        $audit = AccountingYearAudit::where('club_id', $this->club->id)->where('financial_year', 2025)->sole();
        $signatures = app(SignatureRequestService::class);
        $auditorTwoRequest = $signatures->forSignable($audit)->where('purpose', 'year_audit_auditor_two')->sole();
        $signatures->sign($auditorTwoRequest, 'typed', ['typed_name' => $auditorTwo->name], '1.1.1.1', 'Agent');

        // Re-submitting with a different auditor one, while the original's request is still pending, changes nothing.
        $this->actingAs($this->adminUser)->post(route('admin.accounting.financial_year.audit', $this->club->slug), [
            'financial_year' => 2025,
            'auditor_one_user_id' => $replacementAuditorOne->id,
            'auditor_two_user_id' => $auditorTwo->id,
        ]);

        $this->assertEquals('signed', $auditorTwoRequest->fresh()->status->value, 'the already-signed auditor is left alone');
        $stillOriginal = $signatures->forSignable($audit)->where('purpose', 'year_audit_auditor_one')->where('status', SignatureRequestStatus::Pending)->sole();
        $this->assertEquals($auditorOne->name, $stillOriginal->signer_name, 'picking someone new does not reassign on its own');

        // Only after cancelling does resubmitting pick up the newly-selected person.
        $this->actingAs($this->adminUser)->post(route('admin.accounting.financial_year.audit.cancel', ['clubSlug' => $this->club->slug, 'purpose' => 'year_audit_auditor_one']), ['financial_year' => 2025]);

        $this->actingAs($this->adminUser)->post(route('admin.accounting.financial_year.audit', $this->club->slug), [
            'financial_year' => 2025,
            'auditor_one_user_id' => $replacementAuditorOne->id,
            'auditor_two_user_id' => $auditorTwo->id,
        ]);

        $current = $signatures->forSignable($audit)->where('purpose', 'year_audit_auditor_one')->where('status', SignatureRequestStatus::Pending)->sole();
        $this->assertEquals($replacementAuditorOne->name, $current->signer_name);
    }

    public function test_staff_can_download_the_signed_auditor_certificate(): void
    {
        Mail::fake();
        $auditorOne = User::factory()->create();
        $auditorTwo = User::factory()->create();
        $auditorOne->clubs()->attach($this->club->id, ['role' => 'member']);
        $auditorTwo->clubs()->attach($this->club->id, ['role' => 'member']);

        $audit = $this->accountingService->requestYearAudit($this->club, 2025, $auditorOne, $auditorTwo, null);
        $signatures = app(SignatureRequestService::class);
        $request = $signatures->request($audit, 'year_audit_auditor_one', $auditorOne, $auditorOne->name, $auditorOne->email, $this->adminUser);
        $signatures->sign($request, 'typed', ['typed_name' => $auditorOne->name], '1.1.1.1', 'Agent');

        $this->actingAs($this->adminUser)
            ->get(route('admin.accounting.signatures.pdf', ['clubSlug' => $this->club->slug, 'id' => $request->id]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_plain_member_cannot_download_a_signature_certificate(): void
    {
        Mail::fake();
        $auditorOne = User::factory()->create();
        $auditorTwo = User::factory()->create();
        $auditorOne->clubs()->attach($this->club->id, ['role' => 'member']);
        $auditorTwo->clubs()->attach($this->club->id, ['role' => 'member']);
        $plainMember = User::factory()->create();
        $plainMember->clubs()->attach($this->club->id, ['role' => 'member', 'status' => 'active']);

        $audit = $this->accountingService->requestYearAudit($this->club, 2025, $auditorOne, $auditorTwo, null);
        $signatures = app(SignatureRequestService::class);
        $request = $signatures->request($audit, 'year_audit_auditor_one', $auditorOne, $auditorOne->name, $auditorOne->email, $this->adminUser);
        $signatures->sign($request, 'typed', ['typed_name' => $auditorOne->name], '1.1.1.1', 'Agent');

        $this->actingAs($plainMember)
            ->get(route('admin.accounting.signatures.pdf', ['clubSlug' => $this->club->slug, 'id' => $request->id]))
            ->assertForbidden();
    }
}
