<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Mail\EventGuestBookingMail;
use App\Mail\EventPaymentReminderMail;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubType;
use App\Models\DefaultEmailTemplate;
use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\EventRegistration;
use App\Models\User;
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
}
