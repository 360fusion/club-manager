<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EventTicketTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $member;

    private User $organiser;

    private Event $event;

    private EventRegistration $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create(['slug' => 'club-a']);
        $this->member = $this->join('member');
        $this->organiser = $this->join('admin');
        $this->event = Event::create(['club_id' => $this->club->id, 'title' => 'Ladies Night', 'slug' => 'ladies-night', 'starts_at' => now()->addWeek(), 'status' => 'upcoming']);
        $this->booking = EventRegistration::create(['event_id' => $this->event->id, 'user_id' => $this->member->id, 'contact_name' => 'Tom Hart', 'status' => 'attending', 'total' => 70, 'amount_paid' => 35]);
        $this->booking->attendees()->create(['user_id' => $this->member->id, 'name' => 'Tom Hart']);
        $this->booking->attendees()->create(['name' => 'Ann Hart', 'is_guest' => true]);
    }

    private function join(string $role): User
    {
        $user = User::factory()->create();
        $this->club->users()->attach($user->id, ['role' => $role, 'status' => 'active']);

        return $user;
    }

    private function doorUrl(): string
    {
        return URL::signedRoute('admin.events.ticket', ['clubSlug' => 'club-a', 'id' => $this->event->id, 'registration' => $this->booking->id]);
    }

    public function test_a_member_sees_a_ticket_for_their_booking_with_what_is_left_to_pay(): void
    {
        $this->actingAs($this->member)->get(route('member.events.ticket', ['slug' => 'club-a', 'id' => $this->event->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Member/EventTicket')
                ->where('booking.amount_due', 35)
                ->where('booking.attendees.1.name', 'Ann Hart')
                ->where('qrSvg', fn (string $svg) => str_starts_with($svg, '<svg')));
    }

    public function test_there_is_no_ticket_without_a_booking_or_once_it_is_cancelled(): void
    {
        $this->actingAs($this->organiser)->get(route('member.events.ticket', ['slug' => 'club-a', 'id' => $this->event->id]))->assertNotFound();

        $this->booking->update(['status' => 'cancelled']);
        $this->actingAs($this->member)->get(route('member.events.ticket', ['slug' => 'club-a', 'id' => $this->event->id]))->assertNotFound();
    }

    public function test_scanning_a_ticket_shows_the_organiser_the_booking(): void
    {
        $this->actingAs($this->organiser)->get($this->doorUrl())
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Events/Ticket')
                ->where('booking.name', 'Tom Hart')
                ->where('booking.holds_place', true)
                ->has('booking.attendees', 2));
    }

    public function test_a_ticket_link_that_was_changed_or_scanned_by_a_member_is_refused(): void
    {
        $this->actingAs($this->organiser)->get(str_replace("/ticket/{$this->booking->id}", '/ticket/999', $this->doorUrl()))->assertForbidden();
        $this->actingAs($this->member)->get($this->doorUrl())->assertForbidden();
    }

    public function test_checking_in_a_booking_checks_in_everyone_in_it_and_no_one_else(): void
    {
        $other = EventRegistration::create(['event_id' => $this->event->id, 'contact_name' => 'Someone Else', 'status' => 'attending']);
        $stranger = $other->attendees()->create(['name' => 'Someone Else']);

        $this->actingAs($this->organiser)
            ->post(route('admin.events.checkin.store', ['clubSlug' => 'club-a', 'id' => $this->event->id]), ['registration_id' => $this->booking->id, 'action' => 'checkin'])
            ->assertSessionHas('success', '2 people checked in.');

        $this->assertSame(2, $this->booking->attendees()->whereNotNull('checked_in_at')->count());
        $this->assertNull($stranger->fresh()->checked_in_at);
    }

    public function test_a_booking_from_another_event_cannot_be_checked_in_here(): void
    {
        $elsewhere = Event::create(['club_id' => $this->club->id, 'title' => 'Other', 'slug' => 'other', 'starts_at' => now()->addWeek(), 'status' => 'upcoming']);

        $this->actingAs($this->organiser)
            ->post(route('admin.events.checkin.store', ['clubSlug' => 'club-a', 'id' => $elsewhere->id]), ['registration_id' => $this->booking->id, 'action' => 'checkin'])
            ->assertNotFound();

        $this->assertSame(0, $this->booking->attendees()->whereNotNull('checked_in_at')->count());
    }
}
