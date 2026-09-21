<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubType;
use App\Models\Event;
use App\Models\EventMenuItem;
use App\Models\User;
use App\Services\Events\EventRegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventAdminFormTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->admin = $this->join('admin');
    }

    private function join(string $role): User
    {
        $user = User::factory()->create();
        $this->club->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $extra = []): array
    {
        $dish = fn (string $course, int $n) => ['category' => $course, 'name' => ucfirst($course)." {$n}", 'description' => ''];

        return $extra + [
            'title' => 'Annual Dinner', 'slug' => 'annual-dinner', 'starts_at' => now()->addWeeks(3)->format('Y-m-d\TH:i'), 'status' => 'upcoming',
            'has_dining' => true, 'capacity' => 40, 'waitlist_enabled' => true, 'max_guests_per_booking' => 2,
            'ticket_tiers' => [['name' => 'Member', 'price' => 30, 'max_quantity' => 0, 'audience' => 'member'], ['name' => 'Guest', 'price' => 40, 'max_quantity' => 0, 'audience' => 'guest']],
            'menu_items' => [...array_map(fn ($n) => $dish('starter', $n), [1, 2, 3]), ...array_map(fn ($n) => $dish('main', $n), [1, 2, 3]), ...array_map(fn ($n) => $dish('dessert', $n), [1, 2, 3])],
        ];
    }

    private function store(array $extra = [])
    {
        return $this->actingAs($this->admin)->post(route('admin.events.store', ['clubSlug' => 'club-a']), $this->payload($extra));
    }

    public function test_an_event_is_saved_with_places_ticket_audiences_and_three_dishes_per_course(): void
    {
        $this->store()->assertSessionHasNoErrors();

        $event = Event::with(['ticketTiers', 'menuItems'])->sole();

        $this->assertSame(40, $event->capacity);
        $this->assertTrue($event->waitlist_enabled);
        $this->assertSame(2, $event->max_guests_per_booking);
        $this->assertEqualsCanonicalizing(['member', 'guest'], $event->ticketTiers->pluck('audience')->all());
        $this->assertSame([3, 3, 3], [$event->menuItems->where('category', 'starter')->count(), $event->menuItems->where('category', 'main')->count(), $event->menuItems->where('category', 'dessert')->count()]);
    }

    public function test_editing_an_event_keeps_dish_ids_so_existing_meal_choices_survive(): void
    {
        $this->store();
        $event = Event::with('menuItems')->sole();
        $member = $this->join('member');
        $dishes = $event->menuItems->groupBy('category');

        app(EventRegistrationService::class)->register($event, $member, ['attendees' => [[
            'name' => 'Me', 'attending_dining' => true,
            'starter_item_id' => $dishes['starter'][0]->id, 'main_item_id' => $dishes['main'][1]->id, 'dessert_item_id' => $dishes['dessert'][2]->id,
            'ticket_tier_id' => $event->ticketTiers()->where('audience', 'member')->value('id'),
        ]]]);

        $edit = $this->payload(['id' => $event->id, 'title' => 'Renamed Dinner']);
        $edit['menu_items'] = $event->menuItems->map(fn (EventMenuItem $d) => ['id' => $d->id, 'category' => $d->category, 'name' => $d->name === 'Main 2' ? 'Roast Lamb' : $d->name, 'description' => ''])->all();
        $edit['ticket_tiers'] = $event->ticketTiers->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'price' => 35, 'max_quantity' => 0, 'audience' => $t->audience])->all();

        $this->actingAs($this->admin)->post(route('admin.events.store', ['clubSlug' => 'club-a']), $edit)->assertSessionHasNoErrors();

        $attendee = $event->registrations()->sole()->attendees()->sole();
        $this->assertSame('Roast Lamb', $attendee->main->name, 'the same dish, renamed');
        $this->assertNotNull($attendee->ticket_tier_id);
        $this->assertSame(9, EventMenuItem::where('event_id', $event->id)->count());
    }

    public function test_a_dish_or_ticket_type_people_have_chosen_cannot_be_removed(): void
    {
        $this->store();
        $event = Event::with(['menuItems', 'ticketTiers'])->sole();
        $dishes = $event->menuItems->groupBy('category');
        $tier = $event->ticketTiers->firstWhere('audience', 'member');

        app(EventRegistrationService::class)->register($event, $this->join('member'), ['attendees' => [[
            'name' => 'Me', 'attending_dining' => true, 'ticket_tier_id' => $tier->id,
            'starter_item_id' => $dishes['starter'][0]->id, 'main_item_id' => $dishes['main'][0]->id, 'dessert_item_id' => $dishes['dessert'][0]->id,
        ]]]);

        $withoutDish = $this->payload(['id' => $event->id]);
        $withoutDish['menu_items'] = $event->menuItems->reject(fn ($d) => $d->id === $dishes['main'][0]->id)->map(fn ($d) => ['id' => $d->id, 'category' => $d->category, 'name' => $d->name])->values()->all();
        $withoutDish['ticket_tiers'] = $event->ticketTiers->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'price' => $t->price, 'audience' => $t->audience])->all();
        $this->actingAs($this->admin)->post(route('admin.events.store', ['clubSlug' => 'club-a']), $withoutDish)->assertSessionHasErrors('menu_items');

        $withoutTier = $this->payload(['id' => $event->id]);
        $withoutTier['menu_items'] = $event->menuItems->map(fn ($d) => ['id' => $d->id, 'category' => $d->category, 'name' => $d->name])->all();
        $withoutTier['ticket_tiers'] = [['id' => $event->ticketTiers->firstWhere('audience', 'guest')->id, 'name' => 'Guest', 'price' => 40, 'audience' => 'guest']];
        $this->actingAs($this->admin)->post(route('admin.events.store', ['clubSlug' => 'club-a']), $withoutTier)->assertSessionHasErrors('ticket_tiers');

        $this->assertSame(9, EventMenuItem::where('event_id', $event->id)->count());
        $this->assertSame(2, $event->ticketTiers()->count());
    }

    public function test_public_registration_needs_a_public_event(): void
    {
        $this->store(['visibility' => 'club', 'allow_public_registration' => true])->assertSessionHasErrors('allow_public_registration');
        $this->assertSame(0, Event::count());

        $this->store(['visibility' => 'public', 'rsvp_audience' => 'public', 'allow_public_registration' => true, 'slug' => 'open-dinner'])->assertSessionHasNoErrors();
        $this->assertTrue(Event::sole()->allow_public_registration);
    }

    public function test_drafts_are_hidden_and_publishing_notifies_members_once(): void
    {
        $member = $this->join('member');

        $this->store(['status' => 'draft']);
        $event = Event::sole();
        $this->assertCount(0, $member->fresh()->notifications, 'a draft tells nobody');
        $this->actingAs($member)->get(route('member.events', ['slug' => 'club-a']))->assertInertia(fn ($page) => $page->has('events', 0));

        $this->actingAs($this->admin)->post(route('admin.events.store', ['clubSlug' => 'club-a']), $this->payload(['id' => $event->id, 'status' => 'upcoming']));
        $this->assertCount(1, $member->fresh()->notifications);
        $this->actingAs($member)->get(route('member.events', ['slug' => 'club-a']))->assertInertia(fn ($page) => $page->has('events', 1));

        $this->actingAs($this->admin)->post(route('admin.events.store', ['clubSlug' => 'club-a']), $this->payload(['id' => $event->id, 'title' => 'Edited']));
        $this->assertCount(1, $member->fresh()->notifications, 'editing a live event does not notify again');
    }

    public function test_an_event_can_be_duplicated_as_a_draft_and_cancelled(): void
    {
        $this->store();
        $event = Event::sole();
        app(EventRegistrationService::class)->register($event, $this->join('member'), ['attendees' => [['name' => 'Me']]]);

        $this->actingAs($this->admin)->post(route('admin.events.duplicate', ['clubSlug' => 'club-a', 'id' => $event->id]))->assertRedirect();

        $copy = Event::where('id', '!=', $event->id)->with(['menuItems', 'ticketTiers'])->sole();
        $this->assertSame('draft', $copy->status);
        $this->assertSame('Copy of Annual Dinner', $copy->title);
        $this->assertCount(9, $copy->menuItems);
        $this->assertCount(2, $copy->ticketTiers);
        $this->assertSame(0, $copy->registrations()->count());
        $this->assertNotSame($event->slug, $copy->slug);

        $this->actingAs($this->admin)->post(route('admin.events.cancel', ['clubSlug' => 'club-a', 'id' => $event->id]))->assertRedirect();
        $this->assertSame('cancelled', $event->fresh()->status);
        $this->assertSame(1, $event->registrations()->count(), 'bookings are kept');
    }

    public function test_members_cannot_use_the_organiser_actions(): void
    {
        $this->store();
        $event = Event::sole();
        $member = $this->join('member');

        foreach (['admin.events.duplicate', 'admin.events.cancel'] as $route) {
            $this->actingAs($member)->post(route($route, ['clubSlug' => 'club-a', 'id' => $event->id]))->assertForbidden();
        }
    }

    public function test_an_organiser_can_add_cancel_and_move_up_bookings_and_members_cannot(): void
    {
        $this->store(['capacity' => 1, 'waitlist_enabled' => true]);
        $event = Event::sole();
        $service = app(EventRegistrationService::class);
        $first = $service->register($event, $this->join('member'), ['attendees' => [['name' => 'First']]]);
        $waiting = $service->register($event, $this->join('member'), ['attendees' => [['name' => 'Second']]]);
        $this->assertSame('waitlisted', $waiting->status);

        $base = ['clubSlug' => 'club-a', 'id' => $event->id];

        $this->actingAs($this->admin)->post(route('admin.events.registrations.store', $base), ['name' => 'Phoned In', 'email' => 'phone@example.test'])->assertSessionHasNoErrors();
        $manual = $event->registrations()->where('contact_name', 'Phoned In')->sole();
        $this->assertSame('attending', $manual->status, 'the organiser can go over capacity');
        $this->assertNull($manual->user_id);

        $this->actingAs($this->admin)->post(route('admin.events.registrations.promote', $base + ['registrationId' => $waiting->id]))->assertRedirect();
        $this->assertSame('attending', $waiting->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.events.registrations.cancel', $base + ['registrationId' => $first->id]))->assertRedirect();
        $this->assertSame('cancelled', $first->fresh()->status);

        $member = $this->join('member');
        foreach (['registrations.store' => [], 'registrations.cancel' => ['registrationId' => $waiting->id], 'registrations.promote' => ['registrationId' => $waiting->id]] as $name => $extra) {
            $this->actingAs($member)->post(route("admin.events.{$name}", $base + $extra), ['name' => 'X'])->assertForbidden();
        }

        $otherEvent = Event::create(['club_id' => $this->club->id, 'title' => 'Other', 'slug' => 'other', 'starts_at' => now()->addWeek(), 'status' => 'upcoming']);
        $this->actingAs($this->admin)->post(route('admin.events.registrations.cancel', ['clubSlug' => 'club-a', 'id' => $otherEvent->id, 'registrationId' => $waiting->id]))->assertNotFound();
    }
}
