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

class EventOrganiserBookingTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private Event $event;

    private User $admin;

    private User $member;

    private EventPaymentMethod $bank;

    private EventPaymentMethod $card;

    /** @var array<string, list<EventMenuItem>> */
    private array $menu = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['events.online_payments' => true]);
        $type = ClubType::create(['name' => 'Masonic Lodge', 'code' => 'masonic', 'available_modules' => [], 'default_settings' => []]);
        $this->club = Club::create(['club_type_id' => $type->id, 'name' => 'Club A', 'slug' => 'club-a', 'status' => 'active']);
        $this->event = Event::create(['club_id' => $this->club->id, 'title' => 'Dinner', 'slug' => 'dinner', 'starts_at' => now()->addWeeks(2), 'status' => 'upcoming', 'visibility' => Visibility::Club, 'has_dining' => true, 'requires_payment' => true, 'price' => 30, 'dining_price' => 20, 'capacity' => 3, 'waitlist_enabled' => false, 'max_guests_per_booking' => 1]);

        foreach (['starter', 'main', 'dessert'] as $course) {
            foreach ([1, 2] as $n) {
                $this->menu[$course][] = EventMenuItem::create(['event_id' => $this->event->id, 'category' => $course, 'name' => ucfirst($course)." {$n}", 'sort_order' => $n]);
            }
        }

        $bank = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => 'bank_transfer', 'label' => 'Bank', 'config' => ['sort_code' => '20-00-00', 'account_number' => '12345678']]);
        $card = ClubPaymentMethod::create(['club_id' => $this->club->id, 'type' => 'card_online', 'label' => 'Card', 'config' => ['stripe_secret_key' => 'sk', 'stripe_webhook_secret' => 'wh']]);
        $this->bank = EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $bank->id, 'is_enabled' => true]);
        $this->card = EventPaymentMethod::create(['event_id' => $this->event->id, 'payment_method_id' => $card->id, 'is_enabled' => true]);

        $this->admin = $this->join($this->club, 'admin');
        $this->member = $this->join($this->club, 'member', ['name' => 'Alan Archer', 'email' => 'alan@example.test'], ['rank' => 'PM', 'home_club_name' => 'Fraternity Lodge', 'member_number' => 'M-1234', 'phone' => '0700 111', 'dietary_notes' => 'Vegetarian']);
    }

    private function join(Club $club, string $role, array $user = [], array $pivot = []): User
    {
        $created = User::factory()->create($user);
        $club->users()->attach($created->id, ['role' => $role, 'status' => 'active'] + $pivot);

        return $created;
    }

    private function person(string $name, array $extra = []): array
    {
        return $extra + ['name' => $name, 'attending_dining' => true, 'starter_item_id' => $this->menu['starter'][0]->id, 'main_item_id' => $this->menu['main'][0]->id, 'dessert_item_id' => $this->menu['dessert'][0]->id];
    }

    private function add(array $data, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->post(route('admin.events.registrations.store', ['clubSlug' => 'club-a', 'id' => $this->event->id]), $data + ['payment_method' => $this->bank->id]);
    }

    public function test_member_search_finds_this_clubs_active_members_by_name_email_or_number_and_shows_existing_bookings(): void
    {
        $url = fn (string $q) => route('admin.events.member_search', ['clubSlug' => 'club-a', 'id' => $this->event->id, 'q' => $q]);
        $other = Club::create(['club_type_id' => ClubType::first()->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']);
        $this->join($other, 'member', ['name' => 'Alan Elsewhere']);
        $pending = User::factory()->create(['name' => 'Alan Pending']);
        $this->club->users()->attach($pending->id, ['role' => 'member', 'status' => 'pending']);

        $result = $this->actingAs($this->admin)->get($url('alan'))->assertOk()->json('members');
        $this->assertCount(1, $result, 'no other club, no pending member');
        $this->assertSame(['name' => 'Alan Archer', 'email' => 'alan@example.test', 'phone' => '0700 111', 'rank' => 'PM', 'lodge' => 'Fraternity Lodge', 'member_number' => 'M-1234', 'dietary_notes' => 'Vegetarian', 'registration_id' => null, 'registration_status' => null], collect($result[0])->except('id')->all());

        $this->assertCount(1, $this->actingAs($this->admin)->get($url('M-12'))->json('members'));
        $this->assertCount(1, $this->actingAs($this->admin)->get($url('alan@example'))->json('members'));
        $this->assertSame([], $this->actingAs($this->admin)->get($url('a'))->json('members'), 'needs two characters');
        $this->assertSame([], $this->actingAs($this->admin)->get($url('%%'))->json('members'), 'a typed wildcard matches nothing');

        $this->add(['attendees' => [$this->person('Alan Archer')], 'member_id' => $this->member->id])->assertSessionHasNoErrors();
        $this->assertNotNull($this->actingAs($this->admin)->get($url('alan'))->json('members.0.registration_id'));
    }

    public function test_only_organisers_of_this_club_can_search_and_book(): void
    {
        $url = route('admin.events.member_search', ['clubSlug' => 'club-a', 'id' => $this->event->id, 'q' => 'alan']);
        $outsider = $this->join(Club::create(['club_type_id' => ClubType::first()->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']), 'admin');
        $member = $this->join($this->club, 'member');

        foreach ([$member, $outsider] as $user) {
            $this->actingAs($user)->get($url)->assertForbidden();
            $this->add(['attendees' => [['name' => 'X']]], $user)->assertForbidden();
        }

        $this->assertSame(0, EventRegistration::count());
    }

    public function test_booking_a_member_links_their_account_prices_it_with_meals_and_refuses_a_second_booking(): void
    {
        $this->add([
            'member_id' => $this->member->id,
            'attendees' => [$this->person('Alan Archer', ['organisation' => 'Fraternity Lodge', 'dietary_requirements' => 'Vegetarian']), $this->person('Gina Guest', ['is_guest' => true, 'organisation' => 'Visitor Lodge'])],
            'internal_note' => 'Sits with the Master',
        ])->assertSessionHasNoErrors();

        $registration = EventRegistration::sole();
        $this->assertSame($this->member->id, $registration->user_id);
        $this->assertSame('alan@example.test', $registration->contact_email, 'defaults to the member\'s own email');
        $this->assertSame($this->admin->id, $registration->created_by);
        $this->assertSame('Sits with the Master', $registration->internal_note);
        $this->assertSame('100.00', $registration->total, 'two people, 30 ticket and 20 dinner each');
        $this->assertSame(['Fraternity Lodge', 'Visitor Lodge'], $registration->attendees->pluck('organisation')->all());
        $this->assertSame($this->menu['main'][0]->id, $registration->attendees->last()->main_item_id);

        $this->add(['member_id' => $this->member->id, 'attendees' => [$this->person('Alan Archer')]])->assertSessionHasErrors('member_id');
        $this->assertSame(1, EventRegistration::count());
    }

    public function test_a_visitor_is_booked_with_their_lodge_and_the_organiser_ignores_closing_dates_and_the_guest_limit(): void
    {
        $this->event->update(['rsvp_deadline' => now()->subDay(), 'max_guests_per_booking' => 0]);

        $this->add([
            'contact_email' => 'visitor@example.test',
            'contact_phone' => '0700 222',
            'attendees' => [$this->person('Vic Visitor', ['organisation' => 'Elsewhere Lodge No 9']), $this->person('Guest One', ['is_guest' => true])],
        ])->assertSessionHasNoErrors();

        $registration = EventRegistration::sole();
        $this->assertNull($registration->user_id);
        $this->assertNotNull($registration->token_hash);
        $this->assertSame('Elsewhere Lodge No 9', $registration->attendees->first()->organisation);
        $this->assertSame('0700 222', $registration->contact_phone);
    }

    public function test_meals_and_dishes_are_checked_per_person(): void
    {
        $this->add(['attendees' => [$this->person('Vic', ['main_item_id' => $this->menu['starter'][0]->id])]])->assertSessionHasErrors('attendees.0.main_item_id');
        $this->add(['attendees' => [$this->person('Vic'), $this->person('Guest', ['is_guest' => true, 'dessert_item_id' => null])]])->assertSessionHasErrors('attendees.1.dessert_item_id');
        $this->add(['attendees' => [['name' => '']]])->assertSessionHasErrors('attendees.0.name');
        $this->assertSame(0, EventRegistration::count());
    }

    public function test_a_full_event_needs_the_override_and_online_payment_cannot_be_chosen_by_the_organiser(): void
    {
        $this->add(['attendees' => [$this->person('One'), $this->person('Two', ['is_guest' => true]), $this->person('Three', ['is_guest' => true])]])->assertSessionHasNoErrors();
        $this->assertSame(3, $this->event->placesTaken());

        $this->add(['attendees' => [$this->person('Four')]])->assertSessionHasErrors('capacity');
        $this->assertSame(1, EventRegistration::count());

        $this->add(['attendees' => [$this->person('Four')], 'over_capacity' => true])->assertSessionHasNoErrors();
        $this->assertSame(4, $this->event->placesTaken());

        $this->add(['attendees' => [$this->person('Five')], 'over_capacity' => true, 'payment_method' => $this->card->id])->assertSessionHasErrors('payment_method');
        $this->assertSame(2, EventRegistration::count());
    }

    public function test_marking_paid_now_needs_billing_permission_and_lands_in_the_payment_history(): void
    {
        $coach = $this->join($this->club, 'coach');
        $paid = ['enabled' => true, 'comment' => 'Cash at the door'];

        $this->add(['attendees' => [$this->person('Vic')], 'mark_paid' => $paid], $coach)->assertSessionHasErrors('mark_paid');
        $this->assertSame(0, EventRegistration::count(), 'nothing is saved when the payment is refused');

        $this->add(['attendees' => [$this->person('Vic')], 'mark_paid' => $paid])->assertSessionHasNoErrors();
        $registration = EventRegistration::sole();
        $this->assertSame('paid', $registration->payment_status);
        $this->assertDatabaseHas('event_payment_log', ['registration_id' => $registration->id, 'user_id' => $this->admin->id, 'comment' => 'Cash at the door']);
    }

    public function test_the_confirmation_email_is_only_sent_when_asked_and_a_visitor_gets_their_private_link(): void
    {
        Mail::fake();

        $this->add(['contact_email' => 'quiet@example.test', 'attendees' => [$this->person('Quiet')]])->assertSessionHasNoErrors();
        Mail::assertNothingQueued();

        $this->add(['contact_email' => 'vic@example.test', 'send_confirmation' => true, 'attendees' => [$this->person('Vic')]])->assertSessionHasNoErrors();
        Mail::assertQueued(EventBookingMail::class, fn ($mail) => $mail->hasTo('vic@example.test') && str_contains($mail->render(), '/booking/'));

        $this->add(['member_id' => $this->member->id, 'send_confirmation' => true, 'attendees' => [$this->person('Alan Archer')]])->assertSessionHasNoErrors();
        Mail::assertQueued(EventBookingMail::class, fn ($mail) => $mail->hasTo('alan@example.test') && str_contains($mail->render(), route('member.events', ['slug' => 'club-a'])));
    }

    public function test_editing_a_booking_changes_people_and_meals_and_reprices_until_it_is_paid(): void
    {
        $this->add(['contact_email' => 'vic@example.test', 'attendees' => [$this->person('Vic')]])->assertSessionHasNoErrors();
        $registration = EventRegistration::sole();
        $edit = fn (array $data) => $this->actingAs($this->admin)->put(route('admin.events.registrations.update', ['clubSlug' => 'club-a', 'id' => $this->event->id, 'registrationId' => $registration->id]), $data + ['payment_method' => $this->bank->id, 'contact_email' => 'vic@example.test']);

        $shown = $this->actingAs($this->admin)->get(route('admin.events.registrations.show', ['clubSlug' => 'club-a', 'id' => $this->event->id, 'registrationId' => $registration->id]))->assertOk()->json('booking');
        $this->assertSame('Vic', $shown['attendees'][0]['name']);
        $this->assertSame($this->bank->id, $shown['payment_method']);

        $edit(['attendees' => [$this->person('Vic'), $this->person('Guest', ['is_guest' => true])]])->assertSessionHasNoErrors();
        $this->assertSame('100.00', $registration->fresh()->total);
        $this->assertSame(2, $registration->fresh()->attendees()->count());

        $this->actingAs($this->admin)->post(route('admin.events.payment.paid', ['clubSlug' => 'club-a', 'id' => $this->event->id, 'registrationId' => $registration->id]), ['amount' => 100]);
        $edit(['attendees' => [$this->person('Vic')]])->assertSessionHasErrors('registration');
        $this->assertSame(2, $registration->fresh()->attendees()->count());
        $this->assertSame('100.00', $registration->fresh()->total);

        $edit(['attendees' => [$this->person('Vic', ['dietary_requirements' => 'No nuts']), $this->person('Guest', ['is_guest' => true, 'main_item_id' => $this->menu['main'][1]->id])]])->assertSessionHasNoErrors();
        $this->assertSame('No nuts', $registration->fresh()->attendees->first()->dietary_requirements, 'a change that keeps the price the same is fine');
    }

    public function test_a_booking_cannot_be_edited_by_another_clubs_organiser_or_after_check_in(): void
    {
        $this->add(['attendees' => [$this->person('Vic')]])->assertSessionHasNoErrors();
        $registration = EventRegistration::sole();
        $params = ['clubSlug' => 'club-a', 'id' => $this->event->id, 'registrationId' => $registration->id];
        $outsider = $this->join(Club::create(['club_type_id' => ClubType::first()->id, 'name' => 'Club B', 'slug' => 'club-b', 'status' => 'active']), 'admin');

        $this->actingAs($outsider)->put(route('admin.events.registrations.update', $params), ['attendees' => [$this->person('Hijack')]])->assertForbidden();
        $this->actingAs($outsider)->get(route('admin.events.registrations.show', $params))->assertForbidden();

        $registration->attendees()->update(['checked_in_at' => now()]);
        $this->actingAs($this->admin)->put(route('admin.events.registrations.update', $params), ['attendees' => [$this->person('Vic', ['dietary_requirements' => 'x'])], 'payment_method' => $this->bank->id])->assertSessionHasErrors('registration');
    }

    public function test_a_confirmation_can_be_sent_again_and_gives_a_visitor_a_fresh_link(): void
    {
        Mail::fake();
        $this->add(['contact_email' => 'vic@example.test', 'attendees' => [$this->person('Vic')]])->assertSessionHasNoErrors();
        $registration = EventRegistration::sole();
        $oldHash = $registration->token_hash;

        $this->actingAs($this->admin)->post(route('admin.events.registrations.resend', ['clubSlug' => 'club-a', 'id' => $this->event->id, 'registrationId' => $registration->id]))->assertSessionHas('success');

        Mail::assertQueued(EventBookingMail::class, 1);
        $this->assertNotSame($oldHash, $registration->fresh()->token_hash);

        $registration->update(['contact_email' => null]);
        $this->actingAs($this->admin)->post(route('admin.events.registrations.resend', ['clubSlug' => 'club-a', 'id' => $this->event->id, 'registrationId' => $registration->id]))->assertSessionHasErrors('email');
    }

    public function test_the_organiser_price_quote_and_the_registrations_page_carry_what_the_popup_needs(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.events.quote', ['clubSlug' => 'club-a', 'id' => $this->event->id]), ['attendees' => [['is_guest' => false, 'attending_dining' => true], ['is_guest' => true, 'attending_dining' => false]]])
            ->assertOk()->assertJsonPath('charged', true);

        $this->actingAs($this->admin)->get(route('admin.events.subscribers', ['clubSlug' => 'club-a', 'id' => $this->event->id]))->assertInertia(fn ($page) => $page
            ->has('event.tiers')
            ->has('event.payment_options', 1)
            ->where('event.payment_options.0.label', 'Bank')
            ->where('event.is_full', false));

        $this->actingAs($this->join($this->club, 'member'))->postJson(route('admin.events.quote', ['clubSlug' => 'club-a', 'id' => $this->event->id]), ['attendees' => [['is_guest' => false]]])->assertForbidden();
    }
}
