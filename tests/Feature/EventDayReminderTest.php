<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventDayReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private EventRegistration $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $club = Club::factory()->create();
        $this->member = User::factory()->create();
        $event = Event::create(['club_id' => $club->id, 'title' => 'Ladies Night', 'slug' => 'ladies-night', 'starts_at' => '2027-03-12 19:00:00', 'location' => 'Masonic Hall', 'status' => 'upcoming']);
        $this->booking = EventRegistration::create(['event_id' => $event->id, 'user_id' => $this->member->id, 'contact_name' => 'Tom Hart', 'status' => 'attending']);
    }

    public function test_people_booked_hear_about_it_once_on_the_morning_before(): void
    {
        $this->travelTo('2027-03-11 10:00:00');

        $this->artisan('app:send-event-day-reminders')->assertSuccessful();
        $this->artisan('app:send-event-day-reminders')->assertSuccessful();

        $reminder = $this->member->notifications()->sole();
        $this->assertSame('Tomorrow: Ladies Night', $reminder->data['title']);
        $this->assertSame('19:00 · Masonic Hall', $reminder->data['body']);
        $this->assertSame('reminder', $reminder->data['category']);
    }

    public function test_nobody_is_woken_before_nine_or_reminded_about_a_cancelled_booking(): void
    {
        $this->travelTo('2027-03-11 07:00:00');
        $this->artisan('app:send-event-day-reminders');
        $this->assertSame(0, $this->member->notifications()->count());

        $this->booking->update(['status' => 'cancelled']);
        $this->travelTo('2027-03-11 10:00:00');
        $this->artisan('app:send-event-day-reminders');
        $this->assertSame(0, $this->member->notifications()->count());
    }
}
