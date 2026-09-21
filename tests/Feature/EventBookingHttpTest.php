<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventMenuItem;
use App\Models\EventTicketTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventBookingHttpTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $member;

    private Event $event;

    /** @var array<string, list<EventMenuItem>> */
    private array $menu = [];

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->member = User::factory()->create(['name' => 'Mia Member']);
        $this->club->users()->attach($this->member->id, ['role' => 'member', 'status' => 'active']);

        $this->event = Event::create([
            'club_id' => $this->club->id, 'title' => 'Dinner', 'slug' => 'dinner', 'starts_at' => now()->addWeeks(2), 'status' => 'upcoming',
            'visibility' => Visibility::Club, 'has_dining' => true, 'capacity' => 4, 'waitlist_enabled' => true, 'max_guests_per_booking' => 2,
        ]);

        foreach (['starter', 'main', 'dessert'] as $course) {
            foreach ([1, 2, 3] as $n) {
                $this->menu[$course][] = EventMenuItem::create(['event_id' => $this->event->id, 'category' => $course, 'name' => ucfirst($course)." {$n}", 'sort_order' => $n, 'is_vegan' => $course === 'main' && $n === 3]);
            }
        }
    }

    private function person(string $name, bool $guest = false, int $pick = 0): array
    {
        return [
            'name' => $name, 'is_guest' => $guest, 'attending_dining' => true, 'dietary_requirements' => $guest ? 'Vegan' : null,
            'starter_item_id' => $this->menu['starter'][$pick]->id, 'main_item_id' => $this->menu['main'][$pick]->id, 'dessert_item_id' => $this->menu['dessert'][$pick]->id,
        ];
    }

    private function book(array $attendees, string $status = 'attending')
    {
        return $this->actingAs($this->member)->post(route('member.rsvp', ['slug' => 'club-a', 'id' => $this->event->id]), ['attendance_status' => $status, 'attendees' => $attendees]);
    }

    public function test_the_events_page_sends_dishes_by_course_places_and_guest_limit(): void
    {
        $this->actingAs($this->member)->get(route('member.events', ['slug' => 'club-a']))->assertInertia(fn ($page) => $page
            ->component('Member/Events')
            ->where('events.0.capacity', 4)
            ->where('events.0.places_left', 4)
            ->where('events.0.max_guests', 2)
            ->has('events.0.menu.starter', 3)
            ->has('events.0.menu.main', 3)
            ->where('events.0.menu.main.2.is_vegan', true)
            ->where('events.0.user_rsvp', null));
    }

    public function test_a_member_books_for_themselves_and_a_guest_with_a_meal_each(): void
    {
        $this->book([$this->person('Mia Member'), $this->person('Gary Guest', true, 1)])->assertSessionHasNoErrors();

        $registration = $this->event->registrations()->with('attendees.main')->sole();
        $this->assertSame('attending', $registration->status);
        $this->assertSame(['Mia Member', 'Gary Guest'], $registration->attendees->pluck('name')->all());
        $this->assertSame(['Main 1', 'Main 2'], $registration->attendees->map(fn ($a) => $a->main->name)->all());
        $this->assertSame(2, $this->event->placesTaken());

        $this->actingAs($this->member)->get(route('member.events', ['slug' => 'club-a']))->assertInertia(fn ($page) => $page
            ->where('events.0.places_left', 2)
            ->where('events.0.user_rsvp.attendance_status', 'attending')
            ->has('events.0.user_rsvp.attendees', 2)
            ->where('events.0.user_rsvp.attendees.1.meal.main', 'Main 2'));
    }

    public function test_the_screen_gets_field_level_errors_for_missing_choices_and_too_many_guests(): void
    {
        $noMain = $this->person('Mia Member');
        $noMain['main_item_id'] = null;

        $this->book([$noMain])->assertSessionHasErrors('attendees.0.main_item_id');
        $this->book([$this->person('Mia Member'), $this->person('G1', true), $this->person('G2', true), $this->person('G3', true)])->assertSessionHasErrors('attendees');
        $this->assertSame(0, $this->event->registrations()->count());
    }

    public function test_a_booking_that_does_not_fit_joins_the_waiting_list_and_a_decline_frees_places(): void
    {
        $other = User::factory()->create();
        $this->club->users()->attach($other->id, ['role' => 'member', 'status' => 'active']);
        $this->actingAs($other)->post(route('member.rsvp', ['slug' => 'club-a', 'id' => $this->event->id]), ['attendance_status' => 'attending', 'attendees' => [$this->person('Other'), $this->person('G', true), $this->person('G2', true)]])->assertSessionHasNoErrors();

        $this->book([$this->person('Mia Member'), $this->person('Gary Guest', true)])->assertSessionHasNoErrors();
        $this->assertSame('waitlisted', $this->event->registrations()->where('user_id', $this->member->id)->value('status'));

        $this->actingAs($other)->post(route('member.rsvp', ['slug' => 'club-a', 'id' => $this->event->id]), ['attendance_status' => 'declined'])->assertSessionHasNoErrors();

        $this->assertSame('attending', $this->event->registrations()->where('user_id', $this->member->id)->value('status'));
    }

    public function test_drafts_and_events_outside_the_audience_cannot_be_booked(): void
    {
        $this->event->update(['status' => 'draft']);
        $this->book([$this->person('Mia Member')])->assertNotFound();

        $this->event->update(['status' => 'upcoming']);
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->post(route('member.rsvp', ['slug' => 'club-a', 'id' => $this->event->id]), ['attendance_status' => 'attending', 'attendees' => [['name' => 'X']]])->assertForbidden();
        $this->assertSame(0, $this->event->registrations()->count());
    }

    public function test_ticket_types_are_offered_by_audience_and_recorded_per_person(): void
    {
        $memberTier = EventTicketTier::create(['event_id' => $this->event->id, 'name' => 'Member', 'price' => 30, 'max_quantity' => 0, 'audience' => 'member']);
        $guestTier = EventTicketTier::create(['event_id' => $this->event->id, 'name' => 'Guest', 'price' => 45, 'max_quantity' => 0, 'audience' => 'guest']);

        $this->actingAs($this->member)->get(route('member.events', ['slug' => 'club-a']))->assertInertia(fn ($page) => $page
            ->has('events.0.ticket_tiers', 2)
            ->where('events.0.ticket_tiers.0.audience', 'member'));

        $this->book([$this->person('Mia Member'), $this->person('Gary Guest', true)])->assertSessionHasNoErrors();

        $attendees = $this->event->registrations()->sole()->attendees;
        $this->assertSame([$memberTier->id, $guestTier->id], $attendees->pluck('ticket_tier_id')->all());
    }
}
