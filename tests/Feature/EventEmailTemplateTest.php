<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Mail\EventGuestBookingMail;
use App\Mail\EventPaymentReceivedMail;
use App\Mail\EventPaymentReminderMail;
use App\Mail\EventPlaceAvailableMail;
use App\Mail\EventRefundMail;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubType;
use App\Models\DefaultEmailTemplate;
use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Events\EventPaymentService;
use App\Services\Events\EventRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EventEmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private EventRegistration $registration;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->event = Event::create(['club_id' => $club->id, 'title' => 'Dinner', 'slug' => 'dinner', 'starts_at' => now()->addDays(10), 'status' => 'upcoming', 'visibility' => Visibility::Club, 'requires_payment' => true, 'price' => 40]);
        $bank = ClubPaymentMethod::create(['club_id' => $club->id, 'type' => 'bank_transfer', 'label' => 'Bank', 'config' => ['sort_code' => '20-00-00', 'account_number' => '12345678']]);
        $option = EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $bank->id, 'is_enabled' => true]);
        $member = User::factory()->create();
        $club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);
        $this->registration = app(EventRegistrationService::class)->register($this->event, $member, ['payment_method' => $option->id, 'attendees' => [['name' => 'Mia Member'], ['name' => 'Gary Guest', 'is_guest' => true, 'email' => 'gary@example.test']]]);
    }

    public function test_the_event_emails_are_seeded_and_use_the_superadmin_template_wording(): void
    {
        foreach (['event_booking_confirmation', 'event_guest_confirmation', 'event_payment_reminder'] as $key) {
            $this->assertDatabaseHas('default_email_templates', ['template_key' => $key]);
        }

        DefaultEmailTemplate::where('template_key', 'event_payment_reminder')->update([
            'subject' => 'Please pay for {{event_title}}',
            'body_html' => '<p>Hi {{contact_name}}, owed {{amount_due}}</p>{{payment_how}}',
        ]);

        $mail = new EventPaymentReminderMail($this->registration->fresh());

        $this->assertSame('Please pay for Dinner', $mail->envelope()->subject);
        $html = $mail->render();
        $this->assertStringContainsString('owed £80.00', $html);
        $this->assertStringContainsString('12345678', $html);
    }

    public function test_placeholder_values_are_escaped_and_subjects_cannot_carry_line_breaks(): void
    {
        $this->event->update(['title' => "<b>Dinner</b>\nBcc: x@example.test"]);
        DefaultEmailTemplate::where('template_key', 'event_payment_reminder')->update(['subject' => 'Pay {{event_title}}', 'body_html' => '<p>{{event_title}}</p>']);

        $mail = new EventPaymentReminderMail($this->registration->fresh());

        $this->assertStringNotContainsString("\n", $mail->envelope()->subject);
        $this->assertStringNotContainsString('<b>', $mail->render());
    }

    public function test_the_built_in_wording_is_used_when_a_template_has_been_removed(): void
    {
        DefaultEmailTemplate::where('template_key', 'event_payment_reminder')->delete();

        $mail = new EventPaymentReminderMail($this->registration->fresh());

        $this->assertSame('Payment reminder: Dinner', $mail->envelope()->subject);
        $this->assertStringContainsString('A reminder to pay for Dinner', $mail->render());
    }

    public function test_the_guest_template_can_only_use_guest_safe_details(): void
    {
        $guest = $this->registration->attendees->firstWhere('is_guest', true);
        DefaultEmailTemplate::where('template_key', 'event_guest_confirmation')->update(['body_html' => '<p>{{guest_name}} {{payment_details}} {{manage_url}} {{booked_by}}</p>']);

        $html = (new EventGuestBookingMail($this->event, $this->registration, $guest))->render();

        $this->assertStringContainsString('Gary Guest {{payment_details}} {{manage_url}} '.$this->registration->contact_name, $html);
        $this->assertStringNotContainsString($this->registration->payment_reference, $html);
    }

    public function test_only_a_superadmin_can_send_a_test_of_a_template(): void
    {
        Mail::fake();
        $template = DefaultEmailTemplate::where('template_key', 'event_guest_confirmation')->first();
        $payload = ['subject' => 'Hello {{guest_name}}', 'body_html' => '<p>{{guest_name}}</p>'];

        $this->actingAs(User::factory()->create(['is_super_admin' => false]))->post(route('superadmin.email_templates.test', $template->id), $payload)->assertRedirect();
        $this->assertNull(session('success'));

        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin)->post(route('superadmin.email_templates.test', $template->id), $payload)->assertSessionHas('success');
    }

    public function test_a_payment_sends_the_booker_a_receipt_and_a_refund_tells_them_it_is_coming_back(): void
    {
        Mail::fake();
        $payments = app(EventPaymentService::class);

        $payments->markPaid($this->registration, null, 30.0);
        Mail::assertQueued(EventPaymentReceivedMail::class, fn ($mail) => $mail->hasTo($this->registration->contact_email) && str_contains($mail->render(), '£30.00') && str_contains($mail->render(), 'Part paid'));

        $payments->markPaid($this->registration->fresh(), null);
        Mail::assertQueued(EventPaymentReceivedMail::class, fn ($mail) => str_contains($mail->render(), 'Paid in full'));

        $payments->refund($this->registration->fresh(), null, 'Cannot attend', 20.0);
        Mail::assertQueued(EventRefundMail::class, fn ($mail) => $mail->hasTo($this->registration->contact_email) && str_contains($mail->render(), '£20.00'));
    }

    public function test_the_waiting_list_is_told_when_a_place_opens_but_only_the_booking_that_moved_up(): void
    {
        $this->event->update(['capacity' => 2, 'waitlist_enabled' => true]);
        $waiting = User::factory()->create();
        $this->event->club->users()->attach($waiting->id, ['role' => 'member', 'status' => 'active']);
        $second = app(EventRegistrationService::class)->register($this->event, $waiting, ['payment_method' => $this->event->paymentMethods()->value('id'), 'attendees' => [['name' => 'Wendy Waiting'], ['name' => 'Guest Two', 'is_guest' => true]]]);
        $this->assertSame('waitlisted', $second->status);

        Mail::fake();
        app(EventRegistrationService::class)->cancel($this->registration);

        Mail::assertQueued(EventPlaceAvailableMail::class, 1);
        Mail::assertQueued(EventPlaceAvailableMail::class, fn ($mail) => $mail->hasTo($waiting->email) && str_contains($mail->render(), 'now confirmed'));
    }

    public function test_the_new_event_emails_fall_back_to_built_in_wording_when_their_templates_are_removed_and_a_broken_mailer_never_blocks_a_payment(): void
    {
        DefaultEmailTemplate::whereIn('template_key', ['event_payment_received', 'event_refund', 'event_place_available'])->delete();

        $this->assertSame('Payment received: Dinner', (new EventPaymentReceivedMail($this->registration, 10.0))->envelope()->subject);
        $this->assertStringContainsString('Refund', (new EventRefundMail($this->registration, 10.0))->render());
        $this->assertSame('A place is available: Dinner', (new EventPlaceAvailableMail($this->registration))->envelope()->subject);

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('mail server down'));
        app(EventPaymentService::class)->markPaid($this->registration, null, 10.0);
        $this->assertSame('10.00', $this->registration->fresh()->amount_paid);
    }

    public function test_the_reminder_links_a_member_to_pay_online_but_a_guest_gets_no_link(): void
    {
        $mail = new EventPaymentReminderMail($this->registration->fresh());
        $this->assertStringNotContainsString('Pay online now', $mail->render(), 'no online option is set up on this event');
        $this->assertStringContainsString('{{pay_link}}', DefaultEmailTemplate::where('template_key', 'event_payment_reminder')->value('body_html'));
    }
}
