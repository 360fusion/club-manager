<?php

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\MeetingFinancialReturn;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Meeting;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MeetingFinancialReturnTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;
    protected Meeting $meeting;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::firstOrCreate(
            ['code' => 'masonic_lodge'],
            ['name' => 'Masonic Lodge']
        );

        $this->club = Club::create([
            'club_type_id' => $type->id,
            'name' => 'Lodge of Fraternity No. 4321',
            'slug' => 'lodge-of-fraternity',
            'description' => 'Test Club',
        ]);

        $this->user = User::create([
            'name' => 'Secretary User',
            'email' => 'secretary@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->club->users()->attach($this->user->id, ['role' => 'secretary']);

        $this->meeting = Meeting::create([
            'club_id' => $this->club->id,
            'title' => 'Regular Meeting No. 450',
            'meeting_date' => '2026-09-15',
            'starts_at' => '18:30',
            'venue' => 'Masonic Hall',
            'dress_code' => 'Dark Suit',
            'dining_cost_member' => 35.00,
            'dining_cost_guest' => 35.00,
            'status' => 'published',
        ]);

        $service = app(AccountingService::class);
        $service->seedDefaultAccounts($this->club);
    }

    public function test_can_post_meeting_financial_return_via_service(): void
    {
        $service = app(AccountingService::class);

        $returnData = [
            'return_date' => '2026-09-15',
            'dining_fee_per_head' => 35.00,
            'paid_diners_count' => 35,
            'waived_diners_count' => 5,
            'waived_reason' => 'Official Guests',
            'kitchen_cost_per_head' => 28.00,
            'kitchen_vendor_name' => 'Masonic Hall Caterers',
            'raffle_amount' => 600.00,
            'alms_amount' => 120.00,
            'donations_amount' => 150.00,
            'bequest_amount' => 0.00,
            'notes' => 'Great meeting and fundraising',
        ];

        $return = $service->postMeetingFinancialReturn($this->club, $this->meeting, $returnData);

        $this->assertDatabaseHas('meeting_financial_returns', [
            'club_id' => $this->club->id,
            'meeting_id' => $this->meeting->id,
            'paid_diners_count' => 35,
            'waived_diners_count' => 5,
            'total_dining_revenue' => 1225.00, // 35 * 35
            'total_kitchen_bill' => 1120.00,   // (35+5) * 28
            'net_dining_surplus' => 105.00,     // 1225 - 1120
            'total_charity_collected' => 870.00, // 600 + 120 + 150
            'net_bank_deposit' => 2095.00,      // 1225 + 870
        ]);

        // Kitchen Vendor Bill created in Accounts Payable
        $this->assertDatabaseHas('accounting_bills', [
            'club_id' => $this->club->id,
            'vendor_name' => 'Masonic Hall Caterers',
            'amount' => 1120.00,
        ]);

        // Journal Entry posted
        $this->assertDatabaseHas('accounting_journal_entries', [
            'club_id' => $this->club->id,
            'source_type' => 'MeetingReturn',
            'source_id' => $this->meeting->id,
        ]);
    }

    public function test_meeting_financial_return_http_endpoint(): void
    {
        $this->actingAs($this->user);

        $response = $this->post(route('admin.meetings.financial_return.store', [
            'clubSlug' => $this->club->slug,
            'id' => $this->meeting->id,
        ]), [
            'return_date' => '2026-09-15',
            'dining_fee_per_head' => 35.00,
            'paid_diners_count' => 40,
            'waived_diners_count' => 2,
            'waived_reason' => 'Visiting Speaker',
            'kitchen_cost_per_head' => 25.00,
            'kitchen_vendor_name' => 'Oxford Banquet Catering',
            'raffle_amount' => 500.00,
            'alms_amount' => 100.00,
            'donations_amount' => 50.00,
            'bequest_amount' => 0.00,
            'notes' => 'HTTP test return',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('meeting_financial_returns', [
            'club_id' => $this->club->id,
            'meeting_id' => $this->meeting->id,
            'paid_diners_count' => 40,
            'waived_diners_count' => 2,
            'total_dining_revenue' => 1400.00, // 40 * 35
            'total_kitchen_bill' => 1050.00,   // 42 * 25
            'net_dining_surplus' => 350.00,
            'net_bank_deposit' => 2050.00,      // 1400 + 650
        ]);
    }

    public function test_comparative_statement_report_generation(): void
    {
        $service = app(AccountingService::class);

        $service->postMeetingFinancialReturn($this->club, $this->meeting, [
            'return_date' => '2026-09-15',
            'dining_fee_per_head' => 35.00,
            'paid_diners_count' => 30,
            'waived_diners_count' => 0,
            'kitchen_cost_per_head' => 25.00,
            'kitchen_vendor_name' => 'Caterer',
            'raffle_amount' => 7000.00,
            'alms_amount' => 1400.00,
            'donations_amount' => 1700.00,
            'bequest_amount' => 3750.00,
        ]);

        $report = $service->getComparativeIncomeExpenditureData($this->club);

        $this->assertEquals('2024 – 2025', $report['prior_year_label']);
        $this->assertEquals('2025 – 2026', $report['current_year_label']);
        $this->assertCount(6, $report['rows']);
        $this->assertGreaterThan(0, $report['current_totals']['income']);
        $this->assertGreaterThan(0, $report['current_balance_carried_forward']);
    }
}
