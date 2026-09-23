<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\CollectionType;
use App\Domains\ClubAccounting\Enums\GrantApprovalStatus;
use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Enums\MembershipStatus;
use App\Domains\ClubAccounting\Models\CharityCollection;
use App\Domains\ClubAccounting\Models\CharityGrant;
use App\Domains\ClubAccounting\Models\Member;
use App\Models\Accounting\Account;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\AnnualTreasurerReportService;
use App\Services\Signatures\SignatureRequestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AnnualTreasurerReportTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;

    protected User $adminUser;

    protected AccountingService $accountingService;

    protected AnnualTreasurerReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Masonic Lodge',
            'code' => 'lodge',
            'available_modules' => ['accounting'],
        ]);

        $this->club = Club::create([
            'name' => 'Lodge of Amity',
            'slug' => 'lodge-of-amity',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create();
        $this->makeClubAdmin($this->adminUser, $this->club);

        $this->accountingService = app(AccountingService::class);
        $this->reportService = app(AnnualTreasurerReportService::class);
        $this->accountingService->seedDefaultAccounts($this->club);
    }

    public function test_report_separates_general_fund_from_charity_fund_and_totals_charity_collections_and_grants(): void
    {
        Carbon::setTestNow('2026-06-01');

        $bankAcc = Account::where('club_id', $this->club->id)->where('code', '1000')->firstOrFail();
        $duesAcc = Account::where('club_id', $this->club->id)->where('code', '4000')->firstOrFail();
        $raffleAcc = Account::where('club_id', $this->club->id)->where('code', '4300')->firstOrFail();

        // General Fund income (dues).
        $this->accountingService->postJournalEntry($this->club, [
            'entry_date' => '2026-05-01',
            'description' => 'Dues',
            'items' => [
                ['account_id' => $bankAcc->id, 'debit' => 500, 'credit' => 0],
                ['account_id' => $duesAcc->id, 'debit' => 0, 'credit' => 500],
            ],
        ]);

        // Charity-coded ledger income (raffle), which should NOT appear in the General Fund total.
        $this->accountingService->postJournalEntry($this->club, [
            'entry_date' => '2026-05-10',
            'description' => 'Raffle',
            'items' => [
                ['account_id' => $bankAcc->id, 'debit' => 80, 'credit' => 0],
                ['account_id' => $raffleAcc->id, 'debit' => 0, 'credit' => 80],
            ],
        ]);

        $member = Member::create([
            'club_id' => $this->club->id,
            'first_name' => 'Arthur',
            'last_name' => 'Pendragon',
            'email' => 'arthur@example.com',
            'masonic_rank' => 'WBro',
            'membership_status' => MembershipStatus::Active,
            'current_office' => LodgeOffice::Member,
        ]);

        CharityCollection::create([
            'club_id' => $this->club->id,
            'collection_type' => CollectionType::AlmsPlate,
            'cash_amount' => 45.00,
            'cheque_amount' => 0,
            'donor_member_id' => $member->id,
        ]);

        CharityGrant::create([
            'club_id' => $this->club->id,
            'recipient_name' => 'Local Air Ambulance',
            'purpose' => 'Annual donation',
            'amount' => 100.00,
            'approval_status' => GrantApprovalStatus::Disbursed,
        ]);

        $report = $this->reportService->build($this->club, 2026);

        $this->assertEquals(500.00, $report['general_fund']['total_income']);
        $this->assertEmpty(array_filter($report['general_fund']['rows'], fn ($r) => $r['code'] === '4300'));

        $this->assertEquals(45.00, $report['charity_fund']['collections_total']);
        $this->assertEquals(100.00, $report['charity_fund']['grants_disbursed_total']);
        $this->assertFalse($report['is_closed']);

        Carbon::setTestNow();
    }

    public function test_report_reflects_closed_year_and_signed_off_audit(): void
    {
        $auditorOne = User::factory()->create(['name' => 'Auditor One']);
        $auditorTwo = User::factory()->create(['name' => 'Auditor Two']);
        $auditorOne->clubs()->attach($this->club->id, ['role' => 'member']);
        $auditorTwo->clubs()->attach($this->club->id, ['role' => 'member']);

        $this->accountingService->closeFinancialYear($this->club, 2026, $this->adminUser);
        $this->accountingService->signOffYearAudit($this->club, 2026, $auditorOne, $auditorTwo, 'All correct.');

        $report = $this->reportService->build($this->club, 2026);

        $this->assertTrue($report['is_closed']);
        $this->assertNotNull($report['audit_sign_off']);
        $this->assertEquals('Auditor One', $report['audit_sign_off']['auditor_one']);
        $this->assertEquals('Auditor Two', $report['audit_sign_off']['auditor_two']);
        $this->assertNull($report['signatures'], 'no actual electronic signatures exist for a directly-stamped sign-off');
        $this->assertEmpty($report['audit_log']);
    }

    public function test_report_embeds_the_actual_signatures_and_audit_log_once_both_auditors_have_signed_electronically(): void
    {
        Mail::fake();
        $auditorOne = User::factory()->create(['name' => 'Auditor One']);
        $auditorTwo = User::factory()->create(['name' => 'Auditor Two']);
        $auditorOne->clubs()->attach($this->club->id, ['role' => 'member']);
        $auditorTwo->clubs()->attach($this->club->id, ['role' => 'member']);

        $audit = $this->accountingService->requestYearAudit($this->club, 2026, $auditorOne, $auditorTwo, null);
        $signatures = app(SignatureRequestService::class);
        $r1 = $signatures->request($audit, 'year_audit_auditor_one', $auditorOne, $auditorOne->name, $auditorOne->email, $this->adminUser);
        $r2 = $signatures->request($audit, 'year_audit_auditor_two', $auditorTwo, $auditorTwo->name, $auditorTwo->email, $this->adminUser);
        $signatures->sign($r1, 'typed', ['typed_name' => 'Auditor One'], '1.1.1.1', 'Agent');
        $signatures->sign($r2, 'typed', ['typed_name' => 'Auditor Two'], '1.1.1.1', 'Agent');

        $report = $this->reportService->build($this->club, 2026);

        $this->assertNotNull($report['audit_sign_off']);
        $this->assertEquals(['method' => 'typed', 'value' => 'Auditor One'], $report['signatures']['auditor_one']);
        $this->assertEquals(['method' => 'typed', 'value' => 'Auditor Two'], $report['signatures']['auditor_two']);
        $this->assertCount(2, $report['audit_log']);
        $this->assertEquals('Auditor One', $report['audit_log'][0]['signer_name']);

        $this->actingAs($this->adminUser)
            ->get(route('admin.accounting.treasurer_report.export_pdf', ['clubSlug' => $this->club->slug, 'year' => 2026]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_drawn_signature_is_embedded_as_an_image_in_the_report_data(): void
    {
        Mail::fake();
        $auditorOne = User::factory()->create(['name' => 'Auditor One']);
        $auditorTwo = User::factory()->create(['name' => 'Auditor Two']);
        $auditorOne->clubs()->attach($this->club->id, ['role' => 'member']);
        $auditorTwo->clubs()->attach($this->club->id, ['role' => 'member']);

        $audit = $this->accountingService->requestYearAudit($this->club, 2026, $auditorOne, $auditorTwo, null);
        $signatures = app(SignatureRequestService::class);
        $r1 = $signatures->request($audit, 'year_audit_auditor_one', $auditorOne, $auditorOne->name, $auditorOne->email, $this->adminUser);
        $r2 = $signatures->request($audit, 'year_audit_auditor_two', $auditorTwo, $auditorTwo->name, $auditorTwo->email, $this->adminUser);
        $signatures->sign($r1, 'drawn', ['image' => UploadedFile::fake()->image('signature.png', 300, 100)], '1.1.1.1', 'Agent');
        $signatures->sign($r2, 'typed', ['typed_name' => 'Auditor Two'], '1.1.1.1', 'Agent');

        $report = $this->reportService->build($this->club, 2026);

        $this->assertEquals('drawn', $report['signatures']['auditor_one']['method']);
        $this->assertStringStartsWith('data:image/png;base64,', $report['signatures']['auditor_one']['value']);
    }

    public function test_csv_export_route_returns_a_downloadable_csv(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.accounting.treasurer_report.export_csv', ['clubSlug' => $this->club->slug, 'year' => 2026]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertSee("Annual Treasurer's Report", false);
    }

    public function test_csv_export_route_is_forbidden_for_non_billing_roles(): void
    {
        $member = User::factory()->create();
        $member->clubs()->attach($this->club->id, ['role' => 'member']);

        $this->actingAs($member)
            ->get(route('admin.accounting.treasurer_report.export_csv', $this->club->slug))
            ->assertForbidden();
    }
}
