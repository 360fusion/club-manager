<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventMenuItem;
use App\Models\EventRegistration;
use App\Models\EventTicketTier;
use App\Models\User;
use App\Services\Events\EventRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EventRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private EventRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->service = app(EventRegistrationService::class);
    }

    private function member(string $name = 'Member One'): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->club->users()->attach($user->id, ['role' => 'member', 'status' => 'active']);

        return $user;
    }

    private function event(array $attributes = []): Event
    {
        return Event::create($attributes + [
            'club_id' => $this->club->id, 'title' => 'Dinner', 'slug' => 'dinner-'.uniqid(),
            'starts_at' => now()->addWeek(), 'status' => 'upcoming', 'visibility' => Visibility::Club, 'requires_payment' => false,
        ]);
    }

    /**
     * @return array{starter: list<EventMenuItem>, main: list<EventMenuItem>, dessert: list<EventMenuItem>}
     */
    private function menu(Event $event): array
    {
        $menu = [];

        foreach (['starter', 'main', 'dessert'] as $course) {
            foreach (range(1, 3) as $n) {
                $menu[$course][] = EventMenuItem::create(['event_id' => $event->id, 'category' => $course, 'name' => ucfirst($course)." {$n}", 'sort_order' => $n]);
            }
        }

        return $menu;
    }

    public function test_a_member_books_with_guests_and_a_meal_choice_for_each_person(): void
    {
        $event = $this->event(['has_dining' => true]);
        $menu = $this->menu($event);
        $user = $this->member();

        $registration = $this->service->register($event, $user, ['status' => 'attending', 'attendees' => [
            ['name' => 'Member One', 'attending_dining' => true, 'starter_item_id' => $menu['starter'][0]->id, 'main_item_id' => $menu['main'][1]->id, 'dessert_item_id' => $menu['dessert'][2]->id, 'dietary_requirements' => 'Nut allergy'],
            ['name' => 'Guest A', 'is_guest' => true, 'attending_dining' => true, 'starter_item_id' => $menu['starter'][1]->id, 'main_item_id' => $menu['main'][0]->id, 'dessert_item_id' => $menu['dessert'][0]->id],
        ]]);

        $this->assertSame('attending', $registration->status);
        $this->assertCount(2, $registration->attendees);
        $this->assertSame($user->id, $registration->attendees[0]->user_id);
        $this->assertTrue($registration->attendees[1]->is_guest);
        $this->assertSame('Main 2', $registration->attendees[0]->mealSummary()['main']);
        $this->assertSame(2, $event->placesTaken());
    }

    public function test_meal_choices_must_be_complete_and_come_from_this_events_menu(): void
    {
        $event = $this->event(['has_dining' => true]);
        $menu = $this->menu($event);
        $other = $this->menu($this->event(['has_dining' => true, 'slug' => 'other']));
        $user = $this->member();
        $person = fn (array $extra) => ['status' => 'attending', 'attendees' => [['name' => 'Member One', 'attending_dining' => true, ...$extra]]];

        try {
            $this->service->register($event, $user, $person(['starter_item_id' => $menu['starter'][0]->id]));
            $this->fail('A missing main and dessert should be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('attendees.0.main_item_id', $e->errors());
        }

        try {
            $this->service->register($event, $user, $person(['starter_item_id' => $other['starter'][0]->id, 'main_item_id' => $menu['main'][0]->id, 'dessert_item_id' => $menu['dessert'][0]->id]));
            $this->fail("Another event's dish should be refused.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('attendees.0.starter_item_id', $e->errors());
        }

        $this->assertSame(0, EventRegistration::count());
    }

    public function test_guests_are_limited_and_must_be_named(): void
    {
        $event = $this->event(['max_guests_per_booking' => 1]);
        $user = $this->member();
        $guest = fn (string $name) => ['name' => $name, 'is_guest' => true];

        $this->expectException(ValidationException::class);
        $this->service->register($event, $user, ['attendees' => [['name' => 'Me'], $guest('G1'), $guest('G2')]]);
    }

    public function test_a_full_event_refuses_or_waitlists_and_cancelling_promotes_the_waitlist(): void
    {
        $event = $this->event(['capacity' => 3, 'waitlist_enabled' => true]);
        $a = $this->member('Alice');
        $b = $this->member('Bob');
        $c = $this->member('Cara');

        $first = $this->service->register($event, $a, ['attendees' => [['name' => 'Alice'], ['name' => 'G', 'is_guest' => true]]]);
        $second = $this->service->register($event, $b, ['attendees' => [['name' => 'Bob'], ['name' => 'G2', 'is_guest' => true]]]);
        $third = $this->service->register($event, $c, ['attendees' => [['name' => 'Cara']]]);

        $this->assertSame('attending', $first->status);
        $this->assertSame('waitlisted', $second->status, 'two more people would exceed 3 places');
        $this->assertSame('attending', $third->status, 'a single place still fits');
        $this->assertSame(0, $event->placesLeft());

        $this->service->cancel($first);

        $this->assertSame('attending', $second->fresh()->status, 'the waitlisted booking moves up once two places are free');
        $this->assertSame('cancelled', $first->fresh()->status);
    }

    public function test_a_full_event_without_a_waitlist_says_so(): void
    {
        $event = $this->event(['capacity' => 1]);
        $this->service->register($event, $this->member('Alice'), ['attendees' => [['name' => 'Alice']]]);

        try {
            $this->service->register($event, $this->member('Bob'), ['attendees' => [['name' => 'Bob']]]);
            $this->fail('The event is full.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('capacity', $e->errors());
        }
    }

    public function test_changing_a_booking_replaces_it_and_uses_its_own_places(): void
    {
        $event = $this->event(['capacity' => 2]);
        $user = $this->member();

        $this->service->register($event, $user, ['attendees' => [['name' => 'Me'], ['name' => 'G1', 'is_guest' => true]]]);
        $changed = $this->service->register($event, $user, ['attendees' => [['name' => 'Me'], ['name' => 'G1', 'is_guest' => true]]]);

        $this->assertSame(1, EventRegistration::count());
        $this->assertCount(2, $changed->attendees);
        $this->assertSame(2, $event->placesTaken());
    }

    public function test_a_checked_in_booking_cannot_be_changed(): void
    {
        $event = $this->event();
        $user = $this->member();
        $registration = $this->service->register($event, $user, ['attendees' => [['name' => 'Me']]]);
        $registration->attendees()->update(['checked_in_at' => now()]);

        $this->expectException(ValidationException::class);
        $this->service->register($event, $user, ['attendees' => [['name' => 'Me']]]);
    }

    public function test_ticket_types_follow_the_audience_of_each_person(): void
    {
        $event = $this->event();
        $member = EventTicketTier::create(['event_id' => $event->id, 'name' => 'Member', 'price' => 20, 'max_quantity' => 0, 'audience' => 'member']);
        $guestTier = EventTicketTier::create(['event_id' => $event->id, 'name' => 'Guest', 'price' => 30, 'max_quantity' => 0, 'audience' => 'guest']);
        $user = $this->member();

        $registration = $this->service->register($event, $user, ['attendees' => [['name' => 'Me'], ['name' => 'G', 'is_guest' => true]]]);

        $this->assertSame($member->id, $registration->attendees[0]->ticket_tier_id, 'the only eligible tier is chosen automatically');
        $this->assertSame($guestTier->id, $registration->attendees[1]->ticket_tier_id);
        $this->assertSame(1, $member->fresh()->sold_quantity);

        $this->expectException(ValidationException::class);
        $this->service->register($event, $this->member('Other'), ['attendees' => [['name' => 'Other', 'ticket_tier_id' => $guestTier->id]]]);
    }

    public function test_drafts_cancelled_events_and_closed_bookings_refuse_registrations(): void
    {
        $user = $this->member();

        foreach ([['status' => 'draft'], ['status' => 'cancelled'], ['booking_cutoff_days' => 30]] as $i => $attributes) {
            try {
                $this->service->register($this->event($attributes + ['slug' => "closed-{$i}"]), $user, ['attendees' => [['name' => 'Me']]]);
                $this->fail('That event should be closed.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_public_guests_can_book_only_public_events_that_allow_it_and_get_a_token(): void
    {
        $closed = $this->event(['visibility' => Visibility::Public, 'allow_public_registration' => false]);
        $clubOnly = $this->event(['visibility' => Visibility::Club, 'allow_public_registration' => true, 'slug' => 'club-only']);
        $open = $this->event(['visibility' => Visibility::Public, 'allow_public_registration' => true, 'slug' => 'open']);
        $data = ['contact_name' => 'Pat Guest', 'contact_email' => 'pat@example.test', 'attendees' => [['name' => 'Pat Guest']]];

        foreach ([$closed, $clubOnly] as $event) {
            try {
                $this->service->register($event, null, $data);
                $this->fail('Public booking should be refused.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $registration = $this->service->register($open, null, $data);

        $this->assertNull($registration->user_id);
        $this->assertNotEmpty($registration->plainToken);
        $this->assertSame(hash('sha256', $registration->plainToken), $registration->token_hash);
    }

    public function test_existing_member_replies_are_copied_into_registrations_by_the_migration(): void
    {
        $event = $this->event();
        $user = $this->member('Old Reply');
        $tier = EventTicketTier::create(['event_id' => $event->id, 'name' => 'General', 'price' => 10, 'max_quantity' => 0]);

        DB::table('event_user')->insert([
            'event_id' => $event->id, 'user_id' => $user->id, 'attendance_status' => 'attending', 'attending_dining' => true,
            'menu_selections' => json_encode(['starter' => 'Soup', 'main' => 'Beef', 'dessert' => 'Tart']), 'dietary_requirements' => 'Vegan',
            'payment_status' => 'paid', 'amount_paid' => 10, 'ticket_tier_id' => $tier->id, 'checked_in_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        (require database_path('migrations/2026_09_21_164733_copy_event_user_into_registrations.php'))->up();
        (require database_path('migrations/2026_09_21_164733_copy_event_user_into_registrations.php'))->up();

        $registration = EventRegistration::with('attendees')->where('event_id', $event->id)->sole();

        $this->assertSame('attending', $registration->status);
        $this->assertSame('paid', $registration->payment_status);
        $this->assertSame('10.00', $registration->amount_paid);
        $attendee = $registration->attendees->sole();
        $this->assertTrue($attendee->attending_dining);
        $this->assertSame('Beef', $attendee->mealSummary()['main']);
        $this->assertSame('Vegan', $attendee->dietary_requirements);
        $this->assertSame($tier->id, $attendee->ticket_tier_id);
        $this->assertNotNull($attendee->checked_in_at);
    }
}
