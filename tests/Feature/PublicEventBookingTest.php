<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Mail\EventBookingMail;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventMenuItem;
use App\Models\EventPaymentMethod;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicEventBookingTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Event $event;

    /** @var array<string, list<EventMenuItem>> */
    private array $menu = [];

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active', 'settings' => ['bank_account_number' => '99887766']]);
        $this->event = Event::create([
            'club_id' => $this->club->id, 'title' => 'Open Dinner', 'slug' => 'open-dinner', 'starts_at' => now()->addWeeks(2), 'status' => 'upcoming',
            'visibility' => Visibility::Public, 'rsvp_audience' => Visibility::Public, 'allow_public_registration' => true, 'has_dining' => true, 'max_guests_per_booking' => 1,
        ]);

        foreach (['starter', 'main', 'dessert'] as $course) {
            $this->menu[$course][] = EventMenuItem::create(['event_id' => $this->event->id, 'category' => $course, 'name' => ucfirst($course).' A', 'sort_order' => 1]);
        }
    }

    private function payload(array $extra = []): array
    {
        return $extra + [
            'contact_name' => 'Pat Guest', 'contact_email' => 'Pat@Example.test', 'website' => '',
            'attendees' => [['name' => '', 'attending_dining' => true, 'starter_item_id' => $this->menu['starter'][0]->id, 'main_item_id' => $this->menu['main'][0]->id, 'dessert_item_id' => $this->menu['dessert'][0]->id]],
        ];
    }

    private function register(array $extra = [])
    {
        return $this->post(route('public.event.register', ['clubSlug' => 'club-a', 'eventSlug' => 'open-dinner']), $this->payload($extra));
    }

    public function test_anyone_can_see_a_public_event_but_the_page_carries_no_private_club_data(): void
    {
        $response = $this->get(route('public.event', ['clubSlug' => 'club-a', 'eventSlug' => 'open-dinner']))->assertOk();

        $response->assertInertia(fn ($page) => $page->component('Public/Event')->where('event.title', 'Open Dinner')->where('canBookAsGuest', true)->has('event.menu.main', 1)->missing('club.settings'));
        $this->assertStringNotContainsString('99887766', (string) $response->getContent());
    }

    public function test_drafts_and_members_only_events_are_not_found_for_the_public(): void
    {
        $this->event->update(['status' => 'draft']);
        $this->get(route('public.event', ['clubSlug' => 'club-a', 'eventSlug' => 'open-dinner']))->assertNotFound();

        $this->event->update(['status' => 'upcoming', 'visibility' => Visibility::Club, 'rsvp_audience' => Visibility::Club, 'allow_public_registration' => false]);
        $this->get(route('public.event', ['clubSlug' => 'club-a', 'eventSlug' => 'open-dinner']))->assertNotFound();
        $this->register()->assertNotFound();
    }

    public function test_an_outside_guest_books_without_an_account_and_gets_an_emailed_link(): void
    {
        Mail::fake();

        $this->register()->assertSessionHasNoErrors()->assertRedirect(route('public.event', ['clubSlug' => 'club-a', 'eventSlug' => 'open-dinner']));

        $registration = EventRegistration::with('attendees')->sole();
        $this->assertNull($registration->user_id);
        $this->assertSame('pat@example.test', $registration->contact_email);
        $this->assertSame('Pat Guest', $registration->attendees->sole()->name, 'a blank name falls back to the booker');
        $this->assertSame(0, User::count(), 'no account is created for the guest');

        Mail::assertSent(EventBookingMail::class, fn (EventBookingMail $mail) => $mail->hasTo('pat@example.test') && str_contains($mail->manageUrl, '/site/club-a/booking/'));
    }

    public function test_bots_that_fill_the_hidden_field_and_oversized_bookings_are_refused(): void
    {
        Mail::fake();

        $this->register(['website' => 'http://spam.example'])->assertSessionHasErrors('website');

        $tooMany = $this->payload();
        $tooMany['attendees'] = [$tooMany['attendees'][0], $tooMany['attendees'][0], $tooMany['attendees'][0]];
        $this->register($tooMany)->assertSessionHasErrors('attendees');

        $this->assertSame(0, EventRegistration::count());
        Mail::assertNothingSent();
    }

    public function test_booking_the_same_email_again_resends_a_fresh_link_instead_of_a_second_booking(): void
    {
        Mail::fake();

        $this->register();
        $first = null;
        Mail::assertSent(EventBookingMail::class, function (EventBookingMail $mail) use (&$first) {
            $first = $mail->manageUrl;

            return true;
        });

        $this->register(['contact_name' => 'Someone Else']);

        $this->assertSame(1, EventRegistration::count());
        Mail::assertSent(EventBookingMail::class, 2);

        $second = null;
        Mail::assertSent(EventBookingMail::class, function (EventBookingMail $mail) use (&$second, $first) {
            $second = $mail->manageUrl !== $first ? $mail->manageUrl : $second;

            return true;
        });

        $this->assertNotNull($second);
        $this->get($first)->assertInertia(fn ($page) => $page->where('expired', true));
        $this->get($second)->assertInertia(fn ($page) => $page->where('expired', false)->where('contactName', 'Pat Guest'));
    }

    public function test_the_private_link_shows_only_that_booking_and_can_cancel_it(): void
    {
        Mail::fake();
        $this->register();
        $link = null;
        Mail::assertSent(EventBookingMail::class, function (EventBookingMail $mail) use (&$link) {
            $link = $mail->manageUrl;

            return true;
        });
        EventRegistration::create(['event_id' => $this->event->id, 'contact_name' => 'Someone Private', 'contact_email' => 'private@example.test', 'status' => 'attending'])
            ->attendees()->create(['name' => 'Someone Private', 'dietary_requirements' => 'Secret allergy']);

        $html = (string) $this->get($link)->assertOk()->assertInertia(fn ($page) => $page->where('event.user_rsvp.attendance_status', 'attending')->has('event.user_rsvp.attendees', 1))->getContent();
        $this->assertStringNotContainsString('Someone Private', $html);
        $this->assertStringNotContainsString('Secret allergy', $html);

        $this->post($link.'/cancel')->assertSessionHasNoErrors();
        $this->assertSame('cancelled', EventRegistration::where('contact_email', 'pat@example.test')->value('status'));
    }

    public function test_a_wrong_or_other_clubs_token_never_opens_a_booking(): void
    {
        Mail::fake();
        $this->register();
        $token = basename(parse_url($this->tokenLink(), PHP_URL_PATH));
        $other = Club::create(['club_type_id' => $this->club->club_type_id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);

        $this->get("/site/club-a/booking/{$token}x")->assertInertia(fn ($page) => $page->where('expired', true));
        $this->get('/site/club-a/booking/short')->assertInertia(fn ($page) => $page->where('expired', true));
        $this->get("/site/{$other->slug}/booking/{$token}")->assertInertia(fn ($page) => $page->where('expired', true));
        $this->post("/site/{$other->slug}/booking/{$token}/cancel")->assertNotFound();
        $this->assertSame('attending', EventRegistration::sole()->status);
    }

    public function test_a_checked_in_guest_cannot_cancel_and_a_full_event_takes_no_more_bookings(): void
    {
        Mail::fake();
        $this->register();
        EventRegistration::sole()->attendees()->update(['checked_in_at' => now()]);
        $token = basename(parse_url($this->tokenLink(), PHP_URL_PATH));

        $this->post("/site/club-a/booking/{$token}/cancel")->assertSessionHasErrors('registration');
        $this->assertSame('attending', EventRegistration::sole()->status);

        $this->event->update(['capacity' => 1]);
        $this->register(['contact_email' => 'second@example.test'])->assertSessionHasErrors('capacity');
        $this->assertSame(1, EventRegistration::count());
    }

    private function tokenLink(): string
    {
        $link = '';
        Mail::assertSent(EventBookingMail::class, function (EventBookingMail $mail) use (&$link) {
            $link = $mail->manageUrl;

            return true;
        });

        return $link;
    }

    public function test_the_confirmation_email_carries_the_price_bank_details_and_reference(): void
    {
        Mail::fake();
        $this->event->update(['requires_payment' => true, 'price' => 40, 'has_dining' => false, 'booking_fee_type' => 'fixed', 'booking_fee_amount' => 2, 'booking_fee_label' => 'Admin fee']);
        $method = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => 'bank_transfer', 'label' => 'Bank transfer', 'config' => ['account_name' => 'Club A', 'sort_code' => '20-00-00', 'account_number' => '12345678', 'reference_prefix' => 'OPEN']]);
        $option = EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $method->id, 'is_enabled' => true]);

        $this->register(['payment_method' => $option->id, 'attendees' => [['name' => '']]])->assertSessionHasNoErrors();

        $registration = EventRegistration::sole();
        $this->assertSame('42.00', $registration->total);

        Mail::assertSent(EventBookingMail::class, function (EventBookingMail $mail) use ($registration) {
            $html = $mail->render();

            return str_contains($html, '42.00') && str_contains($html, '12345678') && str_contains($html, $registration->payment_reference) && str_contains($html, 'admin fee');
        });
    }
}
