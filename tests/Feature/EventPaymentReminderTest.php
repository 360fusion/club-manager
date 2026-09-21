<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Mail\EventPaymentReminderMail;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Events\EventPaymentService;
use App\Services\Events\EventRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventPaymentReminderTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Event $event;

    private EventPaymentMethod $later;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->event = Event::create(['club_id' => $this->club->id, 'title' => 'Dinner', 'slug' => 'dinner', 'starts_at' => now()->addDays(10), 'status' => 'upcoming', 'visibility' => Visibility::Club, 'requires_payment' => true, 'price' => 40]);
        $bank = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => 'bank_transfer', 'label' => 'Bank', 'config' => ['sort_code' => '20-00-00', 'account_number' => '12345678']]);
        EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $bank->id, 'is_enabled' => true]);
        $method = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => 'pay_later', 'label' => 'Pay later', 'due_days' => 2, 'due_basis' => 'before_event']);
        $this->later = EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $method->id, 'is_enabled' => true]);
        $this->member = User::factory()->create();
        $this->club->users()->attach($this->member->id, ['role' => 'member', 'status' => 'active']);
    }

    private function book(?User $user = null): EventRegistration
    {
        return app(EventRegistrationService::class)->register($this->event, $user ?? $this->member, ['payment_method' => $this->later->id, 'attendees' => [['name' => 'Mia']]]);
    }

    public function test_someone_due_soon_is_reminded_in_the_app_and_by_email_with_bank_details(): void
    {
        Mail::fake();
        $this->book();
        $this->event->update(['starts_at' => now()->addDays(4)]);
        EventRegistration::query()->update(['due_at' => now()->addDays(2)]);

        $this->artisan('app:send-event-payment-reminders')->assertSuccessful();

        $this->assertCount(1, $this->member->fresh()->notifications);
        $this->assertSame('payment', $this->member->notifications->first()->data['category']);
        Mail::assertQueued(EventPaymentReminderMail::class, fn (EventPaymentReminderMail $mail) => $mail->hasTo($this->member->email) && str_contains($mail->render(), '12345678') && str_contains($mail->render(), '40.00'));
        $this->assertNotNull(EventRegistration::sole()->reminder_sent_at);
    }

    public function test_nobody_is_reminded_too_early_twice_in_a_week_after_paying_or_after_the_event_is_off(): void
    {
        Mail::fake();
        $registration = $this->book();

        EventRegistration::query()->update(['due_at' => now()->addDays(20)]);
        $this->artisan('app:send-event-payment-reminders');
        Mail::assertNothingQueued();

        EventRegistration::query()->update(['due_at' => now()->addDay()]);
        $this->artisan('app:send-event-payment-reminders');
        $this->artisan('app:send-event-payment-reminders');
        Mail::assertQueued(EventPaymentReminderMail::class, 1);

        EventRegistration::query()->update(['reminder_sent_at' => now()->subDays(8)]);
        app(EventPaymentService::class)->markPaid($registration->fresh(), null);
        $this->artisan('app:send-event-payment-reminders');
        Mail::assertQueued(EventPaymentReminderMail::class, 1);

        EventRegistration::query()->update(['payment_status' => 'unpaid', 'amount_paid' => 0, 'reminder_sent_at' => null]);
        $this->event->update(['status' => 'cancelled']);
        $this->artisan('app:send-event-payment-reminders');
        Mail::assertQueued(EventPaymentReminderMail::class, 1);
    }
}
