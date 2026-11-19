<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Models\BankImport;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Services\BankReconciliationMatcherService;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalItem;
use App\Models\Club;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\MeetingRsvpGuest;
use App\Models\User;
use App\Services\Meetings\MeetingReplies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingDiningBankTransferTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $secretary;

    private User $tom;

    private Meeting $meeting;

    private MeetingRsvp $reply;

    private MeetingRsvpGuest $guest;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->secretary = User::factory()->create();
        $this->club->users()->attach($this->secretary->id, ['role' => 'admin', 'status' => 'active']);
        $this->tom = User::factory()->create(['name' => 'Tom Hartley']);
        $this->club->users()->attach($this->tom->id, ['role' => 'member', 'status' => 'active']);

        $this->meeting = Meeting::create(['club_id' => $this->club->id, 'title' => 'March Meeting', 'meeting_date' => now()->addWeek()->toDateString(), 'starts_at' => '18:30:00', 'venue' => 'Hall', 'dress_code' => 'Suit', 'status' => 'published', 'dining_cost_member' => 30, 'dining_cost_guest' => 35, 'bank_sort_code' => '12-34-56', 'bank_account_number' => '12345678']);
        $this->reply = app(MeetingReplies::class)->rsvpFor($this->meeting, $this->tom);
        $this->reply->update(['attendance_status' => 'attending_dining']);
        $this->guest = $this->reply->guests()->create(['guest_name' => 'Gus Guest', 'attending_dining' => true]);
    }

    private function line(string $description, float $amount): BankTransaction
    {
        $import = BankImport::create(['club_id' => $this->club->id, 'filename' => 's.csv', 'total_lines' => 1, 'total_amount' => $amount]);

        return BankTransaction::create(['club_id' => $this->club->id, 'bank_import_id' => $import->id, 'transaction_date' => now()->toDateString(), 'raw_description' => $description, 'amount' => $amount, 'transaction_hash' => md5($description.$amount), 'status' => BankTransactionStatus::Unmatched]);
    }

    private function diningList()
    {
        return $this->actingAs($this->secretary)->get(route('admin.meetings.dining', ['clubSlug' => $this->club->slug, 'id' => $this->meeting->id]));
    }

    public function test_a_member_says_they_paid_and_the_dining_list_shows_it_until_the_bank_line_is_matched(): void
    {
        $this->actingAs($this->tom)->post(route('member.meetings.said_paid', ['slug' => $this->club->slug, 'id' => $this->meeting->id]))->assertSessionHasNoErrors();
        $this->assertNotNull($this->reply->fresh()->said_paid_at);
        $this->assertSame('unpaid', $this->reply->fresh()->payment_status);
        $this->diningList()->assertInertia(fn ($page) => $page->where('diners', fn ($diners) => collect($diners)->every(fn ($diner) => $diner['said_paid_at'] === now()->format('j M'))));

        $reference = MeetingReplies::paymentReference($this->meeting, $this->tom);
        $tx = $this->line("FASTER PAYMENT {$reference}", 65.00);
        $suggestion = app(BankReconciliationMatcherService::class)->suggestMatches($tx)[0];
        $this->assertSame(['meeting_dining', $this->reply->id, 100], [$suggestion['match_type'], $suggestion['target_id'], $suggestion['confidence_score']]);

        $this->actingAs($this->secretary);
        app(BankReconciliationMatcherService::class)->reconcileTransaction($tx, 'meeting_dining', $this->reply->id);

        foreach ([$this->reply->fresh(), $this->guest->fresh()] as $place) {
            $this->assertSame(['paid', 'bank', $tx->id], [$place->payment_status, $place->payment_method, $place->bank_transaction_id]);
        }
        $income = Account::where('club_id', $this->club->id)->where('code', '4200')->value('id');
        $this->assertEqualsWithDelta(65.0, (float) JournalItem::where('account_id', $income)->sum('credit'), 0.001, 'posted once to dining income');
        $this->diningList()->assertInertia(fn ($page) => $page->where('diners.0.said_paid_at', null)->where('diners.0.seen_in_bank', true));
    }

    public function test_a_line_for_the_members_own_place_pays_only_that_and_an_odd_amount_is_not_suggested(): void
    {
        $reference = MeetingReplies::paymentReference($this->meeting, $this->tom);
        $matcher = app(BankReconciliationMatcherService::class);

        $odd = $matcher->suggestMatches($this->line("BACS {$reference}", 40.00));
        $this->assertNotContains('meeting_dining', array_column($odd, 'match_type'));

        $tx = $this->line("BACS {$reference}", 30.00);
        $this->assertSame(90, $matcher->suggestMatches($tx)[0]['confidence_score']);
        $this->actingAs($this->secretary);
        $matcher->reconcileTransaction($tx, 'meeting_dining', $this->reply->id);

        $this->assertSame('paid', $this->reply->fresh()->payment_status);
        $this->assertSame('unpaid', $this->guest->fresh()->payment_status);
    }

    public function test_the_emailed_reply_link_offers_and_records_saying_paid(): void
    {
        $page = app(MeetingReplies::class)->replyUrl($this->meeting, $this->reply);
        $saidPaidUrl = null;
        $this->get($page)->assertInertia(function ($inertia) use (&$saidPaidUrl) {
            $saidPaidUrl = $inertia->toArray()['props']['saidPaidUrl'];

            return $inertia->where('owed', fn ($owed) => str_contains($owed, '65.00'));
        });

        $this->post($saidPaidUrl)->assertSessionHasNoErrors();
        $this->assertNotNull($this->reply->fresh()->said_paid_at);
        $this->post($saidPaidUrl.'x')->assertForbidden();
    }

    public function test_saying_paid_needs_bank_details_on_the_meeting_and_something_owed(): void
    {
        $this->meeting->update(['bank_sort_code' => null, 'bank_account_number' => null]);
        $this->actingAs($this->tom)->post(route('member.meetings.said_paid', ['slug' => $this->club->slug, 'id' => $this->meeting->id]))->assertSessionHasErrors('payment');

        $this->meeting->update(['bank_account_number' => '12345678']);
        $this->reply->update(['attendance_status' => 'attending_meeting_only']);
        $this->guest->update(['attending_dining' => false]);
        $this->actingAs($this->tom)->post(route('member.meetings.said_paid', ['slug' => $this->club->slug, 'id' => $this->meeting->id]))->assertSessionHasErrors('payment');

        $this->assertNull($this->reply->fresh()->said_paid_at);
    }

    public function test_the_secretary_taking_a_place_back_to_unpaid_clears_what_was_said(): void
    {
        $this->reply->forceFill(['said_paid_at' => now()])->save();

        $this->actingAs($this->secretary)->post(route('admin.meetings.dining.payment', ['clubSlug' => $this->club->slug, 'id' => $this->meeting->id]), ['kind' => 'member', 'diner_id' => $this->reply->id, 'payment_status' => 'unpaid'])->assertSessionHasNoErrors();

        $this->assertNull($this->reply->fresh()->said_paid_at);
    }
}
