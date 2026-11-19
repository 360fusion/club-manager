<?php

namespace Tests\Feature\Journeys;

use App\Jobs\SendPushNotification;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\EventRegistration;
use App\Models\PushDevice;
use App\Models\User;
use App\Notifications\ClubNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Journeys C and D in tests/JOURNEYS.md: a booking paid later is chased and recorded by the treasurer, and a member
 * sets up the app on their phone, chooses what alerts them, and signs out on that phone only.
 */
class PaymentAndAppJourneyTest extends TestCase
{
    use RefreshDatabase;

    private Club $lodge;

    private User $tom;

    private User $treasurer;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake([SendPushNotification::class]);

        $this->lodge = Club::factory()->create(['name' => 'Harbour Lodge', 'slug' => 'harbour']);
        $this->tom = User::factory()->create(['name' => 'Tom Hart']);
        $this->treasurer = User::factory()->create(['name' => 'Tess Treasurer']);
        $this->lodge->users()->attach($this->tom->id, ['role' => 'member', 'status' => 'active']);
        $this->lodge->users()->attach($this->treasurer->id, ['role' => 'treasurer', 'status' => 'active']);

        $this->event = Event::create(['club_id' => $this->lodge->id, 'title' => 'Ladies Night', 'slug' => 'ladies-night', 'starts_at' => now()->addWeeks(3)->setTime(19, 0), 'status' => 'upcoming', 'requires_payment' => true, 'price' => 35]);
        $later = ClubPaymentMethod::create(['club_id' => $this->lodge->id, 'type' => ClubPaymentMethod::LATER, 'label' => 'Pay later', 'is_active' => true, 'due_days' => 7, 'due_basis' => 'before_event']);
        EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $later->id, 'is_enabled' => true]);
    }

    public function test_a_booking_paid_later_is_chased_on_the_phone_and_recorded_by_the_treasurer(): void
    {
        // Tom has the app but has switched off new-event alerts; payment alerts cannot be switched off.
        PushDevice::factory()->create(['user_id' => $this->tom->id]);
        $this->tom->forceFill(['push_muted' => [ClubNotification::EVENT]])->save();

        // C1: he books with pay later, so it is unpaid with a due date a week before.
        $this->actingAs($this->tom)->post(route('member.rsvp', ['slug' => 'harbour', 'id' => $this->event->id]), [
            'attendance_status' => 'attending',
            'payment_method' => $this->event->paymentMethods()->sole()->id,
            'attendees' => [['name' => 'Tom Hart']],
        ])->assertSessionHasNoErrors();
        $booking = EventRegistration::where('event_id', $this->event->id)->sole();
        $this->assertSame('unpaid', $booking->payment_status);
        $this->assertSame($this->event->starts_at->copy()->subDays(7)->toDateString(), $booking->due_at->toDateString());

        // C2: near the due date the reminder reaches his inbox and his phone.
        $this->travelTo($booking->due_at->copy()->subDays(2));
        $this->artisan('app:send-event-payment-reminders')->assertSuccessful();
        $this->assertStringStartsWith('Payment due: Ladies Night', $this->tom->notifications()->where('data->category', 'payment')->sole()->data['title']);
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->tom->id && str_starts_with($job->title, 'Payment due'));

        // C3: the treasurer records the payment; it is logged, and his ticket shows nothing to pay.
        $this->actingAs($this->treasurer)->post(route('admin.events.payment.paid', ['clubSlug' => 'harbour', 'id' => $this->event->id, 'registrationId' => $booking->id]))->assertSessionHasNoErrors();
        $this->assertSame('paid', $booking->fresh()->payment_status);
        $this->assertSame($this->treasurer->id, DB::table('event_payment_log')->where('registration_id', $booking->id)->value('user_id'));
        $this->actingAs($this->tom)->get(route('member.events.ticket', ['slug' => 'harbour', 'id' => $this->event->id]))
            ->assertInertia(fn ($page) => $page->where('booking.amount_due', 0));

        // A paid booking is not chased again.
        $this->travelTo($booking->due_at->copy()->subDay());
        $this->artisan('app:send-event-payment-reminders');
        $this->assertSame(1, $this->tom->notifications()->where('data->category', 'payment')->count());
    }

    public function test_a_member_sets_up_the_app_chooses_their_alerts_and_signs_out_on_one_phone(): void
    {
        $tablet = PushDevice::factory()->create(['user_id' => $this->tom->id, 'token' => 'toms-tablet']);
        $app = ['User-Agent' => 'Mozilla/5.0 (iPhone) ClubManagerApp'];

        // A2: signing in on the phone and allowing notifications saves it.
        $this->actingAs($this->tom)->withHeaders($app)->postJson(route('members.push_devices.store'), ['token' => 'toms-iphone', 'platform' => 'ios'])->assertOk();
        $this->assertSame(2, $this->tom->pushDevices()->count());

        // D1: in App settings he switches off new-event alerts.
        $this->actingAs($this->tom)->withHeaders($app)->get(route('members.app_settings'))->assertOk();
        $this->actingAs($this->tom)->post(route('members.push_settings.update'), ['muted' => [ClubNotification::EVENT]])->assertSessionHasNoErrors();

        $this->tom->refresh()->notify(ClubNotification::event($this->event, $this->lodge));
        Queue::assertNotPushed(SendPushNotification::class);
        $this->assertSame(1, $this->tom->notifications()->count(), 'It still reaches the inbox');

        $this->tom->notify(ClubNotification::membershipApproved($this->lodge));
        Queue::assertPushed(SendPushNotification::class, 1);

        // D4: signing out on the phone stops that phone's alerts; the tablet keeps them.
        $this->actingAs($this->tom)->withSession(['push_token' => 'toms-iphone'])->post('/logout');
        $this->assertSame(['toms-tablet'], $this->tom->pushDevices()->pluck('token')->all());
        $this->assertModelExists($tablet);
    }
}
