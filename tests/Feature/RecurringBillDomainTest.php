<?php

namespace Tests\Feature;

use App\Models\Accounting\Bill;
use App\Models\Accounting\RecurringBillTemplate;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\User;
use App\Services\AccountingService;
use App\Services\RecurringBillService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringBillDomainTest extends TestCase
{
    use RefreshDatabase;

    protected Club $club;

    protected User $adminUser;

    protected RecurringBillService $recurringBillService;

    protected function setUp(): void
    {
        parent::setUp();

        $clubType = ClubType::create([
            'name' => 'Rowing Club',
            'code' => 'rowing',
            'available_modules' => ['accounting'],
        ]);

        $this->club = Club::create([
            'name' => 'Riverside Rowing Club',
            'slug' => 'riverside-rowing-club',
            'club_type_id' => $clubType->id,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create();
        $this->makeClubAdmin($this->adminUser, $this->club);

        app(AccountingService::class)->seedDefaultAccounts($this->club);
        $this->recurringBillService = app(RecurringBillService::class);
    }

    public function test_a_due_template_generates_a_bill_and_advances_to_the_next_period(): void
    {
        Carbon::setTestNow('2026-06-15');

        $template = RecurringBillTemplate::create([
            'club_id' => $this->club->id,
            'vendor_name' => 'Boathouse Insurance Ltd',
            'category' => 'Insurance',
            'amount' => 120.00,
            'frequency' => 'monthly',
            'next_run_date' => '2026-06-01',
            'is_active' => true,
        ]);

        $this->assertTrue($this->recurringBillService->isDue($template));

        $result = $this->recurringBillService->generateDueBills($this->club);
        $this->assertEquals(1, $result['created_count']);
        $this->assertEquals(120.00, $result['total_billed']);

        $this->assertDatabaseHas('accounting_bills', [
            'club_id' => $this->club->id,
            'vendor_name' => 'Boathouse Insurance Ltd',
            'amount' => 120.00,
        ]);

        $template->refresh();
        $this->assertEquals('2026-07-01', $template->next_run_date->format('Y-m-d'));
        $this->assertFalse($this->recurringBillService->isDue($template));

        Carbon::setTestNow();
    }

    public function test_running_generation_twice_on_the_same_day_does_not_double_bill(): void
    {
        Carbon::setTestNow('2026-06-15');

        RecurringBillTemplate::create([
            'club_id' => $this->club->id,
            'vendor_name' => 'Hall Hire Society',
            'category' => 'Venue',
            'amount' => 60.00,
            'frequency' => 'quarterly',
            'next_run_date' => '2026-06-01',
            'is_active' => true,
        ]);

        $this->recurringBillService->generateDueBills($this->club);
        $second = $this->recurringBillService->generateDueBills($this->club);

        $this->assertEquals(0, $second['created_count']);
        $this->assertEquals(1, Bill::where('club_id', $this->club->id)->count());

        Carbon::setTestNow();
    }

    public function test_an_inactive_template_is_never_due(): void
    {
        $template = RecurringBillTemplate::create([
            'club_id' => $this->club->id,
            'vendor_name' => 'Paused Vendor',
            'amount' => 10.00,
            'frequency' => 'monthly',
            'next_run_date' => now()->subDay()->format('Y-m-d'),
            'is_active' => false,
        ]);

        $this->assertFalse($this->recurringBillService->isDue($template));
    }

    public function test_recurring_bill_actions_are_forbidden_for_non_billing_roles(): void
    {
        $member = User::factory()->create();
        $member->clubs()->attach($this->club->id, ['role' => 'member']);

        $this->actingAs($member)
            ->post(route('admin.accounting.recurring_bills.store', $this->club->slug), [
                'vendor_name' => 'X',
                'amount' => 10,
                'frequency' => 'monthly',
                'next_run_date' => now()->format('Y-m-d'),
            ])
            ->assertForbidden();
    }
}
