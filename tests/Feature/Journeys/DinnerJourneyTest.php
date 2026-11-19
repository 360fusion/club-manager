<?php

namespace Tests\Feature\Journeys;

use App\Jobs\SendPushNotification;
use App\Models\Club;
use App\Models\ClubPaymentMethod;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Journeys A and B in tests/JOURNEYS.md as one story: an organiser sets up a dinner, a member with the app books it
 * with a guest, pays by bank transfer, is reminded the day before, and is checked in at the door from their ticket.
 * Each step leads into the next, so a break anywhere along the way shows up here.
 */
class DinnerJourneyTest extends TestCase
{
    use RefreshDatabase;

    private Club $lodge;

    private User $organiser;

    private User $tom;

    private User $ann;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake([SendPushNotification::class]);

        $this->lodge = Club::factory()->create(['name' => 'Harbour Lodge', 'slug' => 'harbour']);
        $this->organiser = $this->join('admin', 'Olive Organiser');
        $this->tom = $this->join('member', 'Tom Hart');
        $this->ann = $this->join('member', 'Ann Lee');
        PushDevice::factory()->create(['user_id' => $this->tom->id, 'token' => 'toms-iphone']);
    }

    private function join(string $role, string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $this->lodge->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    /** @return array<string, mixed> */
    private function dinner(array $extra = []): array
    {
        $bank = ClubPaymentMethod::firstOrCreate(
            ['club_id' => $this->lodge->id, 'type' => 'bank_transfer'],
            ['label' => 'Bank transfer', 'is_active' => true, 'config' => ['account_name' => 'Harbour Lodge', 'sort_code' => '20-00-00', 'account_number' => '12345678', 'reference_prefix' => 'LN']],
        );
        $dish = fn (string $course, int $n) => ['category' => $course, 'name' => ucfirst($course)." {$n}", 'description' => ''];

        return $extra + [
            'title' => 'Ladies Night', 'slug' => 'ladies-night', 'starts_at' => now()->addWeeks(3)->setTime(19, 0)->format('Y-m-d\TH:i'),
            'location' => 'Masonic Hall', 'status' => 'draft', 'has_dining' => true, 'requires_payment' => true, 'capacity' => 40, 'max_guests_per_booking' => 2,
            'ticket_tiers' => [['name' => 'Member', 'price' => 30, 'max_quantity' => 0, 'audience' => 'member'], ['name' => 'Guest', 'price' => 40, 'max_quantity' => 0, 'audience' => 'guest']],
            'menu_items' => [...array_map(fn ($n) => $dish('starter', $n), [1, 2]), ...array_map(fn ($n) => $dish('main', $n), [1, 2]), ...array_map(fn ($n) => $dish('dessert', $n), [1, 2])],
            'payment_methods' => [['payment_method_id' => $bank->id, 'is_enabled' => true]],
        ];
    }

    public function test_a_dinner_from_the_organisers_draft_to_the_party_checked_in_at_the_door(): void
    {
        // B1: the organiser saves a draft. Nobody hears about it yet.
        $this->actingAs($this->organiser)->post(route('admin.events.store', ['clubSlug' => 'harbour']), $this->dinner())->assertSessionHasNoErrors();
        $event = Event::where('club_id', $this->lodge->id)->sole();
        $this->assertSame(0, $this->tom->notifications()->count());

        // B2 / A3: publishing tells the members once, and Tom's phone gets a push.
        $this->actingAs($this->organiser)->post(route('admin.events.store', ['clubSlug' => 'harbour']), $this->dinner(['id' => $event->id, 'status' => 'upcoming']))->assertSessionHasNoErrors();
        $this->assertSame('New event: Ladies Night', $this->tom->notifications()->sole()->data['title']);
        $this->assertSame(1, $this->ann->notifications()->count());
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->tom->id && $job->title === 'New event: Ladies Night');
        Queue::assertPushed(SendPushNotification::class, 1);

        // A4: Tom books for himself and a guest, three courses each, by bank transfer.
        $event->load(['menuItems', 'ticketTiers', 'paymentMethods']);
        $dish = fn (string $name) => $event->menuItems->firstWhere('name', $name)->id;
        $tier = fn (string $name) => $event->ticketTiers->firstWhere('name', $name)->id;
        $this->actingAs($this->tom)->post(route('member.rsvp', ['slug' => 'harbour', 'id' => $event->id]), [
            'attendance_status' => 'attending',
            'payment_method' => $event->paymentMethods->sole()->id,
            'attendees' => [
                ['name' => 'Tom Hart', 'ticket_tier_id' => $tier('Member'), 'attending_dining' => true, 'starter_item_id' => $dish('Starter 1'), 'main_item_id' => $dish('Main 1'), 'dessert_item_id' => $dish('Dessert 1')],
                ['name' => 'Sue Hart', 'is_guest' => true, 'ticket_tier_id' => $tier('Guest'), 'attending_dining' => true, 'starter_item_id' => $dish('Starter 2'), 'main_item_id' => $dish('Main 1'), 'dessert_item_id' => $dish('Dessert 2'), 'dietary_requirements' => 'No nuts'],
            ],
        ])->assertSessionHasNoErrors();

        $booking = EventRegistration::where('event_id', $event->id)->sole();
        $this->assertSame('attending', $booking->status);
        $this->assertSame('70.00', $booking->total);
        $this->assertSame('unpaid', $booking->payment_status);
        $this->assertNotEmpty($booking->payment_reference);

        $page = $this->actingAs($this->tom)->get(route('member.events', ['slug' => 'harbour']));
        $page->assertInertia(fn ($inertia) => $inertia->where('events.0.places_left', 38)->where('events.0.user_rsvp.payment.reference', $booking->payment_reference));

        // A5: Add to calendar gives a calendar file at the lodge's time.
        $calendar = $page->viewData('page')['props']['events'][0]['calendar'];
        $this->get($calendar['ics'])->assertOk()->assertSee('SUMMARY:Harbour Lodge: Ladies Night', false);

        // B3 / B4: the organiser's guest list and catering numbers include both of them.
        $this->actingAs($this->organiser)->get(route('admin.events.guest_list', ['clubSlug' => 'harbour', 'id' => $event->id]))->assertOk()->assertSee('Sue Hart')->assertSee('No nuts');
        $this->actingAs($this->organiser)->get(route('admin.events.catering', ['clubSlug' => 'harbour', 'id' => $event->id]))->assertOk()->assertSee('Main 1');

        // A6 / C3: the treasurer records the transfer.
        $this->actingAs($this->organiser)->post(route('admin.events.payment.paid', ['clubSlug' => 'harbour', 'id' => $event->id, 'registrationId' => $booking->id]))->assertSessionHasNoErrors();
        $this->assertSame('paid', $booking->fresh()->payment_status);

        // A7: the morning before, Tom is reminded once, in the inbox and on his phone.
        $this->travelTo($event->fresh()->starts_at->copy()->subDay()->setTime(10, 0));
        $this->artisan('app:send-event-day-reminders')->assertSuccessful();
        $this->artisan('app:send-event-day-reminders')->assertSuccessful();
        $reminder = $this->tom->notifications()->where('data->category', 'reminder')->sole();
        $this->assertSame('Tomorrow: Ladies Night', $reminder->data['title']);
        $this->assertSame(route('member.events.ticket', ['slug' => 'harbour', 'id' => $event->id], false), $reminder->data['url']);
        Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->userId === $this->tom->id && $job->title === 'Tomorrow: Ladies Night');
        $this->assertSame(0, $this->ann->notifications()->where('data->category', 'reminder')->count(), 'Ann has not booked');

        // A8: at the door Tom shows his ticket: both names, paid.
        $this->actingAs($this->tom)->get(route('member.events.ticket', ['slug' => 'harbour', 'id' => $event->id]))
            ->assertInertia(fn ($inertia) => $inertia->where('booking.amount_due', 0)->has('booking.attendees', 2));

        // B5 / A9: the organiser scans it and checks the party in; Tom's events page shows it.
        $door = URL::signedRoute('admin.events.ticket', ['clubSlug' => 'harbour', 'id' => $event->id, 'registration' => $booking->id]);
        $this->actingAs($this->organiser)->get($door)->assertInertia(fn ($inertia) => $inertia->where('booking.name', 'Tom Hart'));
        $this->actingAs($this->organiser)->post(route('admin.events.checkin.store', ['clubSlug' => 'harbour', 'id' => $event->id]), ['registration_id' => $booking->id, 'action' => 'checkin'])
            ->assertSessionHas('success', '2 people checked in.');
        $this->actingAs($this->tom)->get(route('member.events', ['slug' => 'harbour']))
            ->assertInertia(fn ($inertia) => $inertia->whereNot('events.0.user_rsvp.checked_in_at', null));

        // B6: a walk-in is added and checked in too.
        $this->actingAs($this->organiser)->post(route('admin.events.checkin.walk_in', ['clubSlug' => 'harbour', 'id' => $event->id]), ['name' => 'Walter Inn'])->assertSessionHasNoErrors();
        $this->assertSame(3, $event->registrations()->with('attendees')->get()->flatMap->attendees->whereNotNull('checked_in_at')->count());
    }
}
