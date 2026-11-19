<?php

namespace Tests\Feature;

use App\Domains\ClubAccounting\Enums\LodgeOffice;
use App\Domains\ClubAccounting\Models\Member;
use App\Mail\MeetingNumbersMail;
use App\Mail\MeetingReplyMail;
use App\Models\Club;
use App\Models\ClubEmailTemplate;
use App\Models\ClubPaymentMethod;
use App\Models\Meeting;
use App\Models\MeetingRsvp;
use App\Models\User;
use App\Services\Meetings\MeetingReplies;
use App\Services\Payment\PayPalGateway;
use App\Services\Payment\StripeGateway;
use App\Services\RsvpTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Mockery\MockInterface;
use Tests\TestCase;

class MeetingReplyScheduleTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test_meeting';

    private Club $club;

    private Meeting $meeting;

    private User $silent;

    private User $diner;

    private User $absent;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        // The meeting is on Friday 11 December 2026 (London, so GMT); the schedule runs at 7, 3 and 1 days before.
        $this->travelTo(Carbon::parse('2026-11-20 10:00:00', 'UTC'));

        $this->club = Club::factory()->create();
        Member::create(['club_id' => $this->club->id, 'first_name' => 'Sam', 'last_name' => 'Secretary', 'membership_status' => 'active', 'current_office' => LodgeOffice::Secretary->value, 'email' => 'secretary@example.test']);

        $this->silent = $this->person('Tom Silent');
        $this->diner = $this->person('Dan Diner');
        $this->absent = $this->person('Abe Absent');
        $visitor = User::factory()->create();
        $this->club->users()->attach($visitor->id, ['role' => 'visitor', 'status' => 'active']);

        $this->meeting = Meeting::create([
            'club_id' => $this->club->id, 'title' => 'December Meeting', 'meeting_date' => '2026-12-11', 'starts_at' => '18:30:00', 'venue' => 'Hall',
            'dress_code' => 'Suit', 'status' => 'published', 'summons_published_at' => now(), 'dining_cost_member' => 30, 'dining_cost_guest' => 35,
            'rsvp_cutoff_at' => MeetingReplies::defaultCutoff($this->club, '2026-12-11'),
        ]);

        $this->reply($this->diner, 'attending_dining', [['guest_name' => 'Gus Guest', 'attending_dining' => true, 'dietary_requirements' => 'Vegetarian']]);
        $this->reply($this->absent, 'apologies', [], 'Working away');
    }

    private function person(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->club->users()->attach($user->id, ['role' => 'member', 'status' => 'active']);

        return $user;
    }

    /**
     * @param  array<int, array<string, mixed>>  $guests
     */
    private function reply(User $user, string $status, array $guests = [], ?string $reason = null): MeetingRsvp
    {
        $rsvp = app(MeetingReplies::class)->rsvpFor($this->meeting, $user);
        $rsvp->update(['attendance_status' => $status, 'apology_reason' => $reason, 'responded_at' => now()]);
        MeetingReplies::syncGuests($this->meeting, $rsvp, $guests);

        return $rsvp->fresh('guests');
    }

    private function runAt(string $londonTime): void
    {
        $this->travelTo(Carbon::parse($londonTime, 'Europe/London'));
        $this->artisan('app:send-meeting-replies')->assertSuccessful();
    }

    public function test_members_who_have_not_replied_are_asked_a_week_before_from_9am_and_only_once(): void
    {
        $this->runAt('2026-12-04 08:00');
        Mail::assertNothingQueued();

        $this->runAt('2026-12-04 09:00');
        $this->runAt('2026-12-04 10:00');

        Mail::assertQueued(MeetingReplyMail::class, 1);
        Mail::assertQueued(MeetingReplyMail::class, fn (MeetingReplyMail $mail) => $mail->kind === MeetingReplyMail::ASK && $mail->hasTo($this->silent->email));
        $this->assertSame(1, $this->silent->notifications()->count());
        $this->assertSame(0, $this->diner->notifications()->count());
    }

    public function test_a_lodges_own_wording_is_used_and_still_carries_the_private_answers(): void
    {
        ClubEmailTemplate::create(['club_id' => $this->club->id, 'template_key' => 'meeting_reply_ask', 'subject' => 'Festive board: {{meeting_title}}', 'body_html' => '<p>Brother {{member_name}}, are you dining?</p>']);

        $this->runAt('2026-12-04 09:00');

        Mail::assertQueued(MeetingReplyMail::class, function (MeetingReplyMail $mail) {
            $html = $mail->render();

            return $mail->envelope()->subject === 'Festive board: December Meeting'
                && str_contains($html, 'Brother Tom Silent, are you dining?')
                && substr_count($html, '/summons/reply/') === 3;
        });
    }

    public function test_a_summons_published_inside_the_week_is_not_followed_by_a_second_ask(): void
    {
        $this->meeting->update(['summons_published_at' => Carbon::parse('2026-12-06 12:00', 'Europe/London')]);

        $this->runAt('2026-12-07 09:00');

        Mail::assertNotQueued(MeetingReplyMail::class);
        $this->assertNotNull($this->meeting->fresh()->replies_requested_at);
    }

    public function test_a_last_call_goes_the_day_before_dining_closes_and_the_secretary_gets_the_numbers_after(): void
    {
        $this->meeting->forceFill(['replies_requested_at' => now()])->save();

        $this->runAt('2026-12-07 09:00');
        Mail::assertQueued(MeetingReplyMail::class, fn (MeetingReplyMail $mail) => $mail->kind === MeetingReplyMail::LAST_CALL && $mail->hasTo($this->silent->email));
        Mail::assertNotQueued(MeetingNumbersMail::class);

        $this->runAt('2026-12-08 09:00');

        Mail::assertQueued(MeetingNumbersMail::class, 1);
        Mail::assertQueued(MeetingNumbersMail::class, function (MeetingNumbersMail $mail) {
            return $mail->hasTo('secretary@example.test') && $mail->change === null
                && $mail->numbers['dining'] === 2 && $mail->numbers['dining_guests'] === 1
                && $mail->numbers['apologies'] === [['name' => 'Abe Absent', 'reason' => 'Working away']]
                && $mail->numbers['no_reply'] === ['Tom Silent']
                && $mail->numbers['dietary'][0]['requirement'] === 'Vegetarian'
                && $mail->numbers['outstanding'] === 65.0;
        });
    }

    public function test_the_day_before_those_coming_are_reminded_with_what_they_still_owe(): void
    {
        $this->meeting->forceFill(['replies_requested_at' => now(), 'last_call_sent_at' => now(), 'numbers_sent_at' => now()])->save();

        $this->runAt('2026-12-10 09:00');

        Mail::assertQueued(MeetingReplyMail::class, 1);
        Mail::assertQueued(MeetingReplyMail::class, function (MeetingReplyMail $mail) {
            return $mail->kind === MeetingReplyMail::CONFIRM && $mail->hasTo($this->diner->email)
                && str_contains($mail->render(), 'Dining still to pay: <strong>£65.00</strong>');
        });
    }

    public function test_after_the_deadline_dining_can_be_dropped_but_not_added_and_the_secretary_hears_of_changes(): void
    {
        $this->travelTo(Carbon::parse('2026-12-09 12:00', 'Europe/London'));
        $this->meeting->forceFill(['numbers_sent_at' => now()])->save();
        $replies = app(MeetingReplies::class);
        $submit = fn (User $user, array $data) => $this->post(
            \URL::temporarySignedRoute('summons.reply.store', $replies->linkExpiry($this->meeting), ['rsvp' => $replies->rsvpFor($this->meeting, $user)->id]),
            $data,
        );

        $submit($this->silent, ['attendance_status' => 'attending_dining'])->assertSessionHasErrors('cutoff');
        $submit($this->diner, ['attendance_status' => 'attending_dining', 'guests' => [['guest_name' => 'Gus Guest', 'attending_dining' => true], ['guest_name' => 'New Guest', 'attending_dining' => true]]])->assertSessionHasErrors('cutoff');
        Mail::assertNotQueued(MeetingNumbersMail::class);

        $submit($this->silent, ['attendance_status' => 'attending_meeting_only'])->assertSessionHasNoErrors();
        $submit($this->diner, ['attendance_status' => 'attending_meeting_only'])->assertSessionHasNoErrors();

        Mail::assertQueued(MeetingNumbersMail::class, fn (MeetingNumbersMail $mail) => $mail->change === 'Dan Diner: attending and dining (2 dining) → attending, not dining.' && $mail->numbers['dining'] === 0);
        Mail::assertQueued(MeetingNumbersMail::class, 2);
    }

    public function test_an_emailed_answer_opens_the_reply_page_with_it_picked_and_a_changed_link_is_refused(): void
    {
        $token = app(RsvpTokenService::class)->createTokenForUser($this->meeting, $this->silent, now()->addMonth());
        $replies = app(MeetingReplies::class);
        $url = $replies->replyUrl($this->meeting, $replies->rsvpFor($this->meeting, $this->silent), 'apologies');

        $this->get($url)->assertInertia(fn ($page) => $page->component('Summons/Rsvp')->where('answer', 'apologies')->where('user.name', 'Tom Silent'));
        $this->get(str_replace('answer=apologies', 'answer=attending_dining', $url))->assertForbidden();

        // The summons link from before keeps working.
        $this->assertNotNull(app(RsvpTokenService::class)->validateToken($token));
    }

    public function test_dining_is_paid_by_card_for_the_member_and_their_guest_and_recorded_once(): void
    {
        config(['events.online_payments' => true]);
        ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => ClubPaymentMethod::CARD, 'label' => 'Card', 'sort_order' => 0, 'is_active' => true, 'config' => ['stripe_secret_key' => 'sk_test_x', 'stripe_webhook_secret' => self::SECRET]]);
        $rsvp = MeetingRsvp::where('user_id', $this->diner->id)->with('guests')->first();
        $captured = null;
        $this->mock(StripeGateway::class, function (MockInterface $mock) use (&$captured) {
            $mock->shouldReceive('createCheckoutSession')->once()->andReturnUsing(function ($method, $params) use (&$captured) {
                $captured = $params;

                return ['id' => 'cs_meeting_1', 'url' => 'https://checkout.stripe.test/cs_meeting_1'];
            });
        });

        $this->actingAs($this->diner)->post(route('member.meetings.pay', ['slug' => $this->club->slug, 'id' => $this->meeting->id]))
            ->assertRedirect('https://checkout.stripe.test/cs_meeting_1');

        $this->assertSame(6500, $captured['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame((string) $rsvp->guests->first()->id, $captured['metadata']['guests']);

        $webhook = function () use ($captured) {
            $payload = json_encode(['id' => 'evt_'.uniqid(), 'object' => 'event', 'type' => 'checkout.session.completed', 'data' => ['object' => [
                'id' => 'cs_meeting_1', 'object' => 'checkout.session', 'payment_status' => 'paid', 'amount_total' => 6500, 'payment_intent' => 'pi_meeting_1', 'metadata' => $captured['metadata'],
            ]]]);
            $timestamp = time();

            return $this->call('POST', route('webhooks.stripe.club', ['clubId' => $this->club->id]), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", self::SECRET)], $payload);
        };

        $webhook()->assertSuccessful();
        $rsvp->refresh()->load('guests');
        $this->assertSame(['paid', 'online'], [$rsvp->payment_status, $rsvp->payment_method]);
        $this->assertSame('paid', $rsvp->guests->first()->payment_status);
        $this->assertSame(0.0, MeetingReplies::owed($this->meeting, $rsvp)['amount']);

        $paidAt = $rsvp->paid_at;
        $this->travel(5)->minutes();
        $webhook()->assertSuccessful();
        $this->assertEquals($paidAt, $rsvp->fresh()->paid_at);
    }

    public function test_dining_can_be_paid_with_paypal_and_is_taken_once_when_the_payer_comes_back(): void
    {
        config(['events.online_payments' => true]);
        ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => ClubPaymentMethod::PAYPAL, 'label' => 'PayPal', 'sort_order' => 0, 'is_active' => true, 'config' => ['paypal_client_id' => 'id', 'paypal_client_secret' => 'secret', 'paypal_webhook_id' => 'wh']]);
        $order = null;
        $this->mock(PayPalGateway::class, function (MockInterface $mock) use (&$order) {
            $mock->shouldReceive('createOrder')->once()->andReturnUsing(function ($method, $params) use (&$order) {
                $order = $params;

                return ['id' => 'ORDER-1', 'url' => 'https://paypal.test/approve/ORDER-1'];
            });
            $mock->shouldReceive('captureOrder')->once()->andReturnUsing(function () use (&$order) {
                return ['status' => 'COMPLETED', 'capture_id' => 'CAP-1', 'amount' => 65.0, 'currency' => 'GBP', 'custom_id' => $order['custom_id']];
            });
        });
        $summons = route('member.meetings.summons', ['slug' => $this->club->slug, 'id' => $this->meeting->id]);

        $this->actingAs($this->diner)->post(route('member.meetings.pay', ['slug' => $this->club->slug, 'id' => $this->meeting->id]), ['provider' => 'paypal'])
            ->assertRedirect('https://paypal.test/approve/ORDER-1');
        $this->assertSame(65.0, $order['amount']);

        $back = fn () => $this->get(route('member.meetings.paypal_return', ['slug' => $this->club->slug, 'id' => $this->meeting->id, 'token' => 'ORDER-1']));
        $back()->assertRedirect($summons.'?payment=success');
        $back()->assertRedirect($summons.'?payment=success');

        $rsvp = MeetingRsvp::where('user_id', $this->diner->id)->with('guests')->first();
        $this->assertSame(['paid', 'paypal', 'CAP-1'], [$rsvp->payment_status, $rsvp->online_provider, $rsvp->online_payment_id]);
        $this->assertSame('paid', $rsvp->guests->first()->payment_status);
    }

    public function test_a_place_paid_online_then_dropped_is_refunded_by_the_lodge_through_the_same_provider(): void
    {
        config(['events.online_payments' => true]);
        ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => ClubPaymentMethod::CARD, 'label' => 'Card', 'sort_order' => 0, 'is_active' => true, 'config' => ['stripe_secret_key' => 'sk_test_x', 'stripe_webhook_secret' => self::SECRET]]);
        $rsvp = MeetingRsvp::where('user_id', $this->diner->id)->first();
        $rsvp->forceFill(['payment_status' => 'paid', 'online_provider' => 'stripe', 'online_payment_id' => 'pi_dropped'])->save();
        $rsvp->update(['attendance_status' => 'attending_meeting_only']);

        $owner = User::factory()->create();
        $this->club->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $secretary = User::factory()->create();
        $this->club->users()->attach($secretary->id, ['role' => 'coach', 'status' => 'active']);
        $refund = ['kind' => 'member', 'diner_id' => $rsvp->id];
        $url = route('admin.meetings.dining.refund', ['clubSlug' => $this->club->slug, 'id' => $this->meeting->id]);

        $this->mock(StripeGateway::class)->shouldReceive('refund')->once()->withArgs(fn ($method, $intent, $minor) => $intent === 'pi_dropped' && $minor === 3000)->andReturn('re_1');

        $this->actingAs($secretary)->get(route('admin.meetings.dining', ['clubSlug' => $this->club->slug, 'id' => $this->meeting->id]))
            ->assertInertia(fn ($page) => $page->where('refundable.0.name', 'Dan Diner')->where('refundable.0.online', true)->where('canRefund', false));
        $this->actingAs($secretary)->post($url, $refund)->assertForbidden();

        $this->actingAs($owner)->post($url, $refund)->assertSessionHasNoErrors();
        $this->assertSame('refunded', $rsvp->fresh()->payment_status);
    }

    public function test_the_caterer_gets_the_count_menu_and_dietary_needs_but_not_who_is_away_or_unpaid(): void
    {
        $this->club->update(['settings' => [...($this->club->settings ?? []), 'caterer_email' => 'kitchen@example.test']]);
        $this->meeting->update(['festive_board_menu' => 'Soup, beef, crumble']);
        Member::create(['club_id' => $this->club->id, 'user_id' => $this->absent->id, 'first_name' => 'Abe', 'last_name' => 'Absent', 'masonic_rank' => 'WBro', 'membership_status' => 'active']);
        $this->meeting->forceFill(['replies_requested_at' => now(), 'last_call_sent_at' => now()])->save();

        $this->runAt('2026-12-08 09:00');

        Mail::assertQueued(MeetingNumbersMail::class, fn (MeetingNumbersMail $mail) => $mail->hasTo('secretary@example.test') && str_starts_with($mail->numbers['apologies'][0]['name'], 'WBro Abe Absent'));
        Mail::assertQueued(MeetingNumbersMail::class, function (MeetingNumbersMail $mail) {
            $html = $mail->render();

            return $mail->forCaterer && $mail->hasTo('kitchen@example.test') && str_contains($html, 'Dining: 2')
                && str_contains($html, 'Soup, beef, crumble') && str_contains($html, 'Vegetarian')
                && ! str_contains($html, 'Absent') && ! str_contains($html, 'Silent') && ! str_contains($html, 'unpaid');
        });

        // A reply that does not change the count is not passed on to the caterer.
        $replies = app(MeetingReplies::class);
        $rsvp = $replies->rsvpFor($this->meeting, $this->silent);
        $before = MeetingReplies::before($rsvp);
        $rsvp->update(['attendance_status' => 'apologies']);
        $replies->afterChange($this->meeting->fresh(), $rsvp, $before);

        Mail::assertQueued(MeetingNumbersMail::class, fn (MeetingNumbersMail $mail) => $mail->forCaterer, 1);
    }

    public function test_the_caterer_sheet_lists_who_is_dining_and_downloads_as_a_pdf(): void
    {
        $owner = User::factory()->create();
        $this->club->users()->attach($owner->id, ['role' => 'owner', 'status' => 'active']);
        $url = route('admin.meetings.dining.caterer', ['clubSlug' => $this->club->slug, 'id' => $this->meeting->id]);

        $this->actingAs($owner)->get($url)->assertOk()->assertSee('2 dining')->assertSee('Gus Guest')->assertSee('Vegetarian')->assertDontSee('Abe Absent');
        $this->actingAs($owner)->get($url.'?download=1')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->diner)->get($url)->assertForbidden();
    }
}
