<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\BankTransactionStatus;
use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Models\BankImport;
use App\Domains\ClubAccounting\Models\BankTransaction;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Domains\ClubAccounting\Models\SubscriptionPayment;
use App\Domains\ClubAccounting\Services\BankReconciliationMatcherService;
use App\Domains\ClubAccounting\Services\SubscriptionBillingService;
use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuesTrackingTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $treasurer;

    private User $tom;

    private MemberSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->treasurer = User::factory()->create();
        $this->club->users()->attach($this->treasurer->id, ['role' => 'treasurer', 'status' => 'active']);
        $this->tom = User::factory()->create();
        $this->club->users()->attach($this->tom->id, ['role' => 'member', 'status' => 'active']);
        $member = Member::create(['club_id' => $this->club->id, 'user_id' => $this->tom->id, 'first_name' => 'Thomas', 'last_name' => 'Hartley', 'membership_status' => 'active']);
        $this->subscription = MemberSubscription::create(['club_id' => $this->club->id, 'member_id' => $member->id, 'billing_year' => 2027, 'due_date' => now()->addMonths(10)->toDateString(), 'amount_due' => 180, 'amount_paid' => 0, 'status' => SubscriptionStatus::Unpaid, 'invoice_reference' => 'SUB-2027-014']);
        $this->actingAs($this->treasurer);
    }

    private function line(string $description, float $amount, string $hash): BankTransaction
    {
        $import = BankImport::create(['club_id' => $this->club->id, 'filename' => 's.csv', 'total_lines' => 1, 'total_amount' => $amount]);

        return BankTransaction::create(['club_id' => $this->club->id, 'bank_import_id' => $import->id, 'transaction_date' => now()->toDateString(), 'raw_description' => $description, 'amount' => $amount, 'transaction_hash' => $hash, 'status' => BankTransactionStatus::Unmatched]);
    }

    private function myDues()
    {
        return $this->actingAs($this->tom)->get('/members/dues');
    }

    public function test_each_payment_is_kept_and_a_monthly_payer_sees_paid_left_and_that_they_are_on_track(): void
    {
        $billing = app(SubscriptionBillingService::class);
        $billing->recordPayment($this->subscription, 18, 'SO', null, null, now()->subMonth());
        $billing->recordPayment($this->subscription->fresh(), 18, 'SO', null, null, now());

        $this->assertSame(2, SubscriptionPayment::count());
        $this->myDues()->assertInertia(fn ($page) => $page
            ->where('subscriptions.0.paid', '£36.00')
            ->where('subscriptions.0.left', '£144.00')
            ->where('subscriptions.0.percent', 20)
            ->where('subscriptions.0.pace.state', 'on_track')
            ->has('subscriptions.0.payments', 2));
    }

    public function test_a_monthly_payment_too_small_to_finish_by_the_due_date_shows_as_behind_and_a_passed_date_as_overdue(): void
    {
        $billing = app(SubscriptionBillingService::class);
        $billing->recordPayment($this->subscription, 5, 'SO', null, null, now()->subMonth());
        $billing->recordPayment($this->subscription->fresh(), 5, 'SO', null, null, now());
        $this->myDues()->assertInertia(fn ($page) => $page->where('subscriptions.0.pace.state', 'behind'));

        $this->subscription->update(['due_date' => now()->subDay()->toDateString()]);
        $this->myDues()->assertInertia(fn ($page) => $page->where('subscriptions.0.pace.state', 'overdue'));
    }

    public function test_a_standing_order_matched_once_is_suggested_for_the_same_member_next_month(): void
    {
        $matcher = app(BankReconciliationMatcherService::class);
        $first = $this->line('SO T HARTLEY LODGE', 15, 'first');
        $matcher->reconcileTransaction($first, 'member_subscription', $this->subscription->id);
        $this->assertSame($first->id, SubscriptionPayment::sole()->bank_transaction_id);

        // Next month: the same payee, no reference or full name in it, and a part payment.
        $suggestion = $matcher->suggestMatches($this->line('SO T HARTLEY LODGE', 15, 'second'))[0];
        $this->assertSame(['member_subscription', $this->subscription->id, 95], [$suggestion['match_type'], $suggestion['target_id'], $suggestion['confidence_score']]);
    }

    public function test_the_treasurer_sees_everyones_dues_and_a_member_cannot(): void
    {
        $this->get(route('member.accounts.dues', ['slug' => $this->club->slug]))->assertInertia(fn ($page) => $page
            ->component('Member/Accounts/Dues')
            ->where('dues.year', 2027)
            ->where('dues.rows.0.name', 'Bro Thomas Hartley')
            ->where('dues.totals.left', '£180.00'));

        $this->actingAs($this->tom)->get(route('member.accounts.dues', ['slug' => $this->club->slug]))->assertForbidden();
    }
}
