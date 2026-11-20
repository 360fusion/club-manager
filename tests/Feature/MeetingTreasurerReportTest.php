<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\CollectionType;
use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Models\CharityCollection;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Models\Accounting\Bill;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\User;
use App\Services\AccountingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingTreasurerReportTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $treasurer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-20 12:00:00');
        $this->club = Club::factory()->create();
        $this->treasurer = User::factory()->create();
        $this->club->users()->attach($this->treasurer->id, ['role' => 'treasurer', 'status' => 'active']);
    }

    private function meeting(string $date): Meeting
    {
        return Meeting::create(['club_id' => $this->club->id, 'title' => 'Regular '.$date, 'meeting_date' => $date, 'starts_at' => '19:00:00', 'venue' => 'Hall', 'dress_code' => 'Suit', 'status' => 'published']);
    }

    private function postEntry(string $date, string $from, string $to, float $amount): void
    {
        $ledger = app(AccountingService::class);
        $ledger->postJournalEntry($this->club, ['description' => 'Test', 'entry_date' => $date, 'items' => [
            ['account_id' => $ledger->getAccount($this->club, $to)->id, 'debit' => $amount, 'credit' => 0],
            ['account_id' => $ledger->getAccount($this->club, $from)->id, 'debit' => 0, 'credit' => $amount],
        ]]);
    }

    public function test_the_report_covers_money_since_the_last_meeting_and_what_is_still_owed(): void
    {
        $last = $this->meeting('2026-10-14');
        $meeting = $this->meeting('2026-11-18');

        $this->postEntry('2026-10-01', '4000', '1000', 500);   // before the last meeting: in the balance, not in "money in"
        $this->postEntry('2026-10-20', '4000', '1000', 120);   // dues received since
        $this->postEntry('2026-11-02', '1000', '5100', 45);    // a bill paid since
        $this->postEntry('2026-11-18', '4400', '1000', 999);   // on the meeting day itself: next report

        CharityCollection::create(['club_id' => $this->club->id, 'meeting_id' => $last->id, 'collection_type' => CollectionType::AlmsPlate, 'cash_amount' => 30, 'cheque_amount' => 0, 'card_amount' => 12.5]);
        $member = Member::create(['club_id' => $this->club->id, 'first_name' => 'Tom', 'last_name' => 'Hartley', 'membership_status' => 'active']);
        MemberSubscription::create(['club_id' => $this->club->id, 'member_id' => $member->id, 'billing_year' => 2026, 'due_date' => '2026-05-01', 'amount_due' => 180, 'amount_paid' => 60, 'status' => SubscriptionStatus::PartiallyPaid, 'invoice_reference' => 'SUB-1']);
        Bill::create(['club_id' => $this->club->id, 'bill_number' => 'BILL-1', 'vendor_name' => 'Hall Trust', 'category' => 'Rent', 'amount' => 80, 'due_date' => '2026-11-30', 'status' => 'pending_approval']);

        $response = $this->actingAs($this->treasurer)->get(route('admin.accounting.meeting_report', ['clubSlug' => $this->club->slug, 'id' => $meeting->id]))->assertOk();

        $data = $response->viewData('money');
        $this->assertSame(575.0, collect($data)->firstWhere('name', 'Operating Bank Account')['balance'], '500 + 120 - 45, without the meeting-day entry');
        $this->assertSame(120.0, $response->viewData('income_total'));
        $this->assertSame(45.0, $response->viewData('spending_total'));
        $this->assertSame(['members' => 1, 'total' => 120.0], $response->viewData('dues'));
        $this->assertSame(42.5, $response->viewData('charity')[0]['amount'], "the last meeting's alms, card included");
        $response->assertSee('Hall Trust')->assertSee('the last meeting on 14 October 2026');
    }

    public function test_only_people_who_may_see_the_accounts_can_open_it(): void
    {
        $meeting = $this->meeting('2026-11-18');
        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($member)->get(route('admin.accounting.meeting_report', ['clubSlug' => $this->club->slug, 'id' => $meeting->id]))->assertForbidden();
        $this->actingAs($this->treasurer)->get(route('admin.accounting.meeting_report', ['clubSlug' => $this->club->slug, 'id' => $meeting->id, 'download' => 1]))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
