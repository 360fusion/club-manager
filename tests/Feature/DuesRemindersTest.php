<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\SubscriptionStatus;
use App\Domains\ClubAccounting\Models\Member;
use App\Domains\ClubAccounting\Models\MemberSubscription;
use App\Domains\ClubAccounting\Services\SubscriptionBillingService;
use App\Mail\DuesReminderMail;
use App\Models\Club;
use App\Models\User;
use App\Notifications\ClubNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DuesRemindersTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $tom;

    private MemberSubscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        $this->club = Club::factory()->create(['settings' => ['dues_reminders' => true]]);
        $this->tom = User::factory()->create();
        $this->club->users()->attach($this->tom->id, ['role' => 'member', 'status' => 'active']);
        $member = Member::create(['club_id' => $this->club->id, 'user_id' => $this->tom->id, 'first_name' => 'Thomas', 'last_name' => 'Hartley', 'email' => 'tom@example.test', 'membership_status' => 'active']);
        $this->subscription = MemberSubscription::create(['club_id' => $this->club->id, 'member_id' => $member->id, 'billing_year' => 2027, 'due_date' => now()->addDays(10)->toDateString(), 'amount_due' => 180, 'amount_paid' => 0, 'status' => SubscriptionStatus::Unpaid, 'invoice_reference' => 'SUB-2027-014']);
    }

    private function remind(): void
    {
        $this->artisan('app:send-dues-reminders')->assertSuccessful();
    }

    public function test_dues_due_within_two_weeks_are_reminded_by_email_and_in_the_app_then_not_again_for_four_weeks(): void
    {
        $this->remind();

        Mail::assertQueued(DuesReminderMail::class, function (DuesReminderMail $mail) {
            $mail->assertSeeInHtml('Bro Thomas Hartley')->assertSeeInHtml('<strong>£180.00</strong> is still to pay', false);

            return $mail->hasTo('tom@example.test');
        });
        Notification::assertSentTo($this->tom, ClubNotification::class, fn (ClubNotification $notice) => $notice->title === 'Dues: £180.00 still to pay');
        $this->assertNotNull($this->subscription->fresh()->reminder_sent_at);

        $this->remind();
        Mail::assertQueuedCount(1);

        $this->travel(29)->days();
        $this->remind();
        Mail::assertQueuedCount(2);
    }

    public function test_no_one_is_chased_when_the_lodge_has_not_switched_reminders_on_or_the_due_date_is_far_off(): void
    {
        $this->subscription->update(['due_date' => now()->addMonths(3)->toDateString()]);
        $this->remind();

        $this->subscription->update(['due_date' => now()->addDays(10)->toDateString()]);
        $this->club->update(['settings' => []]);
        $this->remind();

        Mail::assertNothingQueued();
    }

    public function test_a_standing_order_on_track_is_left_alone_but_one_that_is_behind_is_reminded(): void
    {
        $this->subscription->update(['due_date' => now()->addMonths(10)->toDateString()]);
        $billing = app(SubscriptionBillingService::class);
        $billing->recordPayment($this->subscription, 18, 'SO', null, null, now()->subMonth());
        $billing->recordPayment($this->subscription->fresh(), 18, 'SO', null, null, now());
        $this->remind();
        Mail::assertNothingQueued();

        $this->subscription->fresh()->update(['amount_due' => 400]);
        $this->remind();
        Mail::assertQueued(DuesReminderMail::class, fn (DuesReminderMail $mail) => $mail->dues['pace']['state'] === 'behind');
    }

    public function test_a_member_who_says_they_have_paid_is_not_chased_until_the_money_arrives(): void
    {
        $this->actingAs($this->tom)->post(route('members.dues.said_paid', $this->subscription->id))->assertSessionHasNoErrors();
        $this->assertNotNull($this->subscription->fresh()->said_paid_at);
        $this->remind();
        Mail::assertNothingQueued();

        // The Treasurer sees a part payment in the bank: that clears it, and the rest is chased again.
        app(SubscriptionBillingService::class)->recordPayment($this->subscription->fresh(), 50, 'BACS');
        $this->assertNull($this->subscription->fresh()->said_paid_at);
        $this->remind();
        Mail::assertQueued(DuesReminderMail::class, fn (DuesReminderMail $mail) => $mail->dues['left'] === '£130.00');
    }

    public function test_only_the_member_can_say_they_have_paid_their_own_dues(): void
    {
        $other = User::factory()->create();
        $this->club->users()->attach($other->id, ['role' => 'member', 'status' => 'active']);

        $this->actingAs($other)->post(route('members.dues.said_paid', $this->subscription->id))->assertNotFound();
        $this->assertNull($this->subscription->fresh()->said_paid_at);
    }

    public function test_the_treasurer_switches_reminders_and_a_plain_member_cannot(): void
    {
        $treasurer = User::factory()->create();
        $this->club->users()->attach($treasurer->id, ['role' => 'treasurer', 'status' => 'active']);

        $this->actingAs($this->tom)->post(route('member.accounts.dues.reminders', $this->club->slug), ['on' => false])->assertForbidden();
        $this->actingAs($treasurer)->post(route('member.accounts.dues.reminders', $this->club->slug), ['on' => false])->assertSessionHasNoErrors();

        $this->assertFalse($this->club->fresh()->settings['dues_reminders']);
        $this->actingAs($treasurer)->get(route('member.accounts.dues', $this->club->slug))
            ->assertInertia(fn ($page) => $page->where('reminders', ['on' => false, 'can_change' => true]));
    }
}
