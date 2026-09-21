<?php

namespace Tests\Feature;

use App\Enums\Visibility;
use App\Models\Club;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\EventMenuItem;
use App\Models\User;
use App\Services\Events\EventPaymentService;
use App\Services\Events\EventRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventMealEditTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Event $event;

    private User $admin;

    private EventAttendee $attendee;

    /** @var array<string, list<EventMenuItem>> */
    private array $menu = [];

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->event = Event::create(['club_id' => $this->club->id, 'title' => 'Dinner', 'slug' => 'dinner', 'starts_at' => now()->addWeeks(2), 'status' => 'upcoming', 'visibility' => Visibility::Club, 'has_dining' => true, 'requires_payment' => true, 'price' => 30, 'dining_price' => 20]);

        foreach (['starter', 'main', 'dessert'] as $course) {
            foreach ([1, 2] as $n) {
                $this->menu[$course][] = EventMenuItem::create(['event_id' => $this->event->id, 'category' => $course, 'name' => ucfirst($course)." {$n}", 'sort_order' => $n]);
            }
        }

        $this->admin = User::factory()->create();
        $this->club->users()->attach($this->admin->id, ['role' => 'admin', 'status' => 'active']);
        $member = User::factory()->create();
        $this->club->users()->attach($member->id, ['role' => 'member', 'status' => 'active']);

        $registration = app(EventRegistrationService::class)->register($this->event, $member, ['attendees' => [[
            'name' => 'Mia', 'attending_dining' => true, 'starter_item_id' => $this->menu['starter'][0]->id, 'main_item_id' => $this->menu['main'][0]->id, 'dessert_item_id' => $this->menu['dessert'][0]->id,
        ]]]);
        $this->attendee = $registration->attendees->first();
    }

    private function edit(array $data, ?EventAttendee $attendee = null, ?User $as = null, ?int $eventId = null)
    {
        return $this->actingAs($as ?? $this->admin)->put(route('admin.events.attendees.meal', ['clubSlug' => 'club-a', 'id' => $eventId ?? $this->event->id, 'attendeeId' => ($attendee ?? $this->attendee)->id]), $data);
    }

    private function meal(array $overrides = []): array
    {
        return $overrides + ['attending_dining' => true, 'starter_item_id' => $this->menu['starter'][1]->id, 'main_item_id' => $this->menu['main'][1]->id, 'dessert_item_id' => $this->menu['dessert'][1]->id, 'dietary_requirements' => 'No nuts'];
    }

    public function test_an_organiser_can_change_dishes_and_dietary_notes_even_after_payment(): void
    {
        app(EventPaymentService::class)->markPaid($this->attendee->registration, $this->admin);

        $this->edit($this->meal())->assertSessionHasNoErrors();

        $attendee = $this->attendee->fresh();
        $this->assertSame($this->menu['main'][1]->id, $attendee->main_item_id);
        $this->assertSame('No nuts', $attendee->dietary_requirements);
        $this->assertSame('50.00', $attendee->registration->fresh()->total, 'the price is unchanged');
    }

    public function test_dishes_must_be_on_this_events_menu_and_in_the_right_course(): void
    {
        $other = Event::create(['club_id' => $this->club->id, 'title' => 'Other', 'slug' => 'other', 'starts_at' => now()->addWeek(), 'status' => 'upcoming', 'has_dining' => true]);
        $foreign = EventMenuItem::create(['event_id' => $other->id, 'category' => 'starter', 'name' => 'Elsewhere', 'sort_order' => 1]);

        $this->edit($this->meal(['starter_item_id' => $foreign->id]))->assertSessionHasErrors('attendees.0.starter_item_id');
        $this->edit($this->meal(['starter_item_id' => $this->menu['main'][0]->id]))->assertSessionHasErrors('attendees.0.starter_item_id');
        $this->edit($this->meal(['dessert_item_id' => null]))->assertSessionHasErrors('attendees.0.dessert_item_id');
        $this->assertSame($this->menu['starter'][0]->id, $this->attendee->fresh()->starter_item_id);
    }

    public function test_turning_dinner_off_reprices_an_unpaid_booking_but_is_refused_once_something_is_paid(): void
    {
        $this->assertSame('50.00', $this->attendee->registration->total);

        $this->edit(['attending_dining' => false])->assertSessionHasNoErrors();
        $attendee = $this->attendee->fresh();
        $this->assertFalse($attendee->attending_dining);
        $this->assertNull($attendee->main_item_id);
        $this->assertSame('30.00', $attendee->registration->fresh()->total);

        $this->edit($this->meal())->assertSessionHasNoErrors();
        $this->assertSame('50.00', $attendee->registration->fresh()->total);

        app(EventPaymentService::class)->markPaid($attendee->registration->fresh(), $this->admin, 10.0);
        $this->edit(['attending_dining' => false])->assertSessionHasErrors('attending_dining');
        $this->assertTrue($this->attendee->fresh()->attending_dining);
        $this->assertSame('50.00', $attendee->registration->fresh()->total);
    }

    public function test_only_organisers_of_that_club_and_event_can_edit_a_meal(): void
    {
        $member = $this->club->users()->where('role', 'member')->first();
        $this->edit($this->meal(), null, $member)->assertForbidden();

        $type = ClubType::first();
        $otherClub = Club::create(['club_type_id' => $type->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);
        $outsider = User::factory()->create();
        $otherClub->users()->attach($outsider->id, ['role' => 'admin', 'status' => 'active']);
        $this->edit($this->meal(), null, $outsider)->assertForbidden();

        $otherEvent = Event::create(['club_id' => $this->club->id, 'title' => 'Other', 'slug' => 'other', 'starts_at' => now()->addWeek(), 'status' => 'upcoming']);
        $this->edit($this->meal(), null, null, $otherEvent->id)->assertNotFound();

        $this->assertNull($this->attendee->fresh()->dietary_requirements);
    }
}
