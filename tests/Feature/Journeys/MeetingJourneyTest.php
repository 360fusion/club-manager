<?php

namespace Tests\Feature\Journeys;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\Member;
use App\Mail\MeetingNumbersMail;
use App\Mail\MeetingReplyMail;
use App\Mail\MeetingSummonsMail;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\User;
use App\Services\Meetings\MeetingReplies;
use App\Services\Payment\StripeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Journey E in tests/JOURNEYS.md as one story: the Secretary publishes a summons, members answer from the summons and
 * from the reply schedule's emails, one pays by card, the Secretary and the caterer get the numbers, late changes are
 * passed on, a dropped place is refunded, and those coming are reminded the day before.
 */
class MeetingJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_journey';

    private Club $lodge;

    private User $secretary;

    private User $tom;

    private User $ann;

    private User $ben;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config(['events.online_payments' => true]);
        $this->travelTo(Carbon::parse('2026-11-20 10:00', 'Europe/London'));

        $this->lodge = Club::factory()->create(['name' => 'Harbour Lodge', 'slug' => 'harbour']);
        $this->lodge->update(['settings' => [...($this->lodge->settings ?? []), 'caterer_email' => 'kitchen@example.test']]);
        $this->secretary = $this->join('admin', 'Sam Secretary');
        Member::create(['club_id' => $this->lodge->id, 'user_id' => $this->secretary->id, 'first_name' => 'Sam', 'last_name' => 'Secretary', 'masonic_rank' => 'WBro', 'membership_status' => 'active', 'current_office' => LodgeOffice::Secretary->value, 'email' => 'secretary@example.test']);
        $this->tom = $this->join('member', 'Tom Hart');
        $this->ann = $this->join('member', 'Ann Lee');
        $this->ben = $this->join('member', 'Ben Cole');
        ClubPaymentMethod::create(['club_id' => $this->lodge->id, 'type' => ClubPaymentMethod::CARD, 'label' => 'Card', 'sort_order' => 0, 'is_active' => true, 'config' => ['stripe_secret_key' => 'sk_test_x', 'stripe_webhook_secret' => self::SECRET]]);
    }

    private function join(string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->lodge->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    private function runAt(string $londonTime): void
    {
        $this->travelTo(Carbon::parse($londonTime, 'Europe/London'));
        $this->artisan('app:send-meeting-replies')->assertSuccessful();
    }

    private function paidWebhook(array $metadata): void
    {
        $payload = json_encode(['id' => 'evt_journey', 'object' => 'event', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_tom', 'object' => 'checkout.session', 'payment_status' => 'paid', 'amount_total' => 6500, 'payment_intent' => 'pi_tom', 'metadata' => $metadata,
        ]]]);
        $timestamp = time();

        $this->call('POST', route('webhooks.stripe.club', ['clubId' => $this->lodge->id]), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", self::SECRET)], $payload)->assertSuccessful();
    }

    public function test_a_lodge_meeting_from_the_summons_to_the_night_before(): void
    {
        $checkout = null;
        $this->mock(StripeGateway::class, function (MockInterface $mock) use (&$checkout) {
            $mock->shouldReceive('createCheckoutSession')->once()->andReturnUsing(function ($method, $params) use (&$checkout) {
                $checkout = $params;

                return ['id' => 'cs_tom', 'url' => 'https://checkout.stripe.test/cs_tom'];
            });
            $mock->shouldReceive('refund')->once()->withArgs(fn ($method, $intent, $minor) => $intent === 'pi_tom' && $minor === 3500)->andReturn('re_gus');
        });

        // E1: the Secretary publishes the summons; every member gets it with their private reply link.
        $meeting = Meeting::create([
            'club_id' => $this->lodge->id, 'title' => 'December Meeting', 'meeting_date' => '2026-12-11', 'starts_at' => '18:30:00', 'venue' => 'Masonic Hall',
            'dress_code' => 'Dark suit', 'status' => 'draft', 'dining_cost_member' => 30, 'dining_cost_guest' => 35, 'festive_board_menu' => 'Soup, beef, crumble',
            'rsvp_cutoff_at' => MeetingReplies::defaultCutoff($this->lodge, '2026-12-11'),
        ]);
        $this->actingAs($this->secretary)->post(route('admin.meetings.publish', ['clubSlug' => 'harbour', 'id' => $meeting->id]))->assertSessionHasNoErrors();
        Mail::assertQueued(MeetingSummonsMail::class, 4);

        // E2: Ann sends apologies straight from the summons email.
        $annToken = Mail::queued(MeetingSummonsMail::class)->first(fn (MeetingSummonsMail $mail) => $mail->hasTo($this->ann->email))->rsvpToken;
        $this->post(route('summons.rsvp.store', ['token' => $annToken]), ['attendance_status' => 'apologies', 'apology_reason' => 'Away with work'])->assertSessionHasNoErrors();

        // E3: a week before, the members who have not replied are asked; Tom answers "attending and dining" from the email, with a guest.
        $this->runAt('2026-12-04 09:00');
        $asked = Mail::queued(MeetingReplyMail::class)->filter(fn (MeetingReplyMail $mail) => $mail->kind === MeetingReplyMail::ASK);
        $this->assertEqualsCanonicalizing([$this->secretary->email, $this->tom->email, $this->ben->email], $asked->map(fn ($mail) => $mail->to[0]['address'])->values()->all());

        $tomsMail = $asked->first(fn (MeetingReplyMail $mail) => $mail->hasTo($this->tom->email));
        $this->get($tomsMail->links['attending_dining'])->assertInertia(fn ($page) => $page->component('Summons/Rsvp')->where('answer', 'attending_dining'));
        $tomsReply = MeetingRsvp::where('user_id', $this->tom->id)->first();
        $replies = app(MeetingReplies::class);
        $this->post(\URL::temporarySignedRoute('summons.reply.store', $replies->linkExpiry($meeting), ['rsvp' => $tomsReply->id]), [
            'attendance_status' => 'attending_dining',
            'dietary_requirements' => 'No nuts',
            'guests' => [['guest_name' => 'Gus Guest', 'attending_dining' => true, 'dietary_requirements' => 'Vegetarian']],
        ])->assertSessionHasNoErrors();

        // E4: Tom pays for both places by card from his summons page.
        $this->actingAs($this->tom)->get(route('member.meetings.summons', ['slug' => 'harbour', 'id' => $meeting->id]))
            ->assertInertia(fn ($page) => $page->where('owed', '£65.00')->where('payWith', ['card']));
        $this->actingAs($this->tom)->post(route('member.meetings.pay', ['slug' => 'harbour', 'id' => $meeting->id]), ['provider' => 'card'])->assertRedirect('https://checkout.stripe.test/cs_tom');
        $this->paidWebhook($checkout['metadata']);
        $this->assertSame(0.0, MeetingReplies::owed($meeting, $tomsReply->fresh('guests'))['amount']);

        // E5: the day dining closes, a last call goes to whoever still has not replied.
        $this->runAt('2026-12-07 09:00');
        $lastCall = Mail::queued(MeetingReplyMail::class)->filter(fn (MeetingReplyMail $mail) => $mail->kind === MeetingReplyMail::LAST_CALL);
        $this->assertEqualsCanonicalizing([$this->secretary->email, $this->ben->email], $lastCall->map(fn ($mail) => $mail->to[0]['address'])->values()->all());

        // E6: once it has closed, the Secretary gets the numbers, and the caterer only what the kitchen needs.
        $this->runAt('2026-12-08 09:00');
        $numbers = Mail::queued(MeetingNumbersMail::class)->first(fn (MeetingNumbersMail $mail) => $mail->hasTo('secretary@example.test'));
        $this->assertSame(2, $numbers->numbers['dining']);
        $this->assertSame([['name' => 'Ann Lee', 'reason' => 'Away with work']], $numbers->numbers['apologies']);
        $this->assertSame(['Ben Cole', 'WBro Sam Secretary'], $numbers->numbers['no_reply']);
        $kitchen = Mail::queued(MeetingNumbersMail::class)->first(fn (MeetingNumbersMail $mail) => $mail->hasTo('kitchen@example.test'))->render();
        $this->assertStringContainsString('Dining: 2', $kitchen);
        $this->assertStringContainsString('Vegetarian', $kitchen);
        $this->assertStringNotContainsString('Ann Lee', $kitchen);

        // E7: late changes. Ben cannot add dining now but can send apologies; Tom takes his paid guest off.
        $this->actingAs($this->ben)->post(route('member.meetings.quick_rsvp', ['slug' => 'harbour', 'id' => $meeting->id]), ['attendance_status' => 'attending_dining'])->assertSessionHasErrors('cutoff');
        $this->actingAs($this->ben)->post(route('member.meetings.quick_rsvp', ['slug' => 'harbour', 'id' => $meeting->id]), ['attendance_status' => 'apologies'])->assertSessionHasNoErrors();
        $this->actingAs($this->tom)->post(route('member.meetings.rsvp', ['slug' => 'harbour', 'id' => $meeting->id]), ['attendance_status' => 'attending_dining', 'guests' => []])->assertSessionHasNoErrors();

        $changes = Mail::queued(MeetingNumbersMail::class)->filter(fn (MeetingNumbersMail $mail) => $mail->change !== null);
        $this->assertTrue($changes->contains(fn ($mail) => ! $mail->forCaterer && str_starts_with($mail->change, 'Ben Cole: no reply → apologies')));
        $this->assertTrue($changes->contains(fn ($mail) => ! $mail->forCaterer && str_contains($mail->change, 'They had already paid')));
        $this->assertSame(['The numbers have changed: now 1 dining.'], $changes->where('forCaterer', true)->pluck('change')->values()->all());

        // E8: the Secretary refunds the guest's place through Stripe from the dining list.
        $diningList = route('admin.meetings.dining', ['clubSlug' => 'harbour', 'id' => $meeting->id]);
        $this->actingAs($this->secretary)->get($diningList)->assertInertia(fn ($page) => $page->has('refundable', 1)->where('refundable.0.name', 'Gus Guest'));
        $gus = $tomsReply->guests()->sole();
        $this->actingAs($this->secretary)->post(route('admin.meetings.dining.refund', ['clubSlug' => 'harbour', 'id' => $meeting->id]), ['kind' => 'guest', 'diner_id' => $gus->id])->assertSessionHasNoErrors();
        $this->assertSame('refunded', $gus->fresh()->payment_status);

        // E9: the day before, only those coming are reminded, and Tom has nothing left to pay.
        $this->runAt('2026-12-10 09:00');
        $reminders = Mail::queued(MeetingReplyMail::class)->filter(fn (MeetingReplyMail $mail) => $mail->kind === MeetingReplyMail::CONFIRM);
        $this->assertSame([$this->tom->email], $reminders->map(fn ($mail) => $mail->to[0]['address'])->values()->all());
        $this->assertStringNotContainsString('still to pay', $reminders->first()->render());
        $this->assertSame('Tomorrow: December Meeting', $this->tom->notifications()->latest()->first()->data['title']);

        // E10: the caterer sheet has the final count.
        $this->actingAs($this->secretary)->get(route('admin.meetings.dining.caterer', ['clubSlug' => 'harbour', 'id' => $meeting->id]))
            ->assertOk()->assertSee('1 dining')->assertSee('Tom Hart')->assertDontSee('Gus Guest');
    }
}
