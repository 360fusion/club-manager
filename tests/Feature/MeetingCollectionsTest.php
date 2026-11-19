<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankImport;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Models\CharityCollection;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Services\BankReconciliationMatcherService;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalItem;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\User;
use App\Services\MeetingFinancialReturns;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingCollectionsTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $steward;

    private Member $counter;

    private Member $witness;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->steward = User::factory()->create();
        $this->club->users()->attach($this->steward->id, ['role' => 'member', 'status' => 'active']);
        $this->counter = Member::create(['club_id' => $this->club->id, 'user_id' => $this->steward->id, 'first_name' => 'Colin', 'last_name' => 'Counter', 'membership_status' => 'active', 'current_office' => 'charity_steward']);
        $this->witness = Member::create(['club_id' => $this->club->id, 'first_name' => 'Walter', 'last_name' => 'Witness', 'membership_status' => 'active']);
        $this->meeting = Meeting::create(['club_id' => $this->club->id, 'title' => 'March Meeting', 'meeting_date' => now()->toDateString(), 'starts_at' => '18:30:00', 'venue' => 'Hall', 'dress_code' => 'Suit', 'status' => 'published']);
    }

    private function save(array $data, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->steward)->post(route('member.meetings.collections.store', ['slug' => $this->club->slug, 'id' => $this->meeting->id]), $data + [
            'counted_by_member_id' => $this->counter->id, 'witnessed_by_member_id' => $this->witness->id,
        ]);
    }

    private function balance(string $code, string $side): float
    {
        return (float) JournalItem::where('account_id', Account::where('club_id', $this->club->id)->where('code', $code)->value('id'))->sum($side);
    }

    public function test_the_charity_steward_enters_alms_and_raffle_and_the_cash_goes_into_the_books_awaiting_banking(): void
    {
        $this->actingAs($this->steward)->get(route('member.meetings.collections', ['slug' => $this->club->slug, 'id' => $this->meeting->id]))
            ->assertInertia(fn ($page) => $page->component('Member/Meetings/Collections')->has('entries', 2));

        $this->save(['type' => 'alms_plate', 'cash_amount' => 42.50, 'cheque_amount' => 20])->assertSessionHasNoErrors();
        $this->save(['type' => 'raffle', 'cash_amount' => 60, 'card_amount' => 35])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(62.50, $this->balance('4400', 'credit'), 0.001);
        $this->assertEqualsWithDelta(60.00, $this->balance('4300', 'credit'), 0.001, 'card takings come through the card provider, not here');
        $this->assertEqualsWithDelta(122.50, $this->balance('1150', 'debit'), 0.001);
        $this->assertEqualsWithDelta(95.00, (float) CharityCollection::where('collection_type', 'raffle')->sole()->total_amount, 0.001, 'the totals include card');
    }

    public function test_an_edit_rewrites_the_same_entry_and_nothing_in_cash_removes_it(): void
    {
        $this->save(['type' => 'alms_plate', 'cash_amount' => 40]);
        $this->save(['type' => 'alms_plate', 'cash_amount' => 45]);

        $this->assertSame(1, CharityCollection::count());
        $this->assertSame(1, JournalEntry::where('source_type', 'CharityCollection')->count());
        $this->assertEqualsWithDelta(45.0, $this->balance('4400', 'credit'), 0.001);

        $this->save(['type' => 'alms_plate', 'cash_amount' => 0, 'card_amount' => 10]);
        $this->assertSame(0, JournalEntry::where('source_type', 'CharityCollection')->count());
    }

    public function test_only_the_lodges_counting_officers_can_enter_it_and_the_witness_must_be_someone_else(): void
    {
        $plain = User::factory()->create();
        $this->club->users()->attach($plain->id, ['role' => 'member', 'status' => 'active']);
        $this->actingAs($plain)->get(route('member.meetings.collections', ['slug' => $this->club->slug, 'id' => $this->meeting->id]))->assertForbidden();

        $otherLodge = Club::factory()->create();
        $otherSteward = User::factory()->create();
        $otherLodge->users()->attach($otherSteward->id, ['role' => 'member', 'status' => 'active']);
        Member::create(['club_id' => $otherLodge->id, 'user_id' => $otherSteward->id, 'first_name' => 'Other', 'last_name' => 'Steward', 'membership_status' => 'active', 'current_office' => 'charity_steward']);
        $this->save(['type' => 'alms_plate', 'cash_amount' => 5], $otherSteward)->assertForbidden();

        $this->save(['type' => 'alms_plate', 'cash_amount' => 5, 'witnessed_by_member_id' => $this->counter->id])->assertSessionHasErrors('witnessed_by_member_id');
        $this->assertSame(0, CharityCollection::count());
    }

    public function test_the_paying_in_line_moves_the_money_to_the_bank_and_locks_the_entries(): void
    {
        $this->save(['type' => 'alms_plate', 'cash_amount' => 50]);
        $this->save(['type' => 'raffle', 'cash_amount' => 30]);

        $import = BankImport::create(['club_id' => $this->club->id, 'filename' => 's.csv', 'total_lines' => 1, 'total_amount' => 80]);
        $line = BankTransaction::create(['club_id' => $this->club->id, 'bank_import_id' => $import->id, 'transaction_date' => now()->toDateString(), 'raw_description' => 'PAID IN AT COUNTER', 'amount' => 80, 'transaction_hash' => 'paid-in', 'status' => BankTransactionStatus::Unmatched]);
        $matcher = app(BankReconciliationMatcherService::class);

        $suggestion = collect($matcher->suggestMatches($line))->firstWhere('match_type', 'meeting_collections');
        $this->assertSame([$this->meeting->id, 90], [$suggestion['target_id'], $suggestion['confidence_score']]);

        $this->actingAs($this->steward);
        $matcher->reconcileTransaction($line, 'meeting_collections', $this->meeting->id);

        $this->assertEqualsWithDelta(80.0, $this->balance('1150', 'credit'), 0.001, 'money awaiting banking is cleared');
        $this->assertSame(2, CharityCollection::whereNotNull('bank_transaction_id')->count());
        $this->save(['type' => 'alms_plate', 'cash_amount' => 55])->assertSessionHasErrors('cash_amount');
    }

    public function test_the_financial_return_shows_the_phone_entries_and_does_not_post_them_again(): void
    {
        $this->save(['type' => 'raffle', 'cash_amount' => 60]);
        $this->actingAs($this->steward);

        $return = app(MeetingFinancialReturns::class)->postMeetingFinancialReturn($this->club, $this->meeting, [
            'return_date' => now()->toDateString(), 'dining_fee_per_head' => 30, 'paid_diners_count' => 10, 'kitchen_cost_per_head' => 20, 'raffle_amount' => 999, 'alms_amount' => 0,
        ]);

        $this->assertEqualsWithDelta(60.0, (float) $return->raffle_amount, 0.001, 'the return takes the phone figure, not what was typed');
        $this->assertEqualsWithDelta(60.0, $this->balance('4300', 'credit'), 0.001, 'raffle income is in the books once');
        $this->assertEqualsWithDelta(300.0, $this->balance('4200', 'credit'), 0.001);
    }
}
