<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Mail\EventGuestBookingMail;
use App\Mail\EventPaymentReminderMail;
use App\Models\Club;
use App\Models\ClubEmailTemplate;
use App\Models\ClubPaymentMethod;
use App\Models\ClubType;
use App\Models\DefaultEmailTemplate;
use App\Models\Event;
use App\Models\EventPaymentMethod;
use App\Models\EventRegistration;
use App\Models\User;
use App\Services\Events\EventRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubEmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Club $otherClub;

    private User $admin;

    private EventRegistration $registration;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->otherClub = Club::create(['club_type_id' => $type->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);
        $this->admin = $this->join($this->club, 'admin');

        $this->registration = $this->book($this->club, 'a');
    }

    private function join(Club $club, string $role): User
    {
        $user = User::factory()->create();
        $club->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    private function book(Club $club, string $slug): EventRegistration
    {
        $event = Event::create(['club_id' => $club->id, 'title' => 'Dinner '.$slug, 'slug' => 'dinner-'.$slug, 'starts_at' => now()->addDays(10), 'status' => 'upcoming', 'visibility' => Visibility::Club, 'requires_payment' => true, 'price' => 40]);
        $bank = ClubPaymentMethod::create(['club_id' => $club->id, 'type' => 'bank_transfer', 'label' => 'Bank', 'config' => ['sort_code' => '20-00-00', 'account_number' => '12345678']]);
        $option = EventPaymentMethod::create(['event_id' => $event->id, 'payment_method_id' => $bank->id, 'is_enabled' => true]);

        return app(EventRegistrationService::class)->register($event, $this->join($club, 'member'), ['payment_method' => $option->id, 'attendees' => [['name' => 'Mia']]]);
    }

    private function save(string $key, array $data, ?User $as = null, string $slug = 'club-a')
    {
        return $this->actingAs($as ?? $this->admin)->put(route('admin.email_templates.update', ['clubSlug' => $slug, 'key' => $key]), $data);
    }

    public function test_a_lodges_own_wording_is_used_for_that_lodge_only_and_can_be_reset(): void
    {
        $mine = $this->registration;
        $theirs = $this->book($this->otherClub, 'b');

        $this->save('event_payment_reminder', ['subject' => 'Lodge A says pay {{event_title}}', 'body_html' => '<p>Dear {{contact_name}}, owed {{amount_due}}</p>'])->assertSessionHasNoErrors();

        $this->assertSame('Lodge A says pay Dinner a', (new EventPaymentReminderMail($mine->fresh()))->envelope()->subject);
        $this->assertStringContainsString('Dear', (new EventPaymentReminderMail($mine->fresh()))->render());
        $this->assertSame('Payment reminder: Dinner b', (new EventPaymentReminderMail($theirs->fresh()))->envelope()->subject, 'another lodge keeps the default');
        $this->assertSame('Payment reminder: {{event_title}}', DefaultEmailTemplate::where('template_key', 'event_payment_reminder')->value('subject'));

        $this->actingAs($this->admin)->delete(route('admin.email_templates.destroy', ['clubSlug' => 'club-a', 'key' => 'event_payment_reminder']))->assertRedirect();
        $this->assertSame('Payment reminder: Dinner a', (new EventPaymentReminderMail($mine->fresh()))->envelope()->subject);
        $this->assertSame(0, ClubEmailTemplate::count());
    }

    public function test_the_lodge_page_lists_the_event_emails_and_marks_the_changed_ones(): void
    {
        $this->save('event_refund', ['subject' => 'Money back', 'body_html' => '<p>Refunded {{amount_refunded}}</p>']);

        $this->actingAs($this->admin)->get(route('admin.email_templates.index', ['clubSlug' => 'club-a']))->assertInertia(fn ($page) => $page
            ->component('Admin/EmailTemplates/Index')
            ->has('templates', 7)
            ->where('templates', fn ($templates) => collect($templates)->firstWhere('key', 'event_refund')['customised'] === true
                && collect($templates)->firstWhere('key', 'event_refund')['subject'] === 'Money back'
                && collect($templates)->firstWhere('key', 'event_payment_reminder')['customised'] === false));
    }

    public function test_unsafe_html_is_removed_and_placeholders_in_links_survive(): void
    {
        $this->save('event_booking_confirmation', [
            'subject' => 'Booked',
            'body_html' => '<p onclick="steal()">Hi</p><script>alert(1)</script><a href="javascript:alert(2)">bad</a><a href="{{manage_url}}">Manage</a><img src="https://x.test/a.png" onerror="x()">',
        ])->assertSessionHasNoErrors();

        $body = ClubEmailTemplate::sole()->body_html;
        $this->assertStringNotContainsString('script', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('onerror', $body);
        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringContainsString('href="{{manage_url}}"', $body);
    }

    public function test_unknown_placeholders_and_a_missing_manage_link_are_refused(): void
    {
        $this->save('event_payment_reminder', ['subject' => 'Pay', 'body_html' => '<p>{{contact_name}} {{secret_thing}}</p>'])->assertSessionHasErrors('body_html');
        $this->save('event_guest_confirmation', ['subject' => 'Hi {{bank_details}}', 'body_html' => '<p>Hi</p>'])->assertSessionHasErrors('body_html');
        $this->save('event_booking_confirmation', ['subject' => 'Booked', 'body_html' => '<p>No link here</p>'])->assertSessionHasErrors('body_html');
        $this->save('event_payment_reminder', ['subject' => 'Pay', 'body_html' => '<script>x()</script>'])->assertSessionHasErrors('body_html');
        $this->save('account_invitation', ['subject' => 'x', 'body_html' => '<p>x</p>'])->assertNotFound();

        $this->assertSame(0, ClubEmailTemplate::count());
    }

    public function test_the_guest_email_can_never_gain_payment_details_even_from_a_lodge_template(): void
    {
        $guest = $this->registration->attendees->first();
        $this->registration->attendees()->create(['name' => 'Gary Guest', 'is_guest' => true, 'email' => 'gary@example.test', 'attending_dining' => false, 'price' => 0]);
        $guest = $this->registration->fresh()->attendees->firstWhere('is_guest', true);

        $this->save('event_guest_confirmation', ['subject' => 'Hi {{guest_name}}', 'body_html' => '<p>{{guest_name}} {{booked_by}}</p>'])->assertSessionHasNoErrors();
        $html = (new EventGuestBookingMail($this->registration->event, $this->registration, $guest))->render();

        $this->assertStringContainsString('Gary Guest', $html);
        $this->assertStringNotContainsString($this->registration->payment_reference, $html);
        $this->assertStringNotContainsString('12345678', $html);
    }

    public function test_the_test_send_goes_only_to_the_person_asking_with_placeholders_shown_by_name(): void
    {
        $this->actingAs($this->admin)->post(route('admin.email_templates.test', ['clubSlug' => 'club-a', 'key' => 'event_refund']), ['subject' => 'Hi {{contact_name}}', 'body_html' => '<p>Hello {{contact_name}}</p>'])->assertSessionHas('success');

        $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame('[Test] Hi [contact_name]', $message->getSubject());
        $this->assertSame([$this->admin->email], array_map(fn ($a) => $a->getAddress(), $message->getTo()));
        $this->assertStringContainsString('Hello [contact_name]', $message->getHtmlBody());
    }

    public function test_only_admins_of_the_lodge_can_reword_its_emails(): void
    {
        $payload = ['subject' => 'Hi', 'body_html' => '<p>Hi {{contact_name}}</p>'];

        $this->save('event_refund', $payload, $this->join($this->club, 'member'))->assertForbidden();
        $this->save('event_refund', $payload, $this->join($this->otherClub, 'admin'))->assertForbidden();
        $this->actingAs($this->join($this->club, 'member'))->get(route('admin.email_templates.index', ['clubSlug' => 'club-a']))->assertForbidden();
        $this->actingAs($this->join($this->club, 'member'))->delete(route('admin.email_templates.destroy', ['clubSlug' => 'club-a', 'key' => 'event_refund']))->assertForbidden();

        $this->assertSame(0, ClubEmailTemplate::count());
    }
}
